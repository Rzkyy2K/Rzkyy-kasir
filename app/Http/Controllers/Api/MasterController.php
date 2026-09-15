<?php

namespace App\Http\Controllers\Api;

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\KelompokKategori;
use App\Models\PosUser;
use App\Models\Role;
use App\Models\Sekolah;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class MasterController extends BaseApiController
{
    public function sekolah(Request $request): JsonResponse
    {
        $query = Sekolah::query();
        // Mode manajemen (developer): ?all=1 tampilkan juga yang nonaktif.
        // Dropdown/header konteks tetap memakai daftar aktif saja.
        if (! $request->boolean('all')) {
            $query->where('is_active', 1);
        }

        return $this->ok($query->orderBy('nama_sekolah')->get());
    }

    /**
     * Tambah sekolah baru (khusus developer — untuk sekolah lain yang
     * ingin memakai aplikasi kasir). Frontend membatasi akses lewat
     * peran developer; endpoint mengikuti pola API lain di file ini.
     */
    public function storeSekolah(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kode_sekolah' => 'required|string|max:20|unique:tb_sekolah,kode_sekolah',
            'nama_sekolah' => 'required|string|max:150',
            'alamat_sekolah' => 'sometimes|nullable|string',
            'alamat' => 'sometimes|nullable|string|max:255',
            'website' => 'sometimes|nullable|string|max:200',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $sekolah = Sekolah::create([
                ...$data,
                'is_active' => $data['is_active'] ?? true,
            ]);

            return $this->created($sekolah, 'Sekolah berhasil ditambahkan.');
        } catch (QueryException $e) {
            return $this->storeError($e);
        }
    }

    public function updateSekolah(Request $request, int $id): JsonResponse
    {
        $sekolah = Sekolah::find($id);
        if (! $sekolah) {
            return $this->fail('Sekolah tidak ditemukan.', 404);
        }

        $data = $request->validate([
            'kode_sekolah' => 'sometimes|string|max:20|unique:tb_sekolah,kode_sekolah,'.$id.',id_sekolah',
            'nama_sekolah' => 'sometimes|string|max:150',
            'alamat_sekolah' => 'sometimes|nullable|string',
            'alamat' => 'sometimes|nullable|string|max:255',
            'website' => 'sometimes|nullable|string|max:200',
            'is_active' => 'sometimes|boolean',
        ]);

        $sekolah->update($data);

        return $this->ok($sekolah->fresh(), 'Sekolah berhasil diperbarui.');
    }

    public function destroySekolah(int $id): JsonResponse
    {
        $sekolah = Sekolah::find($id);
        if (! $sekolah) {
            return $this->fail('Sekolah tidak ditemukan.', 404);
        }

        try {
            // Hapus permanen agar sekolah & kode_sekolah benar-bener hilang
            // dan kode bisa dipakai lagi. Bersihkan data terkait dulu
            // dengan FK dimatikan sementara agar tidak kena constraint.
            Schema::disableForeignKeyConstraints();
            // Detail transaksi dulu (FK ke penjualan/pembelian/barang)
            $penjualanIds = DB::table('tb_penjualan')->where('id_sekolah', $id)->pluck('id_penjualan');
            if ($penjualanIds->isNotEmpty()) {
                DB::table('tb_detail_penjualan')->whereIn('id_penjualan', $penjualanIds)->delete();
                DB::table('tb_penjualan')->where('id_sekolah', $id)->delete();
            }
            $pembelianIds = DB::table('tb_pembelian')->where('id_sekolah', $id)->pluck('id_pembelian');
            if ($pembelianIds->isNotEmpty()) {
                DB::table('tb_detail_pembelian')->whereIn('id_pembelian', $pembelianIds)->delete();
                DB::table('tb_pembelian')->where('id_sekolah', $id)->delete();
            }
            // Kategori via kelompok
            $kelompokIds = DB::table('tb_kelompok_kategori')->where('id_sekolah', $id)->pluck('id_kelompok');
            if ($kelompokIds->isNotEmpty()) {
                DB::table('tb_kategori')->whereIn('id_kelompok', $kelompokIds)->delete();
                DB::table('tb_barang')->where('id_sekolah', $id)->delete();
                DB::table('tb_kelompok_kategori')->where('id_sekolah', $id)->delete();
            } else {
                DB::table('tb_barang')->where('id_sekolah', $id)->delete();
            }
            DB::table('tb_supplier')->where('id_sekolah', $id)->delete();
            DB::table('tb_kelompok_pelanggan')->where('id_sekolah', $id)->delete();
            // Pelanggan yang kelompoknya milik sekolah ini (opsional, jaga-jaga)
            if ($kelompokIds->isNotEmpty()) {
                // tb_pelanggan tidak punya id_sekolah langsung, tapi kelompoknya punya
                $pelangganKelompokIds = DB::table('tb_kelompok_pelanggan')->where('id_sekolah', $id)->pluck('id_kelompok_pelanggan');
                // Sudah terhapus di atas, tapi jika ada pelanggan dengan kelompok tersebut, hapus
                if ($pelangganKelompokIds->isNotEmpty()) {
                    DB::table('tb_pelanggan')->whereIn('id_kelompok_pelanggan', $pelangganKelompokIds)->delete();
                }
            }
            DB::table('tb_user')->where('id_sekolah', $id)->delete();
            $sekolah->delete();
            Schema::enableForeignKeyConstraints();

            return $this->ok(null, 'Sekolah berhasil dihapus.');
        } catch (QueryException $e) {
            Schema::enableForeignKeyConstraints();

            return $this->storeError($e);
        }
    }

    /**
     * Seluruh data katalog dalam 1 request (ganti 3 request terpisah)
     * agar pindah halaman tidak berat.
     */
    public function katalog(Request $request): JsonResponse
    {
        $sekolahId = $request->integer('id_sekolah') ?: null;

        $kelompok = KelompokKategori::when($sekolahId, fn ($q) => $q->where('id_sekolah', $sekolahId))
            ->orderBy('nama_kelompok')->get();

        $kelompokIds = $kelompok->pluck('id_kelompok');
        $kategori = Kategori::where('is_deleted', 0)
            ->whereIn('id_kelompok', $kelompokIds)
            ->orderBy('nama')->get();

        $barang = Barang::with(['kategori', 'supplier'])
            ->where('is_delete', 0)
            ->when($sekolahId, fn ($q) => $q->where('id_sekolah', $sekolahId))
            ->orderBy('nama')->get();

        return $this->ok([
            'barang' => $barang,
            'kelompok' => $kelompok,
            'kategori' => $kategori,
        ]);
    }

    public function roles(): JsonResponse
    {
        return $this->ok(Role::orderBy('id_role')->get());
    }

    public function users(Request $request): JsonResponse
    {
        $query = PosUser::with(['sekolah', 'role']);
        if ($request->filled('id_sekolah')) {
            $query->where('id_sekolah', $request->integer('id_sekolah'));
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return $this->ok($query->orderBy('nama_lengkap')->paginate(min($request->integer('per_page', 15), 100)));
    }

    public function storeUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_sekolah' => 'required|integer|exists:tb_sekolah,id_sekolah',
            'id_role' => 'required|integer|exists:roles,id_role',
            'username' => 'required|string|max:50|unique:tb_user,username',
            'password' => 'required|string|min:6',
            'nama_lengkap' => 'required|string|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $user = PosUser::create([
                ...$data,
                'password' => Hash::make($data['password']),
                'is_active' => $data['is_active'] ?? true,
                'created_at' => now(),
                'created_by' => 1,
            ]);

            return $this->created($user->load(['sekolah', 'role']), 'User berhasil ditambahkan.');
        } catch (QueryException $e) {
            return $this->storeError($e);
        }
    }

    public function updateUser(Request $request, int $id): JsonResponse
    {
        $user = PosUser::find($id);
        if (! $user) {
            return $this->fail('User tidak ditemukan.', 404);
        }

        $data = $request->validate([
            'id_sekolah' => 'sometimes|integer|exists:tb_sekolah,id_sekolah',
            'id_role' => 'sometimes|integer|exists:roles,id_role',
            'nama_lengkap' => 'sometimes|string|max:100',
            'password' => 'sometimes|nullable|string|min:6',
            'is_active' => 'sometimes|boolean',
        ]);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update([...$data, 'updated_at' => now(), 'updated_by' => 1]);

        return $this->ok($user->fresh(['sekolah', 'role']), 'User berhasil diperbarui.');
    }

    public function destroyUser(int $id): JsonResponse
    {
        $user = PosUser::find($id);
        if (! $user) {
            return $this->fail('User tidak ditemukan.', 404);
        }

        try {
            Schema::disableForeignKeyConstraints();
            // Bersihkan referensi penjualan/pembelian biar username bisa dipakai lagi
            \Illuminate\Support\Facades\DB::table('tb_penjualan')->where('id_user', $id)->delete();
            \Illuminate\Support\Facades\DB::table('tb_pembelian')->where('id_user', $id)->delete();
            // Detail tidak perlu karena penjualan/pembelian sudah dihapus (FK disable, tapi jaga-jaga)
            $user->delete();
            Schema::enableForeignKeyConstraints();

            return $this->ok(null, 'User berhasil dihapus.');
        } catch (QueryException $e) {
            Schema::enableForeignKeyConstraints();

            return $this->storeError($e);
        }
    }
}
