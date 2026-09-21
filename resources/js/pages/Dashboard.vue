<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BarChart3,
    Boxes,
    LayoutDashboard,
    LineChart,
    Package,
    ReceiptText,
    TrendingUp,
    Trophy,
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

interface GrafikItem {
    tanggal: string;
    total: number | string;
    transaksi: number | string;
}

const pos = usePosStore();
const loading = ref(true);
const data = ref<any>(null);

const metricMode = ref<'omset' | 'transaksi'>('omset');
const chartType = ref<'bar' | 'line'>('bar');
const hoveredIndex = ref<number | null>(null);

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

const grafikList = computed<GrafikItem[]>(() => {
    return (data.value?.grafik_penjualan_7_hari ?? []) as GrafikItem[];
});

const totalOmset7Hari = computed(() => {
    return grafikList.value.reduce((acc, x) => acc + (Number(x.total) || 0), 0);
});

const totalTransaksi7Hari = computed(() => {
    return grafikList.value.reduce(
        (acc, x) => acc + (Number(x.transaksi) || 0),
        0,
    );
});

const rataRataOmset = computed(() => {
    const len = grafikList.value.length || 7;
    return Math.round(totalOmset7Hari.value / len);
});

const bestDay = computed(() => {
    if (!grafikList.value.length) return null;
    let maxItem = grafikList.value[0];
    let maxVal = Number(maxItem.total) || 0;
    for (const item of grafikList.value) {
        const val = Number(item.total) || 0;
        if (val > maxVal) {
            maxVal = val;
            maxItem = item;
        }
    }
    return maxVal > 0 ? maxItem : null;
});

function getNiceCeiling(num: number): number {
    if (num <= 0) return 100;
    const exp = Math.pow(10, Math.floor(Math.log10(num)));
    const fraction = num / exp;
    let niceFraction: number;
    if (fraction <= 1) niceFraction = 1;
    else if (fraction <= 2) niceFraction = 2;
    else if (fraction <= 5) niceFraction = 5;
    else niceFraction = 10;
    return niceFraction * exp;
}

const ceilingValue = computed(() => {
    if (metricMode.value === 'omset') {
        const max = Math.max(
            ...grafikList.value.map((x) => Number(x.total) || 0),
            10000,
        );
        return getNiceCeiling(max);
    } else {
        const max = Math.max(
            ...grafikList.value.map((x) => Number(x.transaksi) || 0),
            5,
        );
        return getNiceCeiling(max);
    }
});

const gridSteps = computed(() => {
    const ceil = ceilingValue.value;
    return [
        { pct: 100, val: ceil },
        { pct: 75, val: ceil * 0.75 },
        { pct: 50, val: ceil * 0.5 },
        { pct: 25, val: ceil * 0.25 },
        { pct: 0, val: 0 },
    ];
});

function formatCompact(val: number): string {
    if (metricMode.value === 'omset') {
        if (val >= 1_000_000_000) {
            return (
                (val / 1_000_000_000).toLocaleString('id-ID', {
                    maximumFractionDigits: 1,
                }) + ' M'
            );
        }
        if (val >= 1_000_000) {
            return (
                (val / 1_000_000).toLocaleString('id-ID', {
                    maximumFractionDigits: 1,
                }) + ' jt'
            );
        }
        if (val >= 1_000) {
            return (
                (val / 1_000).toLocaleString('id-ID', {
                    maximumFractionDigits: 0,
                }) + ' rb'
            );
        }
        return val > 0 ? String(val) : '0';
    } else {
        return `${Math.round(val)}`;
    }
}

function formatHari(tanggalStr: string): string {
    return new Date(tanggalStr).toLocaleDateString('id-ID', { weekday: 'short' });
}

function formatTglBulan(tanggalStr: string): string {
    return new Date(tanggalStr).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
    });
}

function formatTanggalLengkap(tanggalStr: string): string {
    return new Date(tanggalStr).toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function isHariIni(tanggalStr: string): boolean {
    const d = new Date(tanggalStr);
    const now = new Date();
    return (
        d.getFullYear() === now.getFullYear() &&
        d.getMonth() === now.getMonth() &&
        d.getDate() === now.getDate()
    );
}

// SVG Points calculation for smooth area trend
const svgData = computed(() => {
    const items = grafikList.value;
    if (!items.length) return { points: [], linePath: '', areaPath: '' };
    const width = 700;
    const height = 180;
    const paddingX = 40;
    const paddingTop = 20;
    const paddingBottom = 20;
    const drawHeight = height - paddingTop - paddingBottom;
    const ceil = ceilingValue.value || 1;

    const stepX = (width - paddingX * 2) / Math.max(items.length - 1, 1);

    const points = items.map((item, idx) => {
        const val =
            metricMode.value === 'omset'
                ? Number(item.total) || 0
                : Number(item.transaksi) || 0;
        const x = paddingX + idx * stepX;
        const y = height - paddingBottom - (val / ceil) * drawHeight;
        return { x, y, item, val, idx };
    });

    if (points.length <= 1) return { points, linePath: '', areaPath: '' };

    let linePath = `M ${points[0].x} ${points[0].y}`;
    for (let i = 0; i < points.length - 1; i++) {
        const p0 = points[i === 0 ? 0 : i - 1];
        const p1 = points[i];
        const p2 = points[i + 1];
        const p3 = points[i + 2 < points.length ? i + 2 : i + 1];

        const cp1x = p1.x + (p2.x - p0.x) / 6;
        const cp1y = p1.y + (p2.y - p0.y) / 6;
        const cp2x = p2.x - (p3.x - p1.x) / 6;
        const cp2y = p2.y - (p3.y - p1.y) / 6;

        linePath += ` C ${cp1x.toFixed(1)} ${cp1y.toFixed(1)}, ${cp2x.toFixed(1)} ${cp2y.toFixed(1)}, ${p2.x.toFixed(1)} ${p2.y.toFixed(1)}`;
    }

    const baseline = height - paddingBottom;
    const areaPath = `${linePath} L ${points[points.length - 1].x} ${baseline} L ${points[0].x} ${baseline} Z`;

    return { points, linePath, areaPath };
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
                    ? `Ringkasan operasional ${pos.sekolahAktif.nama_sekolah} — ${tanggal(new Date().toISOString())}`
                    : 'Ringkasan operasional dan penjualan toko hari ini'
            "
        >
            <template #actions>
                <Link
                    href="/kasir"
                    class="rounded-xl bg-blue-700 px-3 py-1.5 text-sm font-bold text-white hover:bg-blue-800 active-press dark:bg-blue-600 dark:hover:bg-blue-500 shadow-xs"
                >
                    Buka Kasir
                </Link>
                <Link
                    href="/laporan"
                    class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold hover:border-blue-300 active-press dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-700 shadow-xs"
                >
                    Lihat Laporan
                </Link>
            </template>
        </PageHeader>

        <!-- skeleton : gap disamakan foto = 12px mobile / 16px desktop -->
        <div v-if="loading" class="flex flex-col gap-3 lg:gap-4 animate-fade-in">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:gap-4">
                <div
                    v-for="i in 4"
                    :key="i"
                    class="h-24 animate-pulse rounded-2xl bg-white dark:bg-slate-900"
                />
            </div>
            <div class="grid gap-3 lg:grid-cols-3 lg:gap-4">
                <div class="h-64 animate-pulse rounded-2xl bg-white dark:bg-slate-900 lg:col-span-2" />
                <div class="h-64 animate-pulse rounded-2xl bg-white dark:bg-slate-900" />
            </div>
        </div>

        <template v-else-if="data">
            <!-- wrapper vertikal: gap konsisten seperti di foto (12px mobile, 16px desktop) -->
            <div class="flex flex-col gap-3 lg:gap-4 animate-fade-up">
                <!-- Stat atas -->
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:gap-4">
                <StatCard
                    title="Penjualan Hari Ini"
                    :value="rupiah(data.penjualan_hari_ini)"
                    :hint="`${data.transaksi_hari_ini} transaksi selesai hari ini`"
                    color="blue"
                    :icon="TrendingUp"
                />
                <StatCard
                    title="Transaksi Hari Ini"
                    :value="String(data.transaksi_hari_ini)"
                    hint="Total struk terbit hari ini"
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
                    hint="Total unit stok tersedia"
                    color="amber"
                    :icon="Boxes"
                />
            </div>

                <!-- Grafik + stok rendah -->
                <div class="grid gap-3 lg:grid-cols-3 lg:gap-4">
                <!-- Grafik 7 hari -->
                <div
                    class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 lg:col-span-2 shadow-xs dark:border-slate-800 dark:bg-slate-900"
                >
                    <!-- Header with Title and Switchers -->
                    <div
                        class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-3 dark:border-slate-800"
                    >
                        <div class="flex items-center gap-2.5">
                            <div
                                class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400"
                            >
                                <BarChart3 class="h-4.5 w-4.5" />
                            </div>
                            <div>
                                <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                                    Tren Penjualan 7 Hari
                                </h2>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                    Pantau tren omzet dan volume transaksi 7 hari terakhir
                                </p>
                            </div>
                        </div>

                        <!-- Controls -->
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Metric Toggle -->
                            <div
                                class="inline-flex rounded-xl bg-slate-100 p-1 text-xs dark:bg-slate-800"
                            >
                                <button
                                    type="button"
                                    :class="[
                                        'rounded-lg px-2.5 py-1 font-semibold transition cursor-pointer',
                                        metricMode === 'omset'
                                            ? 'bg-white text-blue-700 shadow-sm dark:bg-slate-900 dark:text-blue-400'
                                            : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200',
                                    ]"
                                    @click="metricMode = 'omset'"
                                >
                                    Omzet (Rp)
                                </button>
                                <button
                                    type="button"
                                    :class="[
                                        'rounded-lg px-2.5 py-1 font-semibold transition cursor-pointer',
                                        metricMode === 'transaksi'
                                            ? 'bg-white text-emerald-700 shadow-sm dark:bg-slate-900 dark:text-emerald-400'
                                            : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200',
                                    ]"
                                    @click="metricMode = 'transaksi'"
                                >
                                    Transaksi
                                </button>
                            </div>

                            <!-- Chart Type Switcher -->
                            <div
                                class="inline-flex rounded-xl bg-slate-100 p-1 text-xs dark:bg-slate-800"
                            >
                                <button
                                    type="button"
                                    title="Grafik Batang"
                                    :class="[
                                        'rounded-lg p-1.5 transition cursor-pointer',
                                        chartType === 'bar'
                                            ? 'bg-white text-blue-700 shadow-sm dark:bg-slate-900 dark:text-blue-400'
                                            : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200',
                                    ]"
                                    @click="chartType = 'bar'"
                                >
                                    <BarChart3 class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    title="Tren Garis"
                                    :class="[
                                        'rounded-lg p-1.5 transition cursor-pointer',
                                        chartType === 'line'
                                            ? 'bg-white text-blue-700 shadow-sm dark:bg-slate-900 dark:text-blue-400'
                                            : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200',
                                    ]"
                                    @click="chartType = 'line'"
                                >
                                    <LineChart class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Mini KPI Summary Bar -->
                    <div
                        class="mb-4 grid grid-cols-3 gap-2 rounded-xl bg-slate-50/80 p-2.5 border border-slate-100 dark:bg-slate-800/50 dark:border-slate-800"
                    >
                        <div class="px-1.5 sm:px-2">
                            <p class="text-[10px] font-medium text-slate-500 dark:text-slate-400">
                                Total 7 Hari
                            </p>
                            <p
                                class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white truncate"
                            >
                                {{ rupiah(totalOmset7Hari) }}
                            </p>
                            <p class="text-[10px] text-slate-400 dark:text-slate-500 truncate">
                                {{ totalTransaksi7Hari }} transaksi
                            </p>
                        </div>
                        <div class="border-x border-slate-200 px-1.5 sm:px-2 dark:border-slate-700/60">
                            <p class="text-[10px] font-medium text-slate-500 dark:text-slate-400">
                                Rata-rata / Hari
                            </p>
                            <p
                                class="text-xs sm:text-sm font-bold text-blue-700 dark:text-blue-400 truncate"
                            >
                                {{ rupiah(rataRataOmset) }}
                            </p>
                            <p class="text-[10px] text-slate-400 dark:text-slate-500 truncate">
                                ~{{ (totalTransaksi7Hari / 7).toFixed(1) }} trx /
                                hari
                            </p>
                        </div>
                        <div class="px-1.5 sm:px-2">
                            <p class="text-[10px] font-medium text-slate-500 dark:text-slate-400">
                                Hari Tertinggi
                            </p>
                            <p
                                class="text-xs sm:text-sm font-bold text-amber-600 dark:text-amber-400 truncate flex items-center gap-1"
                            >
                                <Trophy
                                    class="h-3.5 w-3.5 shrink-0 text-amber-500"
                                />
                                <span v-if="bestDay">
                                    {{ formatHari(bestDay.tanggal) }} ({{
                                        formatTglBulan(bestDay.tanggal)
                                    }})
                                </span>
                                <span v-else>—</span>
                            </p>
                            <p class="text-[10px] text-slate-400 dark:text-slate-500 truncate">
                                {{
                                    bestDay
                                        ? rupiah(bestDay.total)
                                        : 'Belum ada data'
                                }}
                            </p>
                        </div>
                    </div>

                    <!-- Chart Content Area -->
                    <div
                        v-if="!grafikList || grafikList.length === 0"
                        class="py-8"
                    >
                        <EmptyState
                            title="Belum Ada Data Penjualan 7 Hari Terakhir"
                            message="Data grafik tren penjualan akan diperbarui secara otomatis saat transaksi baru tercatat."
                        />
                    </div>
                    <div v-else class="relative pt-6">
                        <!-- Floating Tooltip Box -->
                        <Transition
                            enter-active-class="transition duration-150 ease-out"
                            enter-from-class="opacity-0 scale-95 -translate-y-1"
                            enter-to-class="opacity-100 scale-100 translate-y-0"
                            leave-active-class="transition duration-100 ease-in"
                            leave-from-class="opacity-100 scale-100 translate-y-0"
                            leave-to-class="opacity-0 scale-95 -translate-y-1"
                        >
                            <div
                                v-if="
                                    hoveredIndex !== null &&
                                    grafikList[hoveredIndex]
                                "
                                class="absolute -top-1 z-30 pointer-events-none transform -translate-x-1/2 whitespace-nowrap rounded-xl border border-slate-700 bg-slate-900/95 backdrop-blur-xs px-3 py-2 text-white shadow-xl transition-all duration-150"
                                :style="{
                                    left: `${Math.max(16, Math.min(84, ((hoveredIndex + 0.5) / grafikList.length) * 100))}%`,
                                }"
                            >
                                <div
                                    class="flex items-center gap-1.5 text-[11px] font-bold text-slate-200"
                                >
                                    <span>
                                        {{
                                            formatHari(
                                                grafikList[hoveredIndex].tanggal,
                                            )
                                        }},
                                        {{
                                            formatTglBulan(
                                                grafikList[hoveredIndex].tanggal,
                                            )
                                        }}
                                    </span>
                                    <span
                                        v-if="
                                            isHariIni(
                                                grafikList[hoveredIndex].tanggal,
                                            )
                                        "
                                        class="rounded bg-blue-500/30 px-1 py-0.2 text-[9px] font-semibold text-blue-300"
                                    >
                                        Hari Ini
                                    </span>
                                    <span
                                        v-if="
                                            bestDay?.tanggal ===
                                                grafikList[hoveredIndex]
                                                    .tanggal &&
                                            Number(bestDay.total) > 0
                                        "
                                        class="rounded bg-amber-500/30 px-1 py-0.2 text-[9px] font-semibold text-amber-300"
                                    >
                                        🏆 Tertinggi
                                    </span>
                                </div>
                                <div class="mt-1 flex items-baseline gap-2">
                                    <span class="text-sm font-black text-white">
                                        {{
                                            rupiah(
                                                grafikList[hoveredIndex].total,
                                            )
                                        }}
                                    </span>
                                    <span class="text-[11px] text-slate-400">
                                        ({{
                                            grafikList[hoveredIndex].transaksi
                                        }}
                                        trx)
                                    </span>
                                </div>
                                <div class="mt-0.5 text-[10px] text-slate-400">
                                    Rata-rata:
                                    {{
                                        Number(
                                            grafikList[hoveredIndex].transaksi,
                                        ) > 0
                                            ? rupiah(
                                                  Math.round(
                                                      Number(
                                                          grafikList[
                                                              hoveredIndex
                                                          ].total,
                                                      ) /
                                                          Number(
                                                              grafikList[
                                                                  hoveredIndex
                                                              ].transaksi,
                                                          ),
                                                  ),
                                              )
                                            : 'Rp 0'
                                    }}
                                    / struk
                                </div>
                                <!-- Arrow pointer -->
                                <div
                                    class="absolute left-1/2 -bottom-1 h-2 w-2 -translate-x-1/2 rotate-45 bg-slate-900 border-r border-b border-slate-700"
                                />
                            </div>
                        </Transition>

                        <!-- Chart Body with Y-Axis and Columns -->
                        <div class="flex gap-2 sm:gap-3" style="height: 220px">
                            <!-- Y-Axis Labels -->
                            <div
                                class="w-10 sm:w-12 shrink-0 flex flex-col justify-between text-right pr-1 pb-10 text-[10px] text-slate-400 font-medium select-none dark:text-slate-500"
                            >
                                <span
                                    v-for="(step, idx) in gridSteps"
                                    :key="idx"
                                >
                                    {{ formatCompact(step.val) }}
                                </span>
                            </div>

                            <!-- Main Chart Canvas (Bar or Line) -->
                            <div class="relative flex-1 h-full pb-10">
                                <!-- Subtle Horizontal Grid Lines -->
                                <div
                                    class="absolute inset-x-0 top-0 bottom-10 flex flex-col justify-between pointer-events-none"
                                >
                                    <div
                                        v-for="(step, idx) in gridSteps"
                                        :key="idx"
                                        class="w-full border-t border-dashed border-slate-200/80 dark:border-slate-800"
                                    />
                                </div>

                                <!-- View: Bar Chart -->
                                <div
                                    v-if="chartType === 'bar'"
                                    class="relative z-10 flex h-full items-end gap-1 sm:gap-2"
                                >
                                    <div
                                        v-for="(g, idx) in grafikList"
                                        :key="g.tanggal"
                                        class="group flex flex-1 flex-col items-center justify-end h-full relative cursor-pointer"
                                        @mouseenter="hoveredIndex = idx"
                                        @mouseleave="hoveredIndex = null"
                                        @touchstart="hoveredIndex = idx"
                                    >
                                        <!-- Compact value above bar -->
                                        <div
                                            class="mb-1 text-center truncate w-full"
                                        >
                                            <span
                                                :class="[
                                                    'text-[10px] sm:text-xs font-bold transition-colors',
                                                    hoveredIndex === idx
                                                        ? 'text-blue-700 dark:text-blue-400'
                                                        : 'text-slate-600 dark:text-slate-400',
                                                ]"
                                            >
                                                {{
                                                    formatCompact(
                                                        metricMode === 'omset'
                                                            ? Number(g.total)
                                                            : Number(
                                                                  g.transaksi,
                                                              ),
                                                    )
                                                }}
                                            </span>
                                        </div>

                                        <!-- Column Track & Bar -->
                                        <div
                                            class="relative flex-1 w-full max-w-12 flex items-end justify-center rounded-xl bg-slate-100/70 p-0.5 sm:p-1 border border-slate-100 transition-all duration-200 group-hover:bg-slate-100 dark:bg-slate-800/60 dark:border-slate-700/50 dark:group-hover:bg-slate-800"
                                            :class="{
                                                'ring-2 ring-blue-400/50 bg-blue-50/40 dark:ring-blue-500/50 dark:bg-blue-950/30':
                                                    hoveredIndex === idx,
                                            }"
                                        >
                                            <!-- The filled bar -->
                                            <div
                                                class="w-full rounded-lg sm:rounded-xl transition-all duration-300 ease-out flex flex-col items-center justify-start pt-1"
                                                :class="[
                                                    metricMode === 'omset'
                                                        ? 'bg-gradient-to-t from-blue-600 via-blue-500 to-indigo-500 shadow-sm shadow-blue-500/20'
                                                        : 'bg-gradient-to-t from-emerald-600 via-emerald-500 to-teal-400 shadow-sm shadow-emerald-500/20',
                                                    hoveredIndex === idx
                                                        ? 'brightness-110 scale-[1.02]'
                                                        : '',
                                                ]"
                                                :style="{
                                                    height:
                                                        Math.max(
                                                            ((metricMode ===
                                                            'omset'
                                                                ? Number(
                                                                      g.total,
                                                                  )
                                                                : Number(
                                                                      g.transaksi,
                                                                  )) /
                                                                ceilingValue) *
                                                                100,
                                                            (metricMode ===
                                                            'omset'
                                                                ? Number(
                                                                      g.total,
                                                                  )
                                                                : Number(
                                                                      g.transaksi,
                                                                  )) > 0
                                                                ? 8
                                                                : 3,
                                                        ) + '%',
                                                }"
                                            >
                                                <!-- Trophy on top of best day bar -->
                                                <Trophy
                                                    v-if="
                                                        bestDay?.tanggal ===
                                                            g.tanggal &&
                                                        Number(g.total) > 0 &&
                                                        metricMode === 'omset'
                                                    "
                                                    class="h-3 w-3 text-amber-200 drop-shadow-sm mb-0.5"
                                                />
                                            </div>
                                        </div>

                                        <!-- Day & Date Label below column -->
                                        <div
                                            class="absolute -bottom-9 left-0 right-0 flex flex-col items-center text-center"
                                        >
                                            <span
                                                :class="[
                                                    'text-[11px] leading-tight transition-colors',
                                                    isHariIni(g.tanggal)
                                                        ? 'font-bold text-blue-700 dark:text-blue-400'
                                                        : 'font-semibold text-slate-700 dark:text-slate-300',
                                                ]"
                                            >
                                                {{ formatHari(g.tanggal) }}
                                            </span>
                                            <span
                                                v-if="isHariIni(g.tanggal)"
                                                class="rounded bg-blue-100 px-1 text-[9px] font-bold text-blue-700 leading-tight dark:bg-blue-950/80 dark:text-blue-300"
                                            >
                                                Hari Ini
                                            </span>
                                            <span
                                                v-else
                                                class="text-[10px] text-slate-400 leading-tight dark:text-slate-500"
                                            >
                                                {{ formatTglBulan(g.tanggal) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- View: Smooth Area Trend Line (SVG) -->
                                <div
                                    v-else
                                    class="relative z-10 h-full w-full"
                                >
                                    <svg
                                        viewBox="0 0 700 180"
                                        class="w-full h-full overflow-visible"
                                        preserveAspectRatio="none"
                                    >
                                        <defs>
                                            <linearGradient
                                                id="trendGradient"
                                                x1="0"
                                                y1="0"
                                                x2="0"
                                                y2="1"
                                            >
                                                <stop
                                                    offset="0%"
                                                    :stop-color="
                                                        metricMode === 'omset'
                                                            ? '#3b82f6'
                                                            : '#10b981'
                                                    "
                                                    stop-opacity="0.35"
                                                />
                                                <stop
                                                    offset="100%"
                                                    :stop-color="
                                                        metricMode === 'omset'
                                                            ? '#3b82f6'
                                                            : '#10b981'
                                                    "
                                                    stop-opacity="0.0"
                                                />
                                            </linearGradient>
                                        </defs>

                                        <!-- Filled Area under curve -->
                                        <path
                                            v-if="svgData.areaPath"
                                            :d="svgData.areaPath"
                                            fill="url(#trendGradient)"
                                            class="transition-all duration-300"
                                        />

                                        <!-- The Curve Line -->
                                        <path
                                            v-if="svgData.linePath"
                                            :d="svgData.linePath"
                                            fill="none"
                                            :stroke="
                                                metricMode === 'omset'
                                                    ? '#2563eb'
                                                    : '#059669'
                                            "
                                            stroke-width="3.5"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            class="transition-all duration-300"
                                        />

                                        <!-- Interactive Points -->
                                        <g
                                            v-for="p in svgData.points"
                                            :key="p.idx"
                                            class="cursor-pointer"
                                            @mouseenter="hoveredIndex = p.idx"
                                            @mouseleave="hoveredIndex = null"
                                            @touchstart="hoveredIndex = p.idx"
                                        >
                                            <!-- Invisible hit target for easy touch -->
                                            <circle
                                                :cx="p.x"
                                                :cy="p.y"
                                                r="20"
                                                fill="transparent"
                                            />
                                            <!-- Outer halo on hover -->
                                            <circle
                                                v-if="hoveredIndex === p.idx"
                                                :cx="p.x"
                                                :cy="p.y"
                                                r="9"
                                                :fill="
                                                    metricMode === 'omset'
                                                        ? '#93c5fd'
                                                        : '#a7f3d0'
                                                "
                                                opacity="0.6"
                                            />
                                            <!-- Center dot -->
                                            <circle
                                                :cx="p.x"
                                                :cy="p.y"
                                                :r="
                                                    hoveredIndex === p.idx
                                                        ? 5.5
                                                        : 4
                                                "
                                                :fill="
                                                    metricMode === 'omset'
                                                        ? '#1d4ed8'
                                                        : '#047857'
                                                "
                                                stroke="#ffffff"
                                                stroke-width="2.5"
                                                class="transition-all duration-150"
                                            />
                                        </g>
                                    </svg>

                                    <!-- Day labels below SVG chart -->
                                    <div
                                         class="absolute -bottom-9 inset-x-0 flex justify-between px-3"
                                     >
                                         <div
                                             v-for="g in grafikList"
                                             :key="g.tanggal"
                                             class="flex flex-col items-center text-center"
                                         >
                                             <span
                                                 :class="[
                                                     'text-[11px] leading-tight',
                                                     isHariIni(g.tanggal)
                                                         ? 'font-bold text-blue-700 dark:text-blue-400'
                                                         : 'font-semibold text-slate-700 dark:text-slate-300',
                                                 ]"
                                             >
                                                 {{ formatHari(g.tanggal) }}
                                             </span>
                                             <span
                                                 v-if="isHariIni(g.tanggal)"
                                                 class="rounded bg-blue-100 px-1 text-[9px] font-bold text-blue-700 leading-tight dark:bg-blue-950/80 dark:text-blue-300"
                                             >
                                                 Hari Ini
                                             </span>
                                             <span
                                                 v-else
                                                 class="text-[10px] text-slate-400 leading-tight dark:text-slate-500"
                                             >
                                                 {{ formatTglBulan(g.tanggal) }}
                                             </span>
                                         </div>
                                     </div>
                                 </div>
                             </div>
                         </div>
                     </div>
                 </div>

                 <!-- Stok rendah -->
                 <div
                     class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                 >
                     <div class="mb-4 flex items-center gap-2">
                         <div
                             class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400"
                         >
                             <AlertTriangle class="h-4 w-4" />
                         </div>
                         <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                            Peringatan Stok Rendah (≤ 10)
                        </h2>
                    </div>
                    <div
                        v-if="
                            !data.produk_stok_rendah ||
                            data.produk_stok_rendah.length === 0
                        "
                    >
                        <p class="py-4 text-center text-sm text-slate-400 dark:text-slate-500">
                            Semua stok produk dalam kondisi aman
                        </p>
                    </div>
                     <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                         <li
                             v-for="b in data.produk_stok_rendah"
                             :key="b.id_barang"
                             class="flex items-center justify-between gap-4 py-2.5"
                         >
                             <div class="min-w-0">
                                 <p
                                     class="truncate text-sm font-semibold text-slate-800 dark:text-slate-200"
                                 >
                                     {{ b.nama }}
                                 </p>
                                 <p class="text-xs text-slate-400 dark:text-slate-500">
                                     {{ b.satuan }} · {{ b.kategori?.nama ?? '—' }}
                                 </p>
                             </div>
                             <span
                                 class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold"
                                 :class="
                                     b.stok === 0
                                         ? 'bg-red-50 text-red-600 dark:bg-red-950/60 dark:text-red-400'
                                         : b.stok <= 5
                                           ? 'bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400'
                                           : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                                 "
                             >
                                 {{ b.stok }} pcs
                             </span>
                         </li>
                     </ul>
                     <Link
                         href="/stok"
                         class="mt-4 inline-flex w-full justify-center rounded-xl border border-slate-200 bg-white py-2 text-sm font-semibold hover:border-blue-300 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-700"
                     >
                         Kelola Stok
                     </Link>
                 </div>
             </div>

                 <!-- Transaksi terbaru -->
                 <div
                     class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
                 >
                <div class="flex items-center justify-between px-4 py-3">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                        Transaksi Terbaru
                    </h2>
                    <Link
                        href="/penjualan"
                        class="text-xs font-semibold text-blue-700 hover:underline dark:text-blue-400"
                    >
                        Lihat Semua
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
                        title="Belum Ada Transaksi"
                        message="Mulai transaksi baru di menu Kasir untuk menampilkan riwayat penjualan di sini."
                    />
                </div>
                     <div v-else class="overflow-x-auto">
                         <table class="w-full min-w-160 text-left text-sm">
                             <thead>
                                 <tr
                                     class="border-y border-slate-100 bg-slate-50 text-xs text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-800/80 dark:text-slate-400"
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
                                     class="border-b border-slate-50 hover:bg-slate-50 dark:border-slate-800/60 dark:hover:bg-slate-800/50"
                                 >
                                     <td class="px-4 py-3 text-slate-400 dark:text-slate-500">
                                         {{ t.id_penjualan }}
                                     </td>
                                     <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                         {{ tanggal(t.tanggal_penjualan) }}
                                     </td>
                                     <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                         {{ t.kasir?.nama_lengkap ?? '—' }}
                                     </td>
                                     <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                         {{ t.pelanggan?.nama_pelanggan ?? 'Umum' }}
                                     </td>
                                     <td class="px-4 py-3">
                                         <span
                                             class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400"
                                             >{{ t.status_pembayaran }}</span
                                         >
                                     </td>
                                     <td class="px-4 py-3 text-right font-bold text-slate-900 dark:text-white">
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
                title="Gagal Memuat Dashboard"
                message="Silakan muat ulang halaman atau periksa koneksi internet Anda."
            />
        </div>
    </PosLayout>
</template>
