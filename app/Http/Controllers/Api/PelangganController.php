<?php

namespace App\Http\Controllers\Api;

use App\Models\KelompokPelanggan;
use App\Models\Pelanggan;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PelangganController extends BaseApiController
{
    public function kelompok(Request $request): JsonResponse
    {
        $query = KelompokPelanggan::query();
        if ($request->filled('id_sekolah')) {
            $query->where('id_sekolah', $request->integer('id_sekolah'));
        }

        return $this->ok($query->orderBy('nama_kelompok')->get());
    }

    public function storeKelompok(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_sekolah' => 'nullable|integer|exists:tb_sekolah,id_sekolah',
            'nama_kelompok' => 'required|string|max:100',
        ]);

        try {
            return $this->created(KelompokPelanggan::create($data), 'Kelompok pelanggan berhasil ditambahkan.');
        } catch (QueryException $e) {
            return $this->storeError($e);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $query = Pelanggan::with('kelompok')->where('is_delete', 0);
        if ($request->filled('search')) {
            $s = $request->string('search')->toString();
            $query->where(fn ($q) => $q->where('nama_pelanggan', 'like', "%{$s}%")->orWhere('telepon', 'like', "%{$s}%"));
        }
        if ($request->filled('id_kelompok_pelanggan')) {
            $query->where('id_kelompok_pelanggan', $request->integer('id_kelompok_pelanggan'));
        }

        return $this->ok($query->orderBy('nama_pelanggan')->paginate(min($request->integer('per_page', 15), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_kelompok_pelanggan' => 'nullable|integer|exists:tb_kelompok_pelanggan,id_kelompok_pelanggan',
            'nama_pelanggan' => 'required|string|max:150',
            'telepon' => 'nullable|string|max:20',
            'alamat' => 'nullable|string',
        ]);

        try {
            $pelanggan = Pelanggan::create([
                ...$data, 'is_delete' => 0, 'created_at' => now(), 'created_by' => 1,
                'updated_at' => now(), 'updated_by' => 1,
            ]);

            return $this->created($pelanggan->load('kelompok'), 'Pelanggan berhasil ditambahkan.');
        } catch (QueryException $e) {
            return $this->storeError($e);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $pelanggan = Pelanggan::where('is_delete', 0)->find($id);
        if (! $pelanggan) {
            return $this->fail('Pelanggan tidak ditemukan.', 404);
        }

        $data = $request->validate([
            'id_kelompok_pelanggan' => 'nullable|integer|exists:tb_kelompok_pelanggan,id_kelompok_pelanggan',
            'nama_pelanggan' => 'sometimes|string|max:150',
            'telepon' => 'nullable|string|max:20',
            'alamat' => 'nullable|string',
        ]);

        $pelanggan->update([...$data, 'updated_at' => now(), 'updated_by' => 1]);

        return $this->ok($pelanggan->fresh('kelompok'), 'Pelanggan berhasil diperbarui.');
    }

    public function destroy(int $id): JsonResponse
    {
        $pelanggan = Pelanggan::find($id);
        if (! $pelanggan) {
            return $this->fail('Pelanggan tidak ditemukan.', 404);
        }

        try {
            Schema::disableForeignKeyConstraints();
            DB::table('tb_penjualan')->where('id_pelanggan', $id)->update(['id_pelanggan' => null]);
            $pelanggan->delete();
            Schema::enableForeignKeyConstraints();

            return $this->ok(null, 'Pelanggan berhasil dihapus.');
        } catch (QueryException $e) {
            Schema::enableForeignKeyConstraints();

            return $this->storeError($e);
        }
    }
}
