<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Eye , ReceiptText } from '@lucide/vue';
import { onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import EmptyState from '@/components/pos/EmptyState.vue';
import Modal from '@/components/pos/Modal.vue';
import PageHeader from '@/components/pos/PageHeader.vue';
import Pagination from '@/components/pos/Pagination.vue';
import PosLayout from '@/layouts/PosLayout.vue';
import { rupiah, tanggal } from '@/lib/format';
import { friendlyError } from '@/services/api';
import {
    fetchPenjualan,
    fetchPenjualanById,
} from '@/services/penjualanService';
import { usePosStore } from '@/stores/pos';
import type { Penjualan } from '@/types/pos';

const pos = usePosStore();
const loading = ref(true);
const rows = ref<Penjualan[]>([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const dari = ref('');
const sampai = ref('');

const detail = ref<Penjualan | null>(null);
const loadingDetail = ref(false);

async function load() {
    loading.value = true;
    try {
        const res = await fetchPenjualan({
            id_sekolah: pos.idSekolah,
            dari: dari.value || undefined,
            sampai: sampai.value || undefined,
            per_page: 12,
            page: page.value,
        });
        rows.value = res.data;
        page.value = res.current_page;
        lastPage.value = res.last_page;
        total.value = res.total;
    } catch (e) {
        toast.error(friendlyError(e, 'Riwayat penjualan gagal dimuat.'));
    } finally {
        loading.value = false;
    }
}

async function lihat(id: number) {
    loadingDetail.value = true;
    try {
        detail.value = await fetchPenjualanById(id);
    } catch (e) {
        toast.error(friendlyError(e, 'Detail transaksi gagal dimuat.'));
    } finally {
        loadingDetail.value = false;
    }
}

function filter() {
    page.value = 1;
    void load();
}

onMounted(() => {
    void (async () => {
        await pos.init();
        await load();
    })();
});
watch(() => pos.idSekolah, filter);
</script>

<template>
    <Head title="Penjualan" />
    <PosLayout>
        <PageHeader
            title="Riwayat Penjualan"
            :icon="ReceiptText"
            subtitle="Semua transaksi dari tb_penjualan"
        />
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
            <label class="text-xs text-slate-500"
                >Dari
                <input
                    v-model="dari"
                    type="date"
                    class="mt-1 rounded-lg border border-slate-200 px-4 py-3 text-sm"
                />
            </label>
            <label class="text-xs text-slate-500"
                >Sampai
                <input
                    v-model="sampai"
                    type="date"
                    class="mt-1 rounded-lg border border-slate-200 px-4 py-3 text-sm"
                />
            </label>
            <button
                type="button"
                class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-bold text-white"
                @click="filter"
            >
                Filter
            </button>
        </div>

        <div v-if="loading" class="mt-4 space-y-1.5">
            <div
                v-for="i in 5"
                :key="i"
                class="h-16 animate-pulse rounded-xl bg-white"
            />
        </div>
        <EmptyState
            v-else-if="rows.length === 0"
            class="mt-4"
            title="Belum ada penjualan"
            message="Buat transaksi baru lewat halaman Kasir."
        />
        <div
            v-else
            class="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white"
        >
            <table class="w-full min-w-180 text-left text-sm">
                <thead>
                    <tr
                        class="border-b border-slate-100 bg-slate-50 text-xs text-slate-500 uppercase"
                    >
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Kasir</th>
                        <th class="px-4 py-3">Pelanggan</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="t in rows"
                        :key="t.id_penjualan"
                        class="border-b border-slate-50"
                    >
                        <td class="px-4 py-3 text-slate-400">
                            {{ t.id_penjualan }}
                        </td>
                        <td class="px-4 py-3">
                            {{ tanggal(t.tanggal_penjualan) }}
                        </td>
                        <td class="px-4 py-3">
                            {{ t.kasir?.nama_lengkap ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            {{ t.pelanggan?.nama_pelanggan ?? 'Umum' }}
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-600"
                                >{{ t.status_pembayaran }}</span
                            >
                        </td>
                        <td class="px-4 py-3 text-right font-bold">
                            {{ rupiah(t.total_faktur) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button
                                type="button"
                                class="rounded-lg p-2 text-slate-400 hover:bg-blue-50 hover:text-blue-700"
                                @click="lihat(t.id_penjualan)"
                            >
                                <Eye class="h-4 w-4" />
                            </button>
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
            :open="detail !== null || loadingDetail"
            title="Detail Transaksi"
            wide
            @close="detail = null"
        >
            <p
                v-if="loadingDetail"
                class="py-4 text-center text-sm text-slate-400"
            >
                Memuat…
            </p>
            <div v-else-if="detail" class="text-sm">
                <div class="grid grid-cols-2 gap-1.5 text-slate-600">
                    <p>
                        Tanggal:
                        <strong>{{ tanggal(detail.tanggal_penjualan) }}</strong>
                    </p>
                    <p>
                        Kasir: <strong>{{ detail.kasir?.nama_lengkap }}</strong>
                    </p>
                    <p>
                        Cara bayar: <strong>{{ detail.cara_bayar }}</strong>
                    </p>
                    <p>
                        Status: <strong>{{ detail.status_pembayaran }}</strong>
                    </p>
                </div>
                <table class="mt-4 w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-xs text-slate-400 uppercase">
                            <th class="py-2">Barang</th>
                            <th class="py-2 text-right">Qty</th>
                            <th class="py-2 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="d in detail.detail ?? []"
                            :key="d.id_detail_penjualan"
                            class="border-b border-slate-50"
                        >
                            <td class="py-2">{{ d.barang?.nama }}</td>
                            <td class="py-2 text-right">
                                {{ d.jumlah_barang }}
                            </td>
                            <td class="py-2 text-right font-semibold">
                                {{ rupiah(d.subtotal) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="mt-4 space-y-1 text-right">
                    <p>
                        Total:
                        <strong>{{ rupiah(detail.total_faktur) }}</strong>
                    </p>
                    <p>
                        Bayar: <strong>{{ rupiah(detail.total_bayar) }}</strong>
                    </p>
                    <p>
                        Kembalian:
                        <strong>{{ rupiah(detail.kembalian) }}</strong>
                    </p>
                </div>
            </div>
        </Modal>
    </PosLayout>
</template>
