<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Download , BarChart3 } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import EmptyState from '@/components/pos/EmptyState.vue';
import PageHeader from '@/components/pos/PageHeader.vue';
import PosLayout from '@/layouts/PosLayout.vue';
import { rupiah, tanggal } from '@/lib/format';
import { friendlyError } from '@/services/api';
import { fetchLaporan } from '@/services/masterService';
import { usePosStore } from '@/stores/pos';

const pos = usePosStore();
const tab = ref<'penjualan' | 'pembelian' | 'stok' | 'terlaris'>('penjualan');
const loading = ref(false);
const dari = ref('');
const sampai = ref('');
const ringkasan = ref<any>(null);
const rows = ref<any[]>([]);
const total = ref(0);

const tabs = [
    { key: 'penjualan', label: 'Penjualan' },
    { key: 'pembelian', label: 'Pembelian' },
    { key: 'stok', label: 'Stok' },
    { key: 'terlaris', label: 'Terlaris' },
] as const;

async function load() {
    loading.value = true;
    try {
        const res: any = await fetchLaporan(tab.value, {
            id_sekolah: pos.idSekolah,
            dari: dari.value || undefined,
            sampai: sampai.value || undefined,
            per_page: 50,
        });
        if (tab.value === 'terlaris') {
            rows.value = res;
            ringkasan.value = null;
            total.value = res.length;
        } else {
            ringkasan.value = res.ringkasan;
            rows.value = res.data.data ?? [];
            total.value = res.data.total ?? 0;
        }
    } catch (e) {
        toast.error(friendlyError(e, 'Gagal memuat data laporan.'));
    } finally {
        loading.value = false;
    }
}

const judulKolom = computed(() => {
    if (tab.value === 'penjualan')
        return ['Tanggal Transaksi', 'Petugas Kasir', 'Metode Pembayaran', 'Total Penjualan'];
    if (tab.value === 'pembelian')
        return ['No. Faktur', 'Pemasok (Supplier)', 'Tanggal Faktur', 'Total Pembelian'];
    if (tab.value === 'stok') return ['Nama Produk', 'Harga Jual', 'Sisa Stok'];
    return ['Nama Produk', 'Total Terjual', 'Total Omzet'];
});

function cell(row: any, i: number): string {
    if (tab.value === 'penjualan') {
        return [
            tanggal(row.tanggal_penjualan),
            row.kasir?.nama_lengkap ?? '—',
            row.cara_bayar,
            rupiah(row.total_faktur),
        ][i];
    }
    if (tab.value === 'pembelian') {
        return [
            row.nomor_faktur,
            row.supplier?.nama ?? '—',
            tanggal(row.tanggal_faktur),
            rupiah(row.total_bayar),
        ][i];
    }
    if (tab.value === 'stok') {
        return [
            row.nama,
            rupiah(row.harga_jual),
            `${row.stok} ${row.satuan ?? ''}`,
        ][i];
    }
    return [
        row.barang?.nama ?? `#${row.id_barang}`,
        `${row.total_terjual} pcs`,
        rupiah(row.total_omzet),
    ][i];
}

function exportCsv() {
    const head = judulKolom.value.join(';');
    const lines = rows.value.map((r) =>
        judulKolom.value
            .map((_, i) => `"${cell(r, i).replace(/"/g, '""')}"`)
            .join(';'),
    );
    const blob = new Blob([[head, ...lines].join('\n')], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `laporan-${tab.value}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
}

onMounted(() => {
    void (async () => {
        await pos.init();
        await load();
    })();
});
watch([tab, () => pos.idSekolah], load);
</script>

<template>
    <Head title="Laporan" />
    <PosLayout>
        <PageHeader
            title="Laporan"
            :icon="BarChart3"
            subtitle="Analisis dan rekapitulasi data penjualan, pengadaan, stok, serta produk terlaris"
        >
            <template #actions>
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 transition"
                    @click="exportCsv"
                >
                    <Download class="h-4 w-4" /> Ekspor CSV
                </button>
            </template>
        </PageHeader>

        <div class="flex gap-2 overflow-x-auto pb-1">
            <button
                v-for="t in tabs"
                :key="t.key"
                type="button"
                :class="[
                    'shrink-0 cursor-pointer rounded-full border px-4 py-2 text-sm font-semibold transition',
                    tab === t.key
                        ? 'border-blue-700 bg-blue-700 text-white shadow-xs dark:border-blue-600 dark:bg-blue-600'
                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800',
                ]"
                @click="tab = t.key"
            >
                {{ t.label }}
            </button>
        </div>

        <div
            v-if="tab === 'penjualan' || tab === 'pembelian'"
            class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end"
        >
            <label class="text-xs text-slate-500 dark:text-slate-400"
                >Dari Tanggal
                <input
                    v-model="dari"
                    type="date"
                    class="mt-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-xs dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100"
                />
            </label>
            <label class="text-xs text-slate-500 dark:text-slate-400"
                >Sampai Tanggal
                <input
                    v-model="sampai"
                    type="date"
                    class="mt-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-xs dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100"
                />
            </label>
            <button
                type="button"
                class="cursor-pointer rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500 shadow-xs"
                @click="load"
            >
                Tampilkan Laporan
            </button>
        </div>

        <div
            v-if="ringkasan && (tab === 'penjualan' || tab === 'pembelian')"
            class="mt-4 grid grid-cols-2 gap-4"
        >
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs text-slate-500 dark:text-slate-400 uppercase font-semibold">Total Transaksi</p>
                <p class="text-xl font-bold text-slate-900 dark:text-white">{{ ringkasan.total_transaksi }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs text-slate-500 dark:text-slate-400 uppercase font-semibold">
                    {{ tab === 'penjualan' ? 'Total Omzet Penjualan' : 'Total Belanja Pengadaan' }}
                </p>
                <p class="text-xl font-bold text-blue-800 dark:text-blue-400">
                    {{
                        rupiah(ringkasan.total_omzet ?? ringkasan.total_belanja)
                    }}
                </p>
            </div>
        </div>

        <div
            v-if="ringkasan && tab === 'stok'"
            class="mt-4 grid grid-cols-2 gap-4"
        >
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs text-slate-500 dark:text-slate-400 uppercase font-semibold">Total Jenis Produk</p>
                <p class="text-xl font-bold text-slate-900 dark:text-white">{{ ringkasan.total_item }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs text-slate-500 dark:text-slate-400 uppercase font-semibold">Total Fisik Stok</p>
                <p class="text-xl font-bold text-blue-800 dark:text-blue-400">
                    {{ ringkasan.total_stok }}
                </p>
            </div>
        </div>

        <div v-if="loading" class="mt-4 space-y-2">
            <div
                v-for="i in 5"
                :key="i"
                class="h-12 animate-pulse rounded-xl bg-white dark:bg-slate-900"
            />
        </div>
        <EmptyState
            v-else-if="rows.length === 0"
            class="mt-4"
            title="Belum Ada Data Laporan"
            message="Tidak ditemukan data transaksi pada periode atau kategori yang dipilih."
        />
        <div
            v-else
            class="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
        >
            <table class="w-full min-w-150 text-left text-sm">
                <thead>
                    <tr
                        class="border-b border-slate-100 bg-slate-50 text-xs text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-800/80 dark:text-slate-400"
                    >
                        <th
                            v-for="(h, i) in judulKolom"
                            :key="h"
                            class="px-4 py-3"
                            :class="{
                                'text-right': i === judulKolom.length - 1,
                            }"
                        >
                            {{ h }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(r, ri) in rows"
                        :key="ri"
                        class="border-b border-slate-50 hover:bg-slate-50/80 dark:border-slate-800/60 dark:hover:bg-slate-800/50 dark:text-slate-200"
                    >
                        <td
                            v-for="(_, i) in judulKolom"
                            :key="i"
                            class="px-4 py-3"
                            :class="{
                                'text-right font-semibold text-slate-900 dark:text-white':
                                    i === judulKolom.length - 1,
                            }"
                        >
                            {{ cell(r, i) }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="px-4 py-2 text-xs text-slate-400 dark:text-slate-500">
                Menampilkan {{ rows.length }} dari {{ total }} data laporan
            </p>
        </div>
    </PosLayout>
</template>
