<?php

namespace App\Http\Controllers\Api;

use App\Models\Barang;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class BarangController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Barang::with(['kategori', 'kelompokKategori', 'supplier', 'sekolah'])
            ->where('is_delete', 0);

        if ($request->filled('id_sekolah')) {
            $query->where('id_sekolah', $request->integer('id_sekolah'));
        }
        if ($request->filled('search')) {
            $s = $request->string('search')->toString();
            $query->where(fn ($q) => $q->where('nama', 'like', "%{$s}%")->orWhere('barcode', 'like', "%{$s}%"));
        }
        if ($request->filled('id_kategori')) {
            $query->where('id_kategori', $request->integer('id_kategori'));
        }
        if ($request->filled('id_kelompok_kategori')) {
            $query->where('id_kelompok_kategori', $request->integer('id_kelompok_kategori'));
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        if ($request->filled('stok_rendah')) {
            $query->where('stok', '<=', (int) $request->input('stok_rendah', 10));
        }

        $perPage = min($request->integer('per_page', 15), 100);

        return $this->ok($query->orderBy('nama')->paginate($perPage));
    }

    public function show(int $id): JsonResponse
    {
        $barang = Barang::with(['kategori', 'kelompokKategori', 'supplier', 'sekolah'])
            ->where('is_delete', 0)->find($id);

        return $barang ? $this->ok($barang) : $this->fail('Barang tidak ditemukan.', 404);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_sekolah' => 'required|integer|exists:tb_sekolah,id_sekolah',
            'barcode' => 'nullable|string|max:50|unique:tb_barang,barcode',
            'nama' => 'required|string|max:150',
            'id_kategori' => 'required|integer|exists:tb_kategori,id_kategori',
            'id_kelompok_kategori' => 'required|integer|exists:tb_kelompok_kategori,id_kelompok',
            'id_supplier' => 'required|integer|exists:tb_supplier,id_supplier',
            'satuan' => 'required|string|max:20',
            'harga_beli' => 'required|numeric|min:0',
            'harga_jual' => 'required|numeric|min:0',
            'stok' => 'required|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $barang = Barang::create([
                ...$data,
                'is_active' => $data['is_active'] ?? true,
                'is_delete' => 0,
                'created_at' => now(),
                'created_by' => 1,
                'updated_at' => now(),
                'updated_by' => 1,
            ]);

            return $this->created($barang->load(['kategori', 'supplier']), 'Barang berhasil ditambahkan.');
        } catch (QueryException $e) {
            return $this->storeError($e);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $barang = Barang::where('is_delete', 0)->find($id);
        if (! $barang) {
            return $this->fail('Barang tidak ditemukan.', 404);
        }

        $data = $request->validate([
            'id_sekolah' => 'sometimes|integer|exists:tb_sekolah,id_sekolah',
            'barcode' => ['nullable', 'string', 'max:50', Rule::unique('tb_barang', 'barcode')->ignore($id, 'id_barang')],
            'nama' => 'sometimes|string|max:150',
            'id_kategori' => 'sometimes|integer|exists:tb_kategori,id_kategori',
            'id_kelompok_kategori' => 'sometimes|integer|exists:tb_kelompok_kategori,id_kelompok',
            'id_supplier' => 'sometimes|integer|exists:tb_supplier,id_supplier',
            'satuan' => 'sometimes|string|max:20',
            'harga_beli' => 'sometimes|numeric|min:0',
            'harga_jual' => 'sometimes|numeric|min:0',
            'stok' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $barang->update([...$data, 'updated_at' => now(), 'updated_by' => 1]);

            return $this->ok($barang->fresh(['kategori', 'supplier']), 'Barang berhasil diperbarui.');
        } catch (QueryException $e) {
            return $this->fail('Barang gagal diperbarui. Silakan coba lagi.', 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $barang = Barang::find($id);
        if (! $barang) {
            return $this->fail('Barang tidak ditemukan.', 404);
        }

        try {
            Schema::disableForeignKeyConstraints();
            DB::table('tb_detail_penjualan')->where('id_barang', $id)->delete();
            DB::table('tb_detail_pembelian')->where('id_barang', $id)->delete();
            $barang->delete();
            Schema::enableForeignKeyConstraints();

            return $this->ok(null, 'Barang berhasil dihapus. Barcode/kode bisa dipakai lagi.');
        } catch (QueryException $e) {
            Schema::enableForeignKeyConstraints();

            return $this->storeError($e);
        }
    }

    /** Penyesuaian stok lewat backend (stok masuk/keluar/opname). */
    public function adjustStock(Request $request, int $id): JsonResponse
    {
        $barang = Barang::where('is_delete', 0)->find($id);
        if (! $barang) {
            return $this->fail('Barang tidak ditemukan.', 404);
        }

        $data = $request->validate([
            'tipe' => 'required|in:masuk,keluar,opname',
            'jumlah' => 'required|integer|min:0',
            'catatan' => 'nullable|string',
        ]);

        $baru = match ($data['tipe']) {
            'masuk' => $barang->stok + $data['jumlah'],
            'keluar' => $barang->stok - $data['jumlah'],
            'opname' => $data['jumlah'],
        };

        if ($baru < 0) {
            return $this->fail('Stok tidak mencukupi untuk pengurangan tersebut.', 422);
        }

        $barang->update(['stok' => $baru, 'updated_at' => now(), 'updated_by' => 1]);

        return $this->ok($barang->fresh(), 'Stok berhasil diperbarui.');
    }
}
