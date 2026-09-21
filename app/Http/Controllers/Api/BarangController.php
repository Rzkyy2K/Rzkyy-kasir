<?php

namespace App\Http\Controllers\Api;

use App\Models\Barang;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

    public function prediksiStok(Request $request): JsonResponse
    {
        $days = max(1, min($request->integer('days', 7), 90));
        $idSekolah = $request->filled('id_sekolah') ? $request->integer('id_sekolah') : null;
        $statusFilter = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());

        $startDate = now()->subDays($days)->startOfDay();

        // 1. Agregasi data penjualan aktif (is_delete = 0) dalam N hari terakhir
        $salesQuery = DB::table('tb_detail_penjualan as dp')
            ->join('tb_penjualan as p', 'dp.id_penjualan', '=', 'p.id_penjualan')
            ->where('p.is_delete', 0)
            ->where('p.tanggal_penjualan', '>=', $startDate);

        if ($idSekolah) {
            $salesQuery->where('p.id_sekolah', $idSekolah);
        }

        $salesData = $salesQuery->select(
            'dp.id_barang',
            DB::raw('SUM(dp.jumlah_barang) as total_terjual'),
            DB::raw('SUM(dp.subtotal) as total_omset'),
            DB::raw('COUNT(DISTINCT p.id_penjualan) as frekuensi_transaksi')
        )->groupBy('dp.id_barang')->get()->keyBy('id_barang');

        // 2. Ambil master barang aktif
        $barangQuery = Barang::with(['kategori', 'kelompokKategori', 'supplier', 'sekolah'])
            ->where('is_delete', 0)
            ->where('is_active', 1);

        if ($idSekolah) {
            $barangQuery->where('id_sekolah', $idSekolah);
        }

        if ($search !== '') {
            $barangQuery->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        $allBarang = $barangQuery->get();

        $items = [];
        $totalKritis = 0;
        $totalWaspada = 0;
        $totalAman = 0;
        $estimasiAnggaranRestock = 0;

        foreach ($allBarang as $b) {
            $sale = $salesData->get($b->id_barang);
            $totalTerjual = $sale ? (int) $sale->total_terjual : 0;
            $totalOmset = $sale ? (float) $sale->total_omset : 0;
            $frekuensiTransaksi = $sale ? (int) $sale->frekuensi_transaksi : 0;

            // Laju penjualan harian (burn rate)
            $lajuHarian = round($totalTerjual / $days, 2);
            $stokSekarang = (int) $b->stok;

            // Estimasi sisa hari hingga stok habis
            if ($stokSekarang <= 0) {
                $estimasiHariHabis = 0;
            } elseif ($lajuHarian > 0) {
                $estimasiHariHabis = round($stokSekarang / $lajuHarian, 1);
            } else {
                $estimasiHariHabis = null;
            }

            // Klasifikasi urgensi
            if ($stokSekarang <= 0 || ($estimasiHariHabis !== null && $estimasiHariHabis <= 2)) {
                $status = 'kritis';
                $statusText = $stokSekarang <= 0 ? 'Stok Habis' : 'Kritis (≤ 2 Hari)';
                $totalKritis++;
            } elseif ($estimasiHariHabis !== null && $estimasiHariHabis <= 5) {
                $status = 'waspada';
                $statusText = 'Waspada (3–5 Hari)';
                $totalWaspada++;
            } else {
                $status = 'aman';
                $statusText = $estimasiHariHabis !== null ? 'Stok Aman (> 5 Hari)' : 'Stok Statis / Lambat';
                $totalAman++;
            }

            // Target penyangga stok 14 hari
            $bufferTarget = $lajuHarian > 0 ? (int) ceil($lajuHarian * 14) : 0;
            if ($status === 'kritis' || $status === 'waspada') {
                $rekomendasiRestock = max(0, $bufferTarget - $stokSekarang);
                if ($rekomendasiRestock <= 0 && $stokSekarang <= 0) {
                    $rekomendasiRestock = 10;
                }
            } else {
                $rekomendasiRestock = 0;
            }

            if ($rekomendasiRestock > 0) {
                $estimasiAnggaranRestock += ($rekomendasiRestock * (float) $b->harga_beli);
            }

            // Filter status jika ada
            if ($statusFilter && $statusFilter !== 'all' && $status !== $statusFilter) {
                continue;
            }

            $items[] = [
                'id_barang' => $b->id_barang,
                'nama' => $b->nama,
                'barcode' => $b->barcode,
                'satuan' => $b->satuan,
                'harga_beli' => (float) $b->harga_beli,
                'harga_jual' => (float) $b->harga_jual,
                'stok' => $stokSekarang,
                'total_terjual' => $totalTerjual,
                'total_omset' => $totalOmset,
                'frekuensi_transaksi' => $frekuensiTransaksi,
                'laju_harian' => $lajuHarian,
                'estimasi_hari_habis' => $estimasiHariHabis,
                'status' => $status,
                'status_text' => $statusText,
                'rekomendasi_restock' => $rekomendasiRestock,
                'kategori' => $b->kategori,
                'supplier' => $b->supplier,
                'sekolah' => $b->sekolah,
            ];
        }

        // Urutkan: Kritis -> Waspada -> Aman, lalu estimasi hari habis terendah
        usort($items, function ($a, $b) {
            $order = ['kritis' => 1, 'waspada' => 2, 'aman' => 3];
            $rankA = $order[$a['status']] ?? 4;
            $rankB = $order[$b['status']] ?? 4;

            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }

            $estA = $a['estimasi_hari_habis'] ?? 9999;
            $estB = $b['estimasi_hari_habis'] ?? 9999;
            if ($estA !== $estB) {
                return $estA <=> $estB;
            }

            return $b['total_terjual'] <=> $a['total_terjual'];
        });

        return $this->ok([
            'days' => $days,
            'summary' => [
                'total_produk' => count($allBarang),
                'total_kritis' => $totalKritis,
                'total_waspada' => $totalWaspada,
                'total_aman' => $totalAman,
                'estimasi_anggaran_restock' => $estimasiAnggaranRestock,
            ],
            'items' => $items,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $barang = Barang::with(['kategori', 'kelompokKategori', 'supplier', 'sekolah'])
            ->where('is_delete', 0)->find($id);

        return $barang ? $this->ok($barang) : $this->fail('Barang tidak ditemukan.', 404);
    }

    public function byBarcode(Request $request, string $barcode): JsonResponse
    {
        $query = Barang::with(['kategori', 'kelompokKategori', 'supplier', 'sekolah'])
            ->where('is_delete', 0)
            ->where('barcode', $barcode);

        if ($request->filled('id_sekolah')) {
            $query->where('id_sekolah', $request->integer('id_sekolah'));
        }

        $barang = $query->first();

        return $barang ? $this->ok($barang) : $this->fail('Barang dengan barcode ini tidak ditemukan.', 404);
    }

    public function lookupBarcode(Request $request, string $barcode): JsonResponse
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return $this->fail('Barcode kosong.', 422);
        }

        // 1a. Cek di database sekolah ini terlebih dahulu
        $localQuery = Barang::with(['kategori', 'kelompokKategori', 'supplier'])
            ->where('is_delete', 0)
            ->where('barcode', $barcode);

        if ($request->filled('id_sekolah')) {
            $localQuery->where('id_sekolah', $request->integer('id_sekolah'));
        }

        $localBarang = $localQuery->first();
        if ($localBarang) {
            return $this->ok([
                'found' => true,
                'source' => 'local',
                'nama' => $localBarang->nama,
                'barcode' => $localBarang->barcode,
                'harga_beli' => $localBarang->harga_beli,
                'harga_jual' => $localBarang->harga_jual,
                'satuan' => $localBarang->satuan,
                'id_kategori' => $localBarang->id_kategori,
                'id_kelompok_kategori' => $localBarang->id_kelompok_kategori,
                'id_supplier' => $localBarang->id_supplier,
                'stok' => $localBarang->stok,
                'barang' => $localBarang,
            ], 'Barang ditemukan di database sekolah.');
        }

        // 1b. Cek di database sekolah lain (katalog bersama EduMart)
        $globalBarang = Barang::where('is_delete', 0)
            ->where('barcode', $barcode)
            ->whereNotNull('nama')
            ->where('nama', '!=', '')
            ->first();

        if ($globalBarang) {
            return $this->ok([
                'found' => true,
                'source' => 'edumart_catalog',
                'nama' => $globalBarang->nama,
                'barcode' => $globalBarang->barcode,
                'harga_beli' => $globalBarang->harga_beli,
                'harga_jual' => $globalBarang->harga_jual,
                'satuan' => $globalBarang->satuan,
            ], 'Barang ditemukan dari katalog bersama EduMart.');
        }

        // 1c. Cek di Database/Katalog Produk Sekolah & ATK Indonesia (Joyko, Standard, Faber-Castell, SiDu, dll)
        $katalogProdukUmum = [
            '8993988283294' => ['nama' => 'Pulpen Joyko Q Gel 0.5mm GP-265', 'satuan' => 'pcs'],
            '8993988283287' => ['nama' => 'Pulpen Joyko Q Gel 0.5mm Hitam', 'satuan' => 'pcs'],
            '8993988280019' => ['nama' => 'Pulpen Joyko Gel Pen JK-100 0.5mm', 'satuan' => 'pcs'],
            '8993988010012' => ['nama' => 'Penghapus Joyko B40', 'satuan' => 'pcs'],
            '8993988020011' => ['nama' => 'Rautan Pensil Joyko B-16', 'satuan' => 'pcs'],
            '8993988241010' => ['nama' => 'Pensil Joyko 2B P-88', 'satuan' => 'pcs'],
            '8992775010014' => ['nama' => 'Pulpen Standard AE7 0.5mm', 'satuan' => 'pcs'],
            '8992775010021' => ['nama' => 'Pulpen Standard B-Grip 0.5mm', 'satuan' => 'pcs'],
            '8993077110019' => ['nama' => 'Pensil Faber-Castell 2B', 'satuan' => 'pcs'],
            '8993077220015' => ['nama' => 'Penghapus Faber-Castell Hitam EB-20', 'satuan' => 'pcs'],
            '8992741910010' => ['nama' => 'Buku Tulis Sinar Dunia (SiDu) 38 Lembar', 'satuan' => 'pcs'],
            '8992741910027' => ['nama' => 'Buku Tulis Sinar Dunia (SiDu) 58 Lembar', 'satuan' => 'pcs'],
            '8992745110010' => ['nama' => 'Tipe-X Kertas Joyko CT-522', 'satuan' => 'pcs'],
            '8992745110027' => ['nama' => 'Tipe-X Cair Kenko KE-01', 'satuan' => 'pcs'],
            '8992745220016' => ['nama' => 'Spidol Snowman Boardmarker Hitam BG-12', 'satuan' => 'pcs'],
            '8992745220023' => ['nama' => 'Spidol Snowman Permanent Hitam G-12', 'satuan' => 'pcs'],
            '8992745330012' => ['nama' => 'Penggaris Plastik Butterfly 30cm', 'satuan' => 'pcs'],
            '8992745440018' => ['nama' => 'Gunting Joyko SC-838', 'satuan' => 'pcs'],
            '8992745550014' => ['nama' => 'Lem Kertas Glukol', 'satuan' => 'pcs'],
            '8992745660010' => ['nama' => 'Stapler Joyko HD-10', 'satuan' => 'pcs'],
            '8992745660027' => ['nama' => 'Isi Staples Joyko No. 10', 'satuan' => 'pack'],
            '8993175535878' => ['nama' => 'Nabati Wafer Richeese 37g', 'satuan' => 'pcs'],
            '8993175535885' => ['nama' => 'Nabati Wafer Richoco 37g', 'satuan' => 'pcs'],
            '8992753111104' => ['nama' => 'Teh Botol Sosro 330ml', 'satuan' => 'botol'],
            '8998866200222' => ['nama' => 'Indomie Mi Goreng 85g', 'satuan' => 'bungkus'],
            '8998866200017' => ['nama' => 'Indomie Kuah Rasa Ayam Bawang 69g', 'satuan' => 'bungkus'],
            '8998866200048' => ['nama' => 'Indomie Kuah Rasa Soto Mie 70g', 'satuan' => 'bungkus'],
            '8991389220010' => ['nama' => 'Air Mineral Aqua 600ml', 'satuan' => 'botol'],
            '8996001414002' => ['nama' => 'Air Mineral Le Minerale 600ml', 'satuan' => 'botol'],
            '8991002101235' => ['nama' => 'Chitato Sapi Panggang 68g', 'satuan' => 'bungkus'],
            '8996006858030' => ['nama' => 'Teh Botol Sosro Less Sugar 450ml', 'satuan' => 'botol'],
            '8991001100017' => ['nama' => 'Teh Pucuk Harum 350ml', 'satuan' => 'botol'],
            '8992771000019' => ['nama' => 'Ultra Milk Cokelat 200ml', 'satuan' => 'kotak'],
            '8992771000026' => ['nama' => 'Ultra Milk Full Cream 200ml', 'satuan' => 'kotak'],
            '8992761121010' => ['nama' => 'Beng Beng Cokelat Wafer 25g', 'satuan' => 'pcs'],
            '8992761136014' => ['nama' => 'Choki Choki Cokelat Pasta', 'satuan' => 'pcs'],
            '8991001410017' => ['nama' => 'Kopiko Permen Kopi', 'satuan' => 'bungkus'],
            '8996001301012' => ['nama' => 'Pocari Sweat 350ml', 'satuan' => 'botol'],
            '8992753221018' => ['nama' => 'Fruit Tea Sosro Apel 500ml', 'satuan' => 'botol'],
            '8992753222015' => ['nama' => 'Fruit Tea Sosro Blackcurrant 500ml', 'satuan' => 'botol'],
        ];

        if (isset($katalogProdukUmum[$barcode])) {
            $item = $katalogProdukUmum[$barcode];
            return $this->ok([
                'found' => true,
                'source' => 'katalog_nasional',
                'nama' => $item['nama'],
                'barcode' => $barcode,
                'satuan' => $item['satuan'] ?? 'pcs',
            ], 'Informasi produk ditemukan dari katalog produk nasional.');
        }

        // 2. Coba cari di Open Food Facts (database publik produk makanan/minuman)
        try {
            $response = Http::timeout(4)
                ->withHeaders(['User-Agent' => 'EduMartPOS/1.0'])
                ->get("https://world.openfoodfacts.org/api/v0/product/{$barcode}.json");

            if ($response->successful()) {
                $data = $response->json();
                if (($data['status'] ?? 0) === 1 && !empty($data['product'])) {
                    $prod = $data['product'];
                    $namaCandidates = [
                        $prod['product_name_id'] ?? null,
                        $prod['product_name'] ?? null,
                        $prod['product_name_en'] ?? null,
                        $prod['generic_name_id'] ?? null,
                        $prod['generic_name'] ?? null,
                        $prod['generic_name_en'] ?? null,
                    ];

                    $nama = null;
                    foreach ($namaCandidates as $candidate) {
                        if (!empty($candidate) && is_string($candidate) && trim($candidate) !== '') {
                            $nama = trim($candidate);
                            break;
                        }
                    }

                    $brand = !empty($prod['brands']) ? trim($prod['brands']) : null;
                    $fullName = $nama ? ($brand && !str_contains(strtolower($nama), strtolower($brand)) ? "{$brand} {$nama}" : $nama) : null;

                    if ($fullName) {
                        return $this->ok([
                            'found' => true,
                            'source' => 'openfoodfacts',
                            'nama' => trim($fullName),
                            'barcode' => $barcode,
                            'brand' => $brand,
                        ], 'Informasi produk ditemukan dari database kemasan publik.');
                    }
                }
            }
        } catch (\Throwable $e) {
            // Abaikan error koneksi publik
        }

        // 3. Coba cari di UPCitemdb
        try {
            $upcResponse = Http::timeout(4)
                ->get("https://api.upcitemdb.com/prod/trial/lookup?upc={$barcode}");

            if ($upcResponse->successful()) {
                $upcData = $upcResponse->json();
                if (!empty($upcData['items'][0]['title'])) {
                    $title = trim($upcData['items'][0]['title']);
                    return $this->ok([
                        'found' => true,
                        'source' => 'upcitemdb',
                        'nama' => $title,
                        'barcode' => $barcode,
                    ], 'Informasi produk ditemukan dari database UPC.');
                }
            }
        } catch (\Throwable $e) {
            // Abaikan
        }

        return $this->ok([
            'found' => false,
            'source' => null,
            'barcode' => $barcode,
        ], 'Barcode belum terdaftar di sistem.');
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
