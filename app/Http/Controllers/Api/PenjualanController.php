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
        $query = Penjualan::with(['sekolah', 'kasir', 'pelanggan', 'voidRequester', 'voidApprover']);

        if ($request->filled('status_void')) {
            $query->where('status_void', $request->string('status_void')->toString());
        } else {
            $query->where('is_delete', 0);
        }

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
        $penjualan = Penjualan::with([
            'sekolah',
            'kasir',
            'pelanggan',
            'detail.barang',
            'voidRequester',
            'adminVerifier',
            'voidApprover',
        ])->find($id);

        return $penjualan ? $this->ok($penjualan) : $this->fail('Transaksi tidak ditemukan.', 404);
    }

    public function pendingVoidRequests(Request $request): JsonResponse
    {
        $query = Penjualan::with([
            'sekolah',
            'kasir',
            'pelanggan',
            'voidRequester',
            'adminVerifier',
            'voidApprover',
            'detail.barang',
        ]);

        if ($request->filled('tier')) {
            $tier = $request->string('tier')->toString();
            if ($tier === 'admin') {
                $query->whereIn('status_void', ['pending_admin', 'pending']);
            } elseif ($tier === 'super_admin') {
                $query->where('status_void', 'pending_super_admin');
            }
        } else {
            $query->whereIn('status_void', ['pending_admin', 'pending_super_admin', 'pending']);
        }

        if ($request->filled('id_sekolah')) {
            $query->where('id_sekolah', $request->integer('id_sekolah'));
        }

        return $this->ok($query->orderByDesc('void_requested_at')->get());
    }

    public function requestVoid(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'id_user' => 'required|integer|exists:tb_user,id_user',
            'alasan' => 'required|string|max:500',
            'telepon_kasir' => 'nullable|string|max:30',
        ]);

        $penjualan = Penjualan::find($id);
        if (! $penjualan) {
            return $this->fail('Transaksi tidak ditemukan.', 404);
        }

        if ($penjualan->status_void === 'approved' || ($penjualan->is_delete && ! in_array($penjualan->status_void, ['pending', 'pending_admin', 'pending_super_admin']))) {
            return $this->fail('Transaksi sudah dibatalkan sebelumnya.', 422);
        }

        if (in_array($penjualan->status_void, ['pending_admin', 'pending_super_admin', 'pending'])) {
            return $this->fail('Transaksi ini sedang dalam proses antrean persetujuan pembatalan.', 422);
        }

        $penjualan->update([
            'status_void' => 'pending_admin',
            'alasan_void' => $data['alasan'],
            'void_telepon_kasir' => $data['telepon_kasir'] ?? null,
            'void_requested_by' => $data['id_user'],
            'void_requested_at' => now(),
            'void_admin_verified_by' => null,
            'void_admin_verified_at' => null,
            'void_admin_notes' => null,
            'void_reject_reason' => null,
        ]);

        return $this->ok(
            $penjualan->load(['kasir', 'pelanggan', 'voidRequester']),
            'Permintaan pembatalan transaksi berhasil diajukan. Menunggu cross-check Admin via WhatsApp.'
        );
    }

    public function forwardVoid(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'id_user' => 'required|integer|exists:tb_user,id_user',
            'catatan_admin' => 'required|string|max:500',
        ]);

        $penjualan = Penjualan::find($id);
        if (! $penjualan) {
            return $this->fail('Transaksi tidak ditemukan.', 404);
        }

        if (! in_array($penjualan->status_void, ['pending_admin', 'pending'])) {
            return $this->fail('Hanya transaksi menunggu verifikasi Admin yang dapat diteruskan ke Super Admin.', 422);
        }

        $penjualan->update([
            'status_void' => 'pending_super_admin',
            'void_admin_verified_by' => $data['id_user'],
            'void_admin_verified_at' => now(),
            'void_admin_notes' => $data['catatan_admin'],
        ]);

        return $this->ok(
            $penjualan->fresh()->load(['kasir', 'pelanggan', 'voidRequester', 'adminVerifier']),
            'Hasil cross-check berhasil dicatat. Pengajuan diteruskan ke Super Admin untuk persetujuan akhir.'
        );
    }

    public function approveVoid(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'id_user' => 'required|integer|exists:tb_user,id_user',
        ]);

        $penjualan = Penjualan::with('detail.barang')->find($id);
        if (! $penjualan) {
            return $this->fail('Transaksi tidak ditemukan.', 404);
        }

        if ($penjualan->status_void === 'approved') {
            return $this->fail('Transaksi sudah disetujui dibatalkan sebelumnya.', 422);
        }

        try {
            DB::transaction(function () use ($penjualan, $data) {
                // 1. Kembalikan stok barang yang terjual
                foreach ($penjualan->detail as $item) {
                    if ($item->barang) {
                        $item->barang->increment('stok', $item->jumlah_barang);
                    }
                }

                // 2. Perbarui status transaksi menjadi dibatalkan / void
                $penjualan->update([
                    'status_void' => 'approved',
                    'status_pembayaran' => 'dibatalkan',
                    'is_delete' => 1,
                    'deleted_at' => now(),
                    'deleted_by' => $data['id_user'],
                    'void_approved_by' => $data['id_user'],
                    'void_approved_at' => now(),
                ]);
            });

            return $this->ok(
                $penjualan->fresh()->load(['kasir', 'pelanggan', 'voidRequester', 'adminVerifier', 'voidApprover']),
                'Pembatalan transaksi disetujui secara final oleh Super Admin. Stok barang berhasil dikembalikan.'
            );
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Gagal memproses persetujuan void: ' . $e->getMessage(), 500);
        }
    }

    public function rejectVoid(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'id_user' => 'required|integer|exists:tb_user,id_user',
            'alasan_penolakan' => 'nullable|string|max:500',
        ]);

        $penjualan = Penjualan::find($id);
        if (! $penjualan) {
            return $this->fail('Transaksi tidak ditemukan.', 404);
        }

        if (! in_array($penjualan->status_void, ['pending_admin', 'pending_super_admin', 'pending'])) {
            return $this->fail('Hanya transaksi dalam antrean pending yang dapat ditolak.', 422);
        }

        $penjualan->update([
            'status_void' => 'rejected',
            'void_reject_reason' => $data['alasan_penolakan'] ?? 'Permintaan dibatalkan/ditolak.',
            'void_approved_by' => $data['id_user'],
            'void_approved_at' => now(),
        ]);

        return $this->ok(
            $penjualan->fresh()->load(['kasir', 'pelanggan', 'voidRequester', 'adminVerifier', 'voidApprover']),
            'Permintaan pembatalan transaksi telah ditolak. Transaksi tetap aktif.'
        );
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
