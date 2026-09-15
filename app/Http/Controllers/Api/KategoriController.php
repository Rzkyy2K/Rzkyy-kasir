<?php

namespace App\Http\Controllers\Api;

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\KelompokKategori;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KategoriController extends BaseApiController
{
    public function kelompok(Request $request): JsonResponse
    {
        $query = KelompokKategori::with('sekolah');
        if ($request->filled('id_sekolah')) {
            $query->where('id_sekolah', $request->integer('id_sekolah'));
        }

        return $this->ok($query->orderBy('nama_kelompok')->get());
    }

    public function storeKelompok(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_sekolah' => 'required|integer|exists:tb_sekolah,id_sekolah',
            'nama_kelompok' => 'required|string|max:100',
        ]);

        try {
            $kelompok = KelompokKategori::create([...$data, 'created_at' => now(), 'created_by' => 1]);

            return $this->created($kelompok, 'Kelompok kategori berhasil ditambahkan.');
        } catch (QueryException $e) {
            return $this->storeError($e);
        }
    }

    public function updateKelompok(Request $request, int $id): JsonResponse
    {
        $kelompok = KelompokKategori::find($id);
        if (! $kelompok) {
            return $this->fail('Kelompok kategori tidak ditemukan.', 404);
        }

        $data = $request->validate([
            'nama_kelompok' => 'required|string|max:100',
        ]);

        $kelompok->update($data);

        return $this->ok($kelompok->fresh(), 'Kelompok kategori berhasil diperbarui.');
    }

    public function destroyKelompok(int $id): JsonResponse
    {
        $kelompok = KelompokKategori::find($id);
        if (! $kelompok) {
            return $this->fail('Kelompok kategori tidak ditemukan.', 404);
        }

        // Hitung SEMUA baris relasi termasuk yang soft-delete, karena
        // foreign key MySQL menahan baris fisik apa pun flag-nya.
        // Dipecah aktif vs nonaktif agar pesan ke user jujur: data nonaktif
        // memang tidak tampil di halaman Kategori/Stok.
        $info = $this->hitungPemakaianKelompok($id);

        if ($info['total'] > 0) {
            return response()->json([
                'success' => false,
                'message' => "Kelompok tidak dapat dihapus karena masih dipakai {$info['kategori_aktif']} kategori aktif dan {$info['barang_aktif']} barang aktif"
                    .($info['nonaktif'] > 0 ? " (plus {$info['nonaktif']} data nonaktif yang tak tampil di daftar)" : '')
                    .'. Pindahkan dulu datanya ke kelompok lain.',
                'errors' => $info,
            ], 422);
        }

        try {
            $kelompok->delete();
        } catch (QueryException $e) {
            report($e);

            return $this->fail(
                'Kelompok tidak dapat dihapus karena masih dipakai data lain. Pindahkan dulu datanya.',
                422,
            );
        }

        return $this->ok(null, 'Kelompok kategori berhasil dihapus.');
    }

    /**
     * Pindahkan seluruh referensi (aktif + nonaktif) ke kelompok lain,
     * lalu hapus kelompok lama. Satu transaksi agar tidak tersisa sebagian.
     */
    public function pindahkanKelompok(Request $request, int $id): JsonResponse
    {
        $kelompok = KelompokKategori::find($id);
        if (! $kelompok) {
            return $this->fail('Kelompok kategori tidak ditemukan.', 404);
        }

        $data = $request->validate([
            'id_kelompok_tujuan' => 'required|integer|exists:tb_kelompok_kategori,id_kelompok|not_in:'.$id,
        ]);

        try {
            $info = DB::transaction(function () use ($id, $data) {
                $info = $this->hitungPemakaianKelompok($id);

                Kategori::where('id_kelompok', $id)->update(['id_kelompok' => $data['id_kelompok_tujuan']]);
                Barang::where('id_kelompok_kategori', $id)->update(['id_kelompok_kategori' => $data['id_kelompok_tujuan']]);

                KelompokKategori::where('id_kelompok', $id)->delete();

                return $info;
            });

            return $this->ok($info, "Berhasil memindahkan {$info['total']} data ke kelompok lain dan menghapus kelompok.");
        } catch (QueryException $e) {
            report($e);

            return $this->fail('Pemindahan gagal. Silakan coba lagi.', 500);
        }
    }

    /**
     * @return array{kategori_aktif:int,kategori_nonaktif:int,barang_aktif:int,barang_nonaktif:int,nonaktif:int,total:int}
     */
    private function hitungPemakaianKelompok(int $id): array
    {
        $kategoriAktif = Kategori::where('id_kelompok', $id)->where('is_deleted', 0)->count();
        $kategoriNonaktif = Kategori::where('id_kelompok', $id)->where('is_deleted', 1)->count();
        $barangAktif = Barang::where('id_kelompok_kategori', $id)->where('is_delete', 0)->count();
        $barangNonaktif = Barang::where('id_kelompok_kategori', $id)->where('is_delete', 1)->count();

        return [
            'kategori_aktif' => $kategoriAktif,
            'kategori_nonaktif' => $kategoriNonaktif,
            'barang_aktif' => $barangAktif,
            'barang_nonaktif' => $barangNonaktif,
            'nonaktif' => $kategoriNonaktif + $barangNonaktif,
            'total' => $kategoriAktif + $kategoriNonaktif + $barangAktif + $barangNonaktif,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $query = Kategori::with('kelompok')->where('is_deleted', 0);
        if ($request->filled('id_kelompok')) {
            $query->where('id_kelompok', $request->integer('id_kelompok'));
        }
        if ($request->filled('search')) {
            $query->where('nama', 'like', '%'.$request->string('search')->toString().'%');
        }

        return $this->ok($query->orderBy('nama')->paginate(min($request->integer('per_page', 15), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_kelompok' => 'required|integer|exists:tb_kelompok_kategori,id_kelompok',
            'nama' => 'required|string|max:100',
        ]);

        try {
            $kategori = Kategori::create([
                ...$data, 'is_deleted' => 0, 'created_at' => now(), 'created_by' => 1,
                'updated_at' => now(), 'updated_by' => 1,
            ]);

            return $this->created($kategori->load('kelompok'), 'Kategori berhasil ditambahkan.');
        } catch (QueryException $e) {
            return $this->storeError($e);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $kategori = Kategori::where('is_deleted', 0)->find($id);
        if (! $kategori) {
            return $this->fail('Kategori tidak ditemukan.', 404);
        }

        $data = $request->validate([
            'id_kelompok' => 'sometimes|integer|exists:tb_kelompok_kategori,id_kelompok',
            'nama' => 'sometimes|string|max:100',
        ]);

        $kategori->update([...$data, 'updated_at' => now(), 'updated_by' => 1]);

        return $this->ok($kategori->fresh('kelompok'), 'Kategori berhasil diperbarui.');
    }

    public function destroy(int $id): JsonResponse
    {
        $kategori = Kategori::find($id);
        if (! $kategori) {
            return $this->fail('Kategori tidak ditemukan.', 404);
        }

        try {
            Schema::disableForeignKeyConstraints();
            // Barang yang pakai kategori ini: lepas referensi atau hapus sekalian
            // Lepas dulu agar FK tidak menghalangi hard delete
            DB::table('tb_barang')->where('id_kategori', $id)->update(['id_kategori' => null]);
            $kategori->delete();
            Schema::enableForeignKeyConstraints();

            return $this->ok(null, 'Kategori berhasil dihapus.');
        } catch (QueryException $e) {
            Schema::enableForeignKeyConstraints();

            return $this->storeError($e);
        }
    }
}
