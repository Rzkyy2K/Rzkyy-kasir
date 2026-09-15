<?php

namespace App\Http\Controllers\Api;

use App\Models\Barang;
use App\Models\Pembelian;
use App\Models\Penjualan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
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

        $grafik = (clone $jual)
            ->select(DB::raw('DATE(tanggal_penjualan) as tanggal'), DB::raw('SUM(total_faktur) as total'), DB::raw('COUNT(*) as transaksi'))
            ->where('tanggal_penjualan', '>=', now()->subDays(6)->startOfDay())
            ->groupBy(DB::raw('DATE(tanggal_penjualan)'))
            ->orderBy('tanggal')
            ->get();

        return $this->ok([
            'total_produk' => (clone $barang)->where('is_active', 1)->count(),
            'total_stok' => (clone $barang)->sum('stok'),
            'penjualan_hari_ini' => (float) $hariIni->sum('total_faktur'),
            'transaksi_hari_ini' => $hariIni->count(),
            'total_transaksi' => (clone $jual)->count(),
            'total_pembelian_bulan_ini' => (float) (clone $beli)->whereMonth('tanggal_faktur', now()->month)->whereYear('tanggal_faktur', now()->year)->sum('total_bayar'),
            'produk_stok_rendah' => (clone $barang)->where('stok', '<=', 10)->orderBy('stok')->limit(5)->get(),
            'transaksi_terbaru' => (clone $jual)->with(['kasir', 'pelanggan'])->orderByDesc('tanggal_penjualan')->limit(8)->get(),
            'grafik_penjualan_7_hari' => $grafik,
        ]);
    }
}
