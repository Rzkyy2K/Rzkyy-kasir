<?php

namespace App\Http\Controllers\Api;

use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SupplierController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Supplier::with('sekolah')->where('is_delete', 0);
        if ($request->filled('id_sekolah')) {
            $query->where('id_sekolah', $request->integer('id_sekolah'));
        }
        if ($request->filled('search')) {
            $query->where('nama', 'like', '%'.$request->string('search')->toString().'%');
        }

        return $this->ok($query->orderBy('nama')->paginate(min($request->integer('per_page', 15), 100)));
    }

    public function show(int $id): JsonResponse
    {
        $supplier = Supplier::with(['pembelian' => fn ($q) => $q->orderByDesc('tanggal_faktur')->limit(10)])
            ->where('is_delete', 0)->find($id);

        return $supplier ? $this->ok($supplier) : $this->fail('Supplier tidak ditemukan.', 404);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_sekolah' => 'required|integer|exists:tb_sekolah,id_sekolah',
            'nama' => 'required|string|max:100',
            'no_telepon' => 'nullable|string|max:20',
            'alamat_supplier' => 'nullable|string',
        ]);

        try {
            $supplier = Supplier::create([
                ...$data, 'is_delete' => 0, 'created_at' => now(), 'created_by' => 1,
            ]);

            return $this->created($supplier, 'Supplier berhasil ditambahkan.');
        } catch (QueryException $e) {
            return $this->storeError($e);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::where('is_delete', 0)->find($id);
        if (! $supplier) {
            return $this->fail('Supplier tidak ditemukan.', 404);
        }

        $data = $request->validate([
            'id_sekolah' => 'sometimes|integer|exists:tb_sekolah,id_sekolah',
            'nama' => 'sometimes|string|max:100',
            'no_telepon' => 'nullable|string|max:20',
            'alamat_supplier' => 'nullable|string',
        ]);

        $supplier->update($data);

        return $this->ok($supplier->fresh(), 'Supplier berhasil diperbarui.');
    }

    public function destroy(int $id): JsonResponse
    {
        $supplier = Supplier::find($id);
        if (! $supplier) {
            return $this->fail('Supplier tidak ditemukan.', 404);
        }

        try {
            Schema::disableForeignKeyConstraints();
            DB::table('tb_barang')->where('id_supplier', $id)->update(['id_supplier' => null]);
            DB::table('tb_pembelian')->where('id_supplier', $id)->delete();
            $supplier->delete();
            Schema::enableForeignKeyConstraints();

            return $this->ok(null, 'Supplier berhasil dihapus.');
        } catch (QueryException $e) {
            Schema::enableForeignKeyConstraints();

            return $this->storeError($e);
        }
    }
}
