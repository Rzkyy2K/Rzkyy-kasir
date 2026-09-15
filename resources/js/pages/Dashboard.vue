<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Boxes,
    LayoutDashboard,
    Package,
    ReceiptText,
    TrendingUp,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import EmptyState from '@/components/pos/EmptyState.vue';
import PageHeader from '@/components/pos/PageHeader.vue';
import StatCard from '@/components/pos/StatCard.vue';
import PosLayout from '@/layouts/PosLayout.vue';
import { rupiah, tanggal } from '@/lib/format';
import { friendlyError } from '@/services/api';
import { fetchDashboard } from '@/services/masterService';
import { usePosStore } from '@/stores/pos';

const pos = usePosStore();
const loading = ref(true);
const data = ref<any>(null);

async function load() {
    loading.value = true;
    try {
        data.value = await fetchDashboard(pos.idSekolah);
    } catch (e) {
        toast.error(friendlyError(e, 'Dashboard gagal dimuat.'));
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    void (async () => {
        await pos.init();
        await load();
    })();
});
watch(() => pos.idSekolah, load);

const maxGrafik = computed(() => {
    const arr = (data.value?.grafik_penjualan_7_hari ?? []) as Array<{
        total: number | string;
    }>;
    const max = Math.max(...arr.map((x) => Number(x.total) || 0), 1);
    return max;
});
</script>

<template>
    <Head title="Dashboard" />
    <PosLayout>
        <PageHeader
            title="Dashboard"
            :icon="LayoutDashboard"
            :subtitle="
                pos.sekolahAktif
                    ? `Ringkasan ${pos.sekolahAktif.nama_sekolah} — ${tanggal(new Date().toISOString())}`
                    : 'Ringkasan toko hari ini'
            "
        >
            <template #actions>
                <Link
                    href="/kasir"
                    class="rounded-xl bg-blue-700 px-3 py-1.5 text-sm font-bold text-white hover:bg-blue-800"
                >
                    Buka Kasir
                </Link>
                <Link
                    href="/laporan"
                    class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold hover:border-blue-300"
                >
                    Lihat Laporan
                </Link>
            </template>
        </PageHeader>

        <!-- skeleton : gap disamakan foto = 12px mobile / 16px desktop -->
        <div v-if="loading" class="flex flex-col gap-3 lg:gap-4">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:gap-4">
                <div
                    v-for="i in 4"
                    :key="i"
                    class="h-24 animate-pulse rounded-2xl bg-white"
                />
            </div>
            <div class="grid gap-3 lg:grid-cols-3 lg:gap-4">
                <div class="h-64 animate-pulse rounded-2xl bg-white lg:col-span-2" />
                <div class="h-64 animate-pulse rounded-2xl bg-white" />
            </div>
        </div>

        <template v-else-if="data">
            <!-- wrapper vertikal: gap konsisten seperti di foto (12px mobile, 16px desktop) -->
            <div class="flex flex-col gap-3 lg:gap-4">
                <!-- Stat atas -->
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:gap-4">
                <StatCard
                    title="Penjualan Hari Ini"
                    :value="rupiah(data.penjualan_hari_ini)"
                    :hint="`${data.transaksi_hari_ini} transaksi hari ini`"
                    color="blue"
                    :icon="TrendingUp"
                />
                <StatCard
                    title="Transaksi Hari Ini"
                    :value="String(data.transaksi_hari_ini)"
                    hint="Jumlah struk hari ini"
                    color="emerald"
                    :icon="ReceiptText"
                />
                <StatCard
                    title="Total Produk Aktif"
                    :value="String(data.total_produk)"
                    :hint="`${data.total_stok} pcs total stok`"
                    color="violet"
                    :icon="Package"
                />
                <StatCard
                    title="Total Stok"
                    :value="String(data.total_stok)"
                    hint="Semua barang aktif"
                    color="amber"
                    :icon="Boxes"
                />
            </div>

                <!-- Grafik + stok rendah -->
                <div class="grid gap-3 lg:grid-cols-3 lg:gap-4">
                <!-- Grafik 7 hari -->
                <div
                    class="rounded-2xl border border-slate-200 bg-white p-4 lg:col-span-2"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-slate-900">
                            Grafik Penjualan 7 Hari
                        </h2>
                        <span class="text-xs text-slate-400"
                            >{{ data.grafik_penjualan_7_hari?.length ?? 0 }} hari</span
                        >
                    </div>
                    <div
                        v-if="
                            !data.grafik_penjualan_7_hari ||
                            data.grafik_penjualan_7_hari.length === 0
                        "
                    >
                        <EmptyState
                            title="Belum ada penjualan 7 hari terakhir"
                            message="Transaksi baru akan muncul di grafik ini."
                        />
                    </div>
                    <div v-else class="flex items-end gap-2 pt-2" style="height: 220px">
                        <div
                            v-for="g in data.grafik_penjualan_7_hari"
                            :key="g.tanggal"
                            class="flex flex-1 flex-col items-center gap-1.5"
                        >
                            <span
                                class="text-[10px] font-bold text-slate-700 sm:text-xs"
                            >
                                {{ rupiah(g.total) }}
                            </span>
                            <div
                                class="w-full rounded-t-xl bg-blue-700 transition-all"
                                :style="{
                                    height:
                                        Math.max(
                                            (Number(g.total) / maxGrafik) * 140,
                                            6,
                                        ) + 'px',
                                }"
                                :title="`${g.tanggal}: ${rupiah(g.total)} (${g.transaksi} trx)`"
                            />
                            <span class="text-[10px] leading-tight text-slate-500">
                                {{
                                    new Date(g.tanggal).toLocaleDateString(
                                        'id-ID',
                                        {
                                            day: '2-digit',
                                            month: 'short',
                                        },
                                    )
                                }}
                            </span>
                            <span class="text-[10px] text-slate-400"
                                >{{ g.transaksi }} trx</span
                            >
                        </div>
                    </div>
                </div>

                <!-- Stok rendah -->
                <div
                    class="rounded-2xl border border-slate-200 bg-white p-4"
                >
                    <div class="mb-4 flex items-center gap-2">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600"
                        >
                            <AlertTriangle class="h-4 w-4" />
                        </div>
                        <h2 class="text-sm font-bold text-slate-900">
                            Stok Rendah (≤ 10)
                        </h2>
                    </div>
                    <div
                        v-if="
                            !data.produk_stok_rendah ||
                            data.produk_stok_rendah.length === 0
                        "
                    >
                        <p class="py-4 text-center text-sm text-slate-400">
                            Semua stok aman ✨
                        </p>
                    </div>
                    <ul v-else class="divide-y divide-slate-100">
                        <li
                            v-for="b in data.produk_stok_rendah"
                            :key="b.id_barang"
                            class="flex items-center justify-between gap-4 py-2.5"
                        >
                            <div class="min-w-0">
                                <p
                                    class="truncate text-sm font-semibold text-slate-800"
                                >
                                    {{ b.nama }}
                                </p>
                                <p class="text-xs text-slate-400">
                                    {{ b.satuan }} · {{ b.kategori?.nama ?? '—' }}
                                </p>
                            </div>
                            <span
                                class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold"
                                :class="
                                    b.stok === 0
                                        ? 'bg-red-50 text-red-600'
                                        : b.stok <= 5
                                          ? 'bg-amber-50 text-amber-600'
                                          : 'bg-slate-100 text-slate-600'
                                "
                            >
                                {{ b.stok }} pcs
                            </span>
                        </li>
                    </ul>
                    <Link
                        href="/stok"
                        class="mt-4 inline-flex w-full justify-center rounded-xl border border-slate-200 bg-white py-2 text-sm font-semibold hover:border-blue-300"
                    >
                        Kelola Stok
                    </Link>
                </div>
            </div>

                <!-- Transaksi terbaru -->
                <div
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white"
                >
                <div class="flex items-center justify-between px-4 py-3">
                    <h2 class="text-sm font-bold text-slate-900">
                        Transaksi Terbaru
                    </h2>
                    <Link
                        href="/penjualan"
                        class="text-xs font-semibold text-blue-700 hover:underline"
                    >
                        Lihat semua
                    </Link>
                </div>
                <div
                    v-if="
                        !data.transaksi_terbaru ||
                        data.transaksi_terbaru.length === 0
                    "
                    class="px-4 pb-4"
                >
                    <EmptyState
                        title="Belum ada transaksi"
                        message="Buat transaksi di halaman Kasir, data akan muncul di sini."
                    />
                </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-160 text-left text-sm">
                            <thead>
                                <tr
                                    class="border-y border-slate-100 bg-slate-50 text-xs text-slate-500 uppercase"
                                >
                                    <th class="px-4 py-3">#</th>
                                    <th class="px-4 py-3">Tanggal</th>
                                    <th class="px-4 py-3">Kasir</th>
                                    <th class="px-4 py-3">Pelanggan</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="t in data.transaksi_terbaru"
                                    :key="t.id_penjualan"
                                    class="border-b border-slate-50 hover:bg-slate-50"
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
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>

        <div v-else class="mt-4">
            <EmptyState
                title="Gagal memuat dashboard"
                message="Coba muat ulang halaman."
            />
        </div>
    </PosLayout>
</template>
