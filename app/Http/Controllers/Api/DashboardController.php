<?php

namespace App\Http\Controllers\Api;

use App\Models\Barang;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\PosUser;
use App\Models\Sekolah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isDeveloper = ($user && $user->role && strtolower($user->role->nama_role) === 'developer')
            || $request->boolean('is_dev')
            || $request->boolean('is_developer');

        // Mode Khusus Developer: Ringkasan Sistem & Multi-Sekolah (Privasi Transaksi Sekolah Terjaga)
        if ($isDeveloper) {
            $totalSekolah = Sekolah::count();
            $totalUserActive = PosUser::where('is_active', 1)->count();
            $totalUserAll = PosUser::count();

            // Sekolah konteks aktif yang dipilih developer
            $sekolahId = $request->integer('id_sekolah');
            if (! $sekolahId) {
                $sekolahId = Sekolah::where('is_active', 1)->value('id_sekolah') ?? 1;
            }

            $currentSekolah = Sekolah::find($sekolahId);

            // Ringkasan akun AKTIF per role KHUSUS sekolah yang dipilih (bukan akumulasi semua sekolah)
            $roleCounts = PosUser::join('roles', 'tb_user.id_role', '=', 'roles.id_role')
                ->where('tb_user.id_sekolah', $sekolahId)
                ->where('tb_user.is_active', 1)
                ->select('roles.nama_role', DB::raw('count(*) as total'))
                ->groupBy('roles.nama_role')
                ->get()
                ->pluck('total', 'nama_role');

            $totalUserSekolahAktif = PosUser::where('id_sekolah', $sekolahId)
                ->where('is_active', 1)
                ->count();

            // Daftar akun pengguna AKTIF sekolah yang dipilih (sesuai halaman Manajemen User)
            $schoolUsers = PosUser::with(['role', 'sekolah'])
                ->where('id_sekolah', $sekolahId)
                ->where('is_active', 1)
                ->orderBy('nama_lengkap')
                ->get()
                ->map(function ($u) {
                    return [
                        'id_user' => $u->id_user,
                        'nama_lengkap' => $u->nama_lengkap,
                        'username' => $u->username,
                        'nama_role' => $u->role ? $u->role->nama_role : '—',
                        'nama_sekolah' => $u->sekolah ? $u->sekolah->nama_sekolah : '—',
                        'is_active' => (bool) $u->is_active,
                    ];
                });

            // Ringkasan sekolah terdaftar beserta total staf/pengguna aktif dan rincian perannya
            $sekolahList = Sekolah::select('id_sekolah', 'kode_sekolah', 'nama_sekolah', 'alamat_sekolah', 'website', 'is_active')
                ->withCount(['users as total_user' => function ($q) {
                    $q->where('is_active', 1);
                }])
                ->get()
                ->map(function ($s) {
                    $rolesBreakdown = PosUser::join('roles', 'tb_user.id_role', '=', 'roles.id_role')
                        ->where('tb_user.id_sekolah', $s->id_sekolah)
                        ->where('tb_user.is_active', 1)
                        ->select('roles.nama_role', DB::raw('count(*) as total'))
                        ->groupBy('roles.nama_role')
                        ->pluck('total', 'nama_role');

                    $s->role_counts = $rolesBreakdown;

                    return $s;
                });

            $totalProdukGlobal = Barang::where('is_delete', 0)->where('is_active', 1)->count();
            $totalTransaksiGlobal = Penjualan::where('is_delete', 0)->count();

            return $this->ok([
                'is_developer' => true,
                'current_sekolah' => $currentSekolah ? [
                    'id_sekolah' => $currentSekolah->id_sekolah,
                    'nama_sekolah' => $currentSekolah->nama_sekolah,
                    'kode_sekolah' => $currentSekolah->kode_sekolah,
                ] : null,
                'total_sekolah' => $totalSekolah,
                'total_user_aktif' => $totalUserActive,
                'total_user_semua' => $totalUserAll,
                'total_user_sekolah_aktif' => $totalUserSekolahAktif,
                'role_counts' => $roleCounts,
                'recent_users' => $schoolUsers,
                'sekolah_list' => $sekolahList,
                'total_produk_global' => $totalProdukGlobal,
                'total_transaksi_global' => $totalTransaksiGlobal,
                'system_info' => [
                    'app_name' => config('app.name', 'EduMart POS'),
                    'app_env' => config('app.env', 'production'),
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                    'database' => config('database.default'),
                    'timezone' => config('app.timezone', 'Asia/Jakarta'),
                ],
            ]);
        }

        // Mode Standar Sekolah (Super Admin, Admin, Kasir)
        $sekolahId = $request->integer('id_sekolah') ?: null;
        $barang = Barang::where('is_delete', 0);
        $jual = Penjualan::where('is_delete', 0);
        $beli = Pembelian::where('is_delete', 0);

        if ($sekolahId) {
            $barang->where('id_sekolah', $sekolahId);
            $jual->where('id_sekolah', $sekolahId);
            $beli->where('id_sekolah', $sekolahId);
        }

        $hariIni = $jual->clone()->whereDate('tanggal_penjualan', today());

        $rawGrafik = (clone $jual)
            ->select(DB::raw('DATE(tanggal_penjualan) as tanggal'), DB::raw('SUM(total_faktur) as total'), DB::raw('COUNT(*) as transaksi'))
            ->where('tanggal_penjualan', '>=', now()->subDays(6)->startOfDay())
            ->groupBy(DB::raw('DATE(tanggal_penjualan)'))
            ->get()
            ->keyBy(function ($item) {
                return date('Y-m-d', strtotime($item->tanggal));
            });

        $grafik7Hari = [];
        for ($i = 6; $i >= 0; $i--) {
            $tgl = now()->subDays($i)->format('Y-m-d');
            $row = $rawGrafik->get($tgl);
            $grafik7Hari[] = [
                'tanggal' => $tgl,
                'total' => $row ? (float) $row->total : 0,
                'transaksi' => $row ? (int) $row->transaksi : 0,
            ];
        }

        return $this->ok([
            'is_developer' => false,
            'total_produk' => (clone $barang)->where('is_active', 1)->count(),
            'total_stok' => (clone $barang)->sum('stok'),
            'penjualan_hari_ini' => (float) $hariIni->sum('total_faktur'),
            'transaksi_hari_ini' => $hariIni->count(),
            'total_transaksi' => (clone $jual)->count(),
            'total_pembelian_bulan_ini' => (float) (clone $beli)->whereMonth('tanggal_faktur', now()->month)->whereYear('tanggal_faktur', now()->year)->sum('total_bayar'),
            'produk_stok_rendah' => (clone $barang)->where('stok', '<=', 10)->orderBy('stok')->limit(5)->get(),
            'transaksi_terbaru' => (clone $jual)->with(['kasir', 'pelanggan'])->orderByDesc('tanggal_penjualan')->limit(8)->get(),
            'grafik_penjualan_7_hari' => $grafik7Hari,
        ]);
    }
}
