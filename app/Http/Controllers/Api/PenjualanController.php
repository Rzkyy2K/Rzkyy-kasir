<?php

namespace App\Http\Controllers\Api;

use App\Models\Barang;
use App\Models\DetailPenjualan;
use App\Models\Penjualan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PenjualanController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Penjualan::with(['sekolah', 'kasir', 'pelanggan'])
            ->where('is_delete', 0);

        if ($request->filled('id_sekolah')) {
            $query->where('id_sekolah', $request->integer('id_sekolah'));
        }
        if ($request->filled('dari')) {
            $query->where('tanggal_penjualan', '>=', $request->date('dari')->startOfDay());
        }
        if ($request->filled('sampai')) {
            $query->where('tanggal_penjualan', '<=', $request->date('sampai')->endOfDay());
        }
        if ($request->filled('id_user')) {
            $query->where('id_user', $request->integer('id_user'));
        }

        return $this->ok($query->orderByDesc('tanggal_penjualan')->paginate(min($request->integer('per_page', 15), 100)));
    }

    public function show(int $id): JsonResponse
    {
        $penjualan = Penjualan::with(['sekolah', 'kasir', 'pelanggan', 'detail.barang'])
            ->where('is_delete', 0)->find($id);

        return $penjualan ? $this->ok($penjualan) : $this->fail('Transaksi tidak ditemukan.', 404);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_sekolah' => 'required|integer|exists:tb_sekolah,id_sekolah',
            'id_user' => 'required|integer|exists:tb_user,id_user',
            'id_pelanggan' => 'nullable|integer|exists:tb_pelanggan,id_pelanggan',
            'total_bayar' => 'required|numeric|min:0',
            'jenis_transaksi' => 'sometimes|in:tunai,kredit',
            'cara_bayar' => 'sometimes|string|max:50',
            'note' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id_barang' => 'required|integer|exists:tb_barang,id_barang',
            'items.*.jumlah_barang' => 'required|integer|min:1',
            'items.*.diskon_tipe' => 'sometimes|nullable|numeric',
            'items.*.diskon_nilai' => 'sometimes|nullable|numeric|min:0',
            'items.*.diskon_nominal' => 'sometimes|nullable|numeric|min:0',
        ]);

        try {
            $penjualan = DB::transaction(function () use ($data) {
                $totalFaktur = 0;
                $rows = [];

                foreach ($data['items'] as $item) {
                    /** @var Barang $barang */
                    $barang = Barang::where('is_delete', 0)->lockForUpdate()->find($item['id_barang']);
                    if (! $barang || ! $barang->is_active) {
                        throw new \RuntimeException("Barang {$item['id_barang']} tidak tersedia.");
                    }
                    if ($barang->stok < $item['jumlah_barang']) {
                        throw new \RuntimeException("Stok {$barang->nama} tidak mencukupi (sisa {$barang->stok}).");
                    }

                    $diskonNominal = (float) ($item['diskon_nominal'] ?? 0);
                    $diskonTipe = (float) ($item['diskon_tipe'] ?? 0);
                    $diskonNilai = (float) ($item['diskon_nilai'] ?? 0);
                    $subtotal = ($barang->harga_jual * $item['jumlah_barang']) - $diskonNominal;
                    $totalFaktur += $subtotal;

                    $rows[] = [
                        'barang' => $barang,
                        'jumlah_barang' => $item['jumlah_barang'],
                        'harga_beli' => $barang->harga_beli,
                        'harga_jual' => $barang->harga_jual,
                        'diskon_tipe' => $diskonTipe,
                        'diskon_nilai' => $diskonNilai,
                        'diskon_nominal' => $diskonNominal,
                        'subtotal' => $subtotal,
                    ];
                }

                $kembalian = max(0, (float) $data['total_bayar'] - $totalFaktur);

                $penjualan = Penjualan::create([
                    'id_sekolah' => $data['id_sekolah'],
                    'id_user' => $data['id_user'],
                    'id_pelanggan' => $data['id_pelanggan'] ?? null,
                    'tanggal_penjualan' => now(),
                    'total_faktur' => $totalFaktur,
                    'total_bayar' => $data['total_bayar'],
                    'kembalian' => $kembalian,
                    'status_pembayaran' => ((float) $data['total_bayar'] >= $totalFaktur) ? 'sudah bayar' : 'belum bayar',
                    'jenis_transaksi' => $data['jenis_transaksi'] ?? 'tunai',
                    'cara_bayar' => $data['cara_bayar'] ?? 'tunai',
                    'note' => $data['note'] ?? null,
                    'created_at' => now(),
                    'created_by' => $data['id_user'],
                    'is_delete' => 0,
                ]);

                foreach ($rows as $row) {
                    DetailPenjualan::create([
                        'id_penjualan' => $penjualan->id_penjualan,
                        'id_barang' => $row['barang']->id_barang,
                        'jumlah_barang' => $row['jumlah_barang'],
                        'harga_beli' => $row['harga_beli'],
                        'harga_jual' => $row['harga_jual'],
                        'diskon_tipe' => $row['diskon_tipe'],
                        'diskon_nilai' => $row['diskon_nilai'],
                        'diskon_nominal' => $row['diskon_nominal'],
                        'subtotal' => $row['subtotal'],
                    ]);
                    $row['barang']->decrement('stok', $row['jumlah_barang']);
                }

                return $penjualan;
            });

            return $this->created(
                $penjualan->load(['detail.barang', 'pelanggan']),
                'Transaksi berhasil disimpan.'
            );
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Transaksi gagal disimpan. Silakan coba lagi.', 500);
        }
    }
}
