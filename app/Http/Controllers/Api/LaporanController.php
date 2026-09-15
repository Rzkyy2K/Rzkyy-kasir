<?php

namespace App\Http\Controllers\Api;

use App\Models\Barang;
use App\Models\DetailPenjualan;
use App\Models\Pembelian;
use App\Models\Penjualan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanController extends BaseApiController
{
    private function scopeSekolah($query, Request $request, string $kolom = 'id_sekolah')
    {
        if ($request->filled('id_sekolah')) {
            $query->where($kolom, $request->integer('id_sekolah'));
        }

        return $query;
    }

    public function penjualan(Request $request): JsonResponse
    {
        $query = $this->scopeSekolah(Penjualan::with(['kasir', 'pelanggan', 'detail.barang'])->where('is_delete', 0), $request);
        if ($request->filled('dari')) {
            $query->where('tanggal_penjualan', '>=', $request->date('dari')->startOfDay());
        }
        if ($request->filled('sampai')) {
            $query->where('tanggal_penjualan', '<=', $request->date('sampai')->endOfDay());
        }
        if ($request->filled('id_user')) {
            $query->where('id_user', $request->integer('id_user'));
        }

        $rows = $query->orderByDesc('tanggal_penjualan')->paginate(min($request->integer('per_page', 20), 100));
        $ringkas = [
            'total_transaksi' => (clone $query)->count(),
            'total_omzet' => (float) (clone $query)->sum('total_faktur'),
        ];

        return $this->ok(['ringkasan' => $ringkas, 'data' => $rows]);
    }

    public function pembelian(Request $request): JsonResponse
    {
        $query = $this->scopeSekolah(Pembelian::with(['supplier', 'detail.barang'])->where('is_delete', 0), $request);
        if ($request->filled('dari')) {
            $query->where('tanggal_faktur', '>=', $request->date('dari')->startOfDay());
        }
        if ($request->filled('sampai')) {
            $query->where('tanggal_faktur', '<=', $request->date('sampai')->endOfDay());
        }

        $ringkas = [
            'total_transaksi' => (clone $query)->count(),
            'total_belanja' => (float) (clone $query)->sum('total_bayar'),
        ];

        return $this->ok(['ringkasan' => $ringkas, 'data' => $query->orderByDesc('tanggal_faktur')->paginate(min($request->integer('per_page', 20), 100))]);
    }

    public function stok(Request $request): JsonResponse
    {
        $query = $this->scopeSekolah(Barang::with(['kategori', 'supplier'])->where('is_delete', 0), $request);
        if ($request->filled('id_kategori')) {
            $query->where('id_kategori', $request->integer('id_kategori'));
        }

        // Bentuk respons disamakan dengan laporan lain: {ringkasan, data}.
        $ringkas = [
            'total_item' => (clone $query)->count(),
            'total_stok' => (int) (clone $query)->sum('stok'),
        ];

        return $this->ok(['ringkasan' => $ringkas, 'data' => $query->orderBy('stok')->paginate(min($request->integer('per_page', 20), 100))]);
    }

    public function terlaris(Request $request): JsonResponse
    {
        $query = DetailPenjualan::select('id_barang', DB::raw('SUM(jumlah_barang) as total_terjual'), DB::raw('SUM(subtotal) as total_omzet'))
            ->with('barang')
            ->groupBy('id_barang')
            ->orderByDesc('total_terjual')
            ->limit($request->integer('limit', 10));

        return $this->ok($query->get());
    }
}
