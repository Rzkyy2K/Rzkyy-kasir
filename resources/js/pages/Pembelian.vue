<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus, Trash2 , Truck } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import EmptyState from '@/components/pos/EmptyState.vue';
import Modal from '@/components/pos/Modal.vue';
import PageHeader from '@/components/pos/PageHeader.vue';
import Pagination from '@/components/pos/Pagination.vue';
import PosLayout from '@/layouts/PosLayout.vue';
import { rupiah, tanggal } from '@/lib/format';
import { friendlyError } from '@/services/api';
import { fetchBarang } from '@/services/barangService';
import { createPembelian, fetchPembelian } from '@/services/pembelianService';
import { fetchSupplier } from '@/services/supplierService';
import { usePosStore } from '@/stores/pos';
import type { Barang, Supplier } from '@/types/pos';

const pos = usePosStore();
const loading = ref(true);
const riwayat = ref<any[]>([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);

const showForm = ref(false);
const saving = ref(false);
const supplierList = ref<Supplier[]>([]);
const barangList = ref<Barang[]>([]);
const idSupplier = ref(0);
const jenis = ref('tunai');
const cara = ref('tunai');
const note = ref('');
const baris = ref<{ id_barang: number; jumlah: number; harga_beli: number }[]>(
    [],
);

const totalBelanja = computed(() =>
    baris.value.reduce((n, b) => n + b.jumlah * b.harga_beli, 0),
);

async function load() {
    loading.value = true;
    try {
        const res: any = await fetchPembelian({
            id_sekolah: pos.idSekolah,
            per_page: 10,
            page: page.value,
        });
        riwayat.value = res.data;
        page.value = res.current_page;
        lastPage.value = res.last_page;
        total.value = res.total;
    } catch (e) {
        toast.error(friendlyError(e, 'Riwayat pembelian gagal dimuat.'));
    } finally {
        loading.value = false;
    }
}

function tambahBaris() {
    baris.value.push({
        id_barang: barangList.value[0]?.id_barang ?? 0,
        jumlah: 1,
        harga_beli: 0,
    });
}

async function simpan() {
    if (!idSupplier.value) {
        toast.error('Silakan pilih mitra pemasok (supplier).');
        return;
    }
    if (baris.value.length === 0) {
        toast.error('Tambahkan minimal satu produk dalam daftar pembelian.');
        return;
    }
    if (!pos.idUser) {
        toast.error('Silakan pilih profil pengguna aktif di header terlebih dahulu.');
        return;
    }
    saving.value = true;
    try {
        const res: any = await createPembelian({
            id_sekolah: pos.idSekolah,
            id_supplier: idSupplier.value,
            id_user: pos.idUser,
            status_pembelian: 'selesai',
            jenis_transaksi: jenis.value,
            cara_bayar: cara.value,
            note: note.value || undefined,
            items: baris.value,
        });
        toast.success(
            res.message || 'Transaksi pembelian berhasil disimpan. Stok produk telah diperbarui.',
        );
        showForm.value = false;
        baris.value = [];
        await load();
        window.dispatchEvent(new CustomEvent('pos:stock-changed'));
    } catch (e) {
        toast.error(
            friendlyError(e, 'Gagal menyimpan transaksi pembelian. Silakan coba kembali.'),
        );
    } finally {
        saving.value = false;
    }
}

onMounted(() => {
    void (async () => {
        await pos.init();
        supplierList.value = (
            await fetchSupplier({ id_sekolah: pos.idSekolah, per_page: 100 })
        ).data;
        barangList.value = (
            await fetchBarang({ id_sekolah: pos.idSekolah, per_page: 100 })
        ).data;
        idSupplier.value = supplierList.value[0]?.id_supplier ?? 0;
        await load();

        // Cek query parameters untuk restock otomatis dari Prediksi Stok
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('tambah') === '1') {
            const pIdBarang = Number(urlParams.get('id_barang') || 0);
            const pJumlah = Math.max(1, Number(urlParams.get('jumlah') || 1));
            const pIdSupplier = Number(urlParams.get('id_supplier') || 0);

            if (
                pIdSupplier &&
                supplierList.value.some((s) => s.id_supplier === pIdSupplier)
            ) {
                idSupplier.value = pIdSupplier;
            }

            const foundBarang = barangList.value.find(
                (b) => b.id_barang === pIdBarang,
            );
            if (foundBarang) {
                baris.value = [
                    {
                        id_barang: foundBarang.id_barang,
                        jumlah: pJumlah,
                        harga_beli: Number(foundBarang.harga_beli) || 0,
                    },
                ];
                showForm.value = true;
                toast.info(
                    `Restock otomatis: ${foundBarang.nama} (${pJumlah} ${foundBarang.satuan}) siap diproses.`,
                );
            }
        }
    })();
});
</script>

<template>
    <Head title="Pembelian" />
    <PosLayout>
        <PageHeader
            title="Pembelian"
            :icon="Truck"
            subtitle="Pencatatan faktur pengadaan barang ke supplier dan restock otomatis"
        >
            <template #actions>
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white shadow-xs hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500"
                    @click="showForm = true"
                >
                    <Plus class="h-4 w-4" /> Tambah Pembelian
                </button>
            </template>
        </PageHeader>

        <div v-if="loading" class="space-y-2">
            <div
                v-for="i in 4"
                :key="i"
                class="h-16 animate-pulse rounded-xl bg-white dark:bg-slate-900"
            />
        </div>
        <EmptyState
            v-else-if="riwayat.length === 0"
            title="Belum Ada Riwayat Pembelian"
            message="Belum ada data transaksi pembelian stok ke supplier yang tercatat."
        />
        <div
            v-else
            class="overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
        >
            <table class="w-full min-w-170 text-left text-sm">
                <thead>
                    <tr
                        class="border-b border-slate-100 bg-slate-50 text-xs text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-800/80 dark:text-slate-400"
                    >
                        <th class="px-4 py-3">No. Faktur</th>
                        <th class="px-4 py-3">Pemasok (Supplier)</th>
                        <th class="px-4 py-3">Tanggal Faktur</th>
                        <th class="px-4 py-3">Status & Transaksi</th>
                        <th class="px-4 py-3 text-right">Total Pembelian</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="p in riwayat"
                        :key="p.id_pembelian"
                        class="border-b border-slate-50 hover:bg-slate-50/80 dark:border-slate-800/60 dark:hover:bg-slate-800/50"
                    >
                        <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-100">
                            {{ p.nomor_faktur }}
                        </td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ p.supplier?.nama ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                            {{ tanggal(p.tanggal_faktur) }}
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400"
                                >{{ p.status_pembelian }} ·
                                {{ p.jenis_transaksi }}</span
                            >
                        </td>
                        <td class="px-4 py-3 text-right font-bold text-slate-900 dark:text-white">
                            {{ rupiah(p.total_bayar) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination
            :page="page"
            :last-page="lastPage"
            :total="total"
            @change="
                (p) => {
                    page = p;
                    load();
                }
            "
        />

        <Modal
            :open="showForm"
            title="Tambah Transaksi Pembelian"
            wide
            @close="showForm = false"
        >
            <div class="grid gap-4 sm:grid-cols-3">
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                    >Pemasok (Supplier)*
                    <select
                        v-model="idSupplier"
                        class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    >
                        <option
                            v-for="s in supplierList"
                            :key="s.id_supplier"
                            :value="s.id_supplier"
                        >
                            {{ s.nama }}
                        </option>
                    </select>
                </label>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                    >Jenis Transaksi
                    <select
                        v-model="jenis"
                        class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    >
                        <option value="tunai">Tunai</option>
                        <option value="kredit">Kredit</option>
                    </select>
                </label>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                    >Metode Pembayaran
                    <select
                        v-model="cara"
                        class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    >
                        <option value="tunai">Tunai</option>
                        <option value="transfer">Transfer</option>
                    </select>
                </label>
            </div>
            <div class="mt-4 space-y-3">
                <div
                    v-for="(b, i) in baris"
                    :key="i"
                    class="grid grid-cols-[1fr_auto] items-end gap-2 rounded-xl border border-slate-100 bg-slate-50 p-3 sm:grid-cols-[1fr_90px_130px_auto] dark:border-slate-800 dark:bg-slate-800/50"
                >
                    <label class="text-xs text-slate-500 dark:text-slate-400"
                        >Pilihan Produk
                        <select
                            v-model="b.id_barang"
                            class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-2 py-2 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                        >
                            <option
                                v-for="x in barangList"
                                :key="x.id_barang"
                                :value="x.id_barang"
                            >
                                {{ x.nama }} (Stok: {{ x.stok }})
                            </option>
                        </select>
                    </label>
                    <label class="text-xs text-slate-500 dark:text-slate-400"
                        >Jumlah (Qty)
                        <input
                            v-model.number="b.jumlah"
                            type="number"
                            min="1"
                            class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-2 py-2 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                        />
                    </label>
                    <label class="text-xs text-slate-500 dark:text-slate-400"
                        >Harga Beli (Rp)
                        <input
                            v-model.number="b.harga_beli"
                            type="number"
                            min="0"
                            class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-2 py-2 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                        />
                    </label>
                    <button
                        type="button"
                        class="cursor-pointer rounded-lg p-2 text-slate-400 hover:text-red-600 dark:text-slate-500 dark:hover:text-red-400 transition"
                        @click="baris.splice(i, 1)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </button>
                </div>
                <button
                    type="button"
                    class="cursor-pointer text-sm font-semibold text-blue-700 hover:underline dark:text-blue-400"
                    @click="tambahBaris"
                >
                    + Tambah Item Produk
                </button>
            </div>
            <label class="mt-4 block text-xs font-medium text-slate-600 dark:text-slate-300"
                >Catatan Transaksi (Opsional)
                <input
                    v-model="note"
                    placeholder="Contoh: Pengadaan berkala stok seragam / faktur fisik no. 1024"
                    class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                />
            </label>
            <div class="mt-4 flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Total Pembelian:
                    <strong class="text-lg text-blue-800 dark:text-blue-400">{{
                        rupiah(totalBelanja)
                    }}</strong>
                </p>
                <button
                    type="button"
                    :disabled="saving"
                    class="cursor-pointer rounded-xl bg-blue-700 px-6 py-2.5 text-sm font-bold text-white hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500 transition disabled:opacity-60 shadow-xs"
                    @click="simpan"
                >
                    {{ saving ? 'Menyimpan…' : 'Simpan Transaksi' }}
                </button>
            </div>
        </Modal>
    </PosLayout>
</template>
