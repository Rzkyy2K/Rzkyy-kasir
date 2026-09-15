<?php

namespace App\Http\Controllers\Api;

use App\Models\Barang;
use App\Models\DetailPembelian;
use App\Models\Pembelian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PembelianController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Pembelian::with(['sekolah', 'supplier'])
            ->where('is_delete', 0);

        if ($request->filled('id_sekolah')) {
            $query->where('id_sekolah', $request->integer('id_sekolah'));
        }
        if ($request->filled('id_supplier')) {
            $query->where('id_supplier', $request->integer('id_supplier'));
        }
        if ($request->filled('dari')) {
            $query->where('tanggal_faktur', '>=', $request->date('dari')->startOfDay());
        }
        if ($request->filled('sampai')) {
            $query->where('tanggal_faktur', '<=', $request->date('sampai')->endOfDay());
        }

        return $this->ok($query->orderByDesc('tanggal_faktur')->paginate(min($request->integer('per_page', 15), 100)));
    }

    public function show(int $id): JsonResponse
    {
        $pembelian = Pembelian::with(['sekolah', 'supplier', 'detail.barang'])
            ->where('is_delete', 0)->find($id);

        return $pembelian ? $this->ok($pembelian) : $this->fail('Pembelian tidak ditemukan.', 404);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_sekolah' => 'required|integer|exists:tb_sekolah,id_sekolah',
            'id_supplier' => 'required|integer|exists:tb_supplier,id_supplier',
            'id_user' => 'required|integer|exists:tb_user,id_user',
            'nomor_faktur' => 'nullable|string|max:50',
            'tanggal_faktur' => 'sometimes|date',
            'status_pembelian' => 'sometimes|in:draft,selesai',
            'jenis_transaksi' => 'sometimes|in:tunai,kredit',
            'cara_bayar' => 'sometimes|string|max:11',
            'note' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id_barang' => 'required|integer|exists:tb_barang,id_barang',
            'items.*.jumlah' => 'required|integer|min:1',
            'items.*.harga_beli' => 'required|numeric|min:0',
        ]);

        try {
            $pembelian = DB::transaction(function () use ($data) {
                $total = collect($data['items'])->sum(fn ($i) => $i['jumlah'] * $i['harga_beli']);

                $pembelian = Pembelian::create([
                    'id_sekolah' => $data['id_sekolah'],
                    'id_supplier' => $data['id_supplier'],
                    'id_user' => $data['id_user'],
                    'nomor_faktur' => $data['nomor_faktur'] ?? ('PB-'.now()->format('Ymd-His')),
                    'tanggal_faktur' => $data['tanggal_faktur'] ?? now(),
                    'total_bayar' => $total,
                    'status_pembelian' => $data['status_pembelian'] ?? 'selesai',
                    'jenis_transaksi' => $data['jenis_transaksi'] ?? 'tunai',
                    'cara_bayar' => $data['cara_bayar'] ?? 'tunai',
                    'note' => $data['note'] ?? null,
                    'created_at' => now(),
                    'created_by' => $data['id_user'],
                    'is_delete' => 0,
                ]);

                foreach ($data['items'] as $item) {
                    /** @var Barang $barang */
                    $barang = Barang::where('is_delete', 0)->lockForUpdate()->find($item['id_barang']);
                    if (! $barang) {
                        throw new \RuntimeException("Barang {$item['id_barang']} tidak tersedia.");
                    }

                    DetailPembelian::create([
                        'id_pembelian' => $pembelian->id_pembelian,
                        'id_barang' => $barang->id_barang,
                        'satuan' => $barang->satuan,
                        'jumlah' => $item['jumlah'],
                        'harga_beli' => $item['harga_beli'],
                        'subtotal' => $item['jumlah'] * $item['harga_beli'],
                    ]);

                    if (($data['status_pembelian'] ?? 'selesai') === 'selesai') {
                        $barang->increment('stok', $item['jumlah']);
                    }
                }

                return $pembelian;
            });

            return $this->created($pembelian->load(['detail.barang', 'supplier']), 'Pembelian berhasil disimpan. Stok diperbarui.');
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Pembelian gagal disimpan. Silakan coba lagi.', 500);
        }
    }
}
