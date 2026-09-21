<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    ArrowDownUp,
    Boxes,
    Calendar,
    CheckCircle2,
    DollarSign,
    RefreshCw,
    Sparkles,
    TrendingDown,
    Truck,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import EmptyState from '@/components/pos/EmptyState.vue';
import Modal from '@/components/pos/Modal.vue';
import PageHeader from '@/components/pos/PageHeader.vue';
import Pagination from '@/components/pos/Pagination.vue';
import SearchBar from '@/components/pos/SearchBar.vue';
import PosLayout from '@/layouts/PosLayout.vue';
import { rupiah } from '@/lib/format';
import { friendlyError } from '@/services/api';
import { adjustStock, fetchBarang, fetchPrediksiStok } from '@/services/barangService';
import { usePosStore } from '@/stores/pos';
import type { Barang, PrediksiStokData, PrediksiStokItem } from '@/types/pos';

const pos = usePosStore();

// Tab navigasi
const activeTab = ref<'fisik' | 'prediksi'>('fisik');

// State Stok Fisik
const loading = ref(true);
const rows = ref<Barang[]>([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const search = ref('');
const hanyaRendah = ref(false);

const showAdjust = ref(false);
const target = ref<Barang | null>(null);
const tipe = ref<'masuk' | 'keluar' | 'opname'>('masuk');
const jumlah = ref(0);
const saving = ref(false);

let timer: ReturnType<typeof setTimeout> | null = null;

// State Prediksi Stok (Smart Restock Alert)
const prediksiLoading = ref(false);
const prediksiDays = ref(7);
const prediksiFilter = ref<'all' | 'kritis' | 'waspada' | 'aman'>('all');
const prediksiSearch = ref('');
const prediksiData = ref<PrediksiStokData | null>(null);

let prediksiSearchTimer: ReturnType<typeof setTimeout> | null = null;

const alertCount = computed(() => {
    if (!prediksiData.value) return 0;
    return (
        prediksiData.value.summary.total_kritis +
        prediksiData.value.summary.total_waspada
    );
});

async function load() {
    loading.value = true;
    try {
        const res = await fetchBarang({
            id_sekolah: pos.idSekolah,
            search: search.value || undefined,
            stok_rendah: hanyaRendah.value ? 10 : undefined,
            per_page: 12,
            page: page.value,
        });
        rows.value = res.data;
        page.value = res.current_page;
        lastPage.value = res.last_page;
        total.value = res.total;
    } catch (e) {
        toast.error(friendlyError(e, 'Data stok gagal dimuat.'));
    } finally {
        loading.value = false;
    }
}

async function loadPrediksi() {
    prediksiLoading.value = true;
    try {
        const res = await fetchPrediksiStok({
            id_sekolah: pos.idSekolah,
            days: prediksiDays.value,
            status: prediksiFilter.value,
            search: prediksiSearch.value || undefined,
        });
        prediksiData.value = res;
    } catch (e) {
        toast.error(friendlyError(e, 'Prediksi stok gagal dihitung.'));
    } finally {
        prediksiLoading.value = false;
    }
}

function onSearch() {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        page.value = 1;
        void load();
    }, 400);
}

function onPrediksiSearch() {
    if (prediksiSearchTimer) clearTimeout(prediksiSearchTimer);
    prediksiSearchTimer = setTimeout(() => {
        void loadPrediksi();
    }, 400);
}

function switchTab(tab: 'fisik' | 'prediksi') {
    activeTab.value = tab;
    if (tab === 'prediksi' && !prediksiData.value) {
        void loadPrediksi();
    }
}

function bukaAdjust(b: Barang) {
    target.value = b;
    tipe.value = 'masuk';
    jumlah.value = 0;
    showAdjust.value = true;
}

function bukaAdjustItem(item: PrediksiStokItem) {
    target.value = {
        id_barang: item.id_barang,
        id_sekolah: pos.idSekolah,
        nama: item.nama,
        satuan: item.satuan,
        stok: item.stok,
        harga_beli: item.harga_beli,
        harga_jual: item.harga_jual,
        is_active: true,
        id_kategori: item.kategori?.id_kategori ?? 0,
        id_kelompok_kategori: 0,
        id_supplier: item.supplier?.id_supplier ?? 0,
    };
    tipe.value = 'masuk';
    jumlah.value = item.rekomendasi_restock > 0 ? item.rekomendasi_restock : 10;
    showAdjust.value = true;
}

function beliKeSupplier(item: PrediksiStokItem) {
    const pQty = item.rekomendasi_restock > 0 ? item.rekomendasi_restock : 10;
    const pSup = item.supplier?.id_supplier ?? '';
    router.visit(
        `/pembelian?tambah=1&id_barang=${item.id_barang}&jumlah=${pQty}&id_supplier=${pSup}`,
    );
}

async function simpanAdjust() {
    if (!target.value) return;
    saving.value = true;
    try {
        const res = await adjustStock(target.value.id_barang, {
            tipe: tipe.value,
            jumlah: jumlah.value,
        });
        toast.success(res.message);
        showAdjust.value = false;
        await Promise.all([load(), loadPrediksi()]);
        window.dispatchEvent(new CustomEvent('pos:stock-changed'));
    } catch (e) {
        toast.error(
            friendlyError(e, 'Stok gagal diperbarui. Silakan coba lagi.'),
        );
    } finally {
        saving.value = false;
    }
}

onMounted(() => {
    void (async () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('tab') === 'prediksi') {
            activeTab.value = 'prediksi';
        }
        await pos.init();
        await Promise.all([load(), loadPrediksi()]);
    })();
});

watch(
    () => pos.idSekolah,
    () => {
        page.value = 1;
        void load();
        void loadPrediksi();
    },
);

watch(hanyaRendah, () => {
    page.value = 1;
    void load();
});

watch(prediksiDays, () => {
    void loadPrediksi();
});

watch(prediksiFilter, () => {
    void loadPrediksi();
});
</script>

<template>
    <Head title="Stok" />
    <PosLayout>
        <PageHeader
            title="Kelola Stok"
            :icon="Boxes"
            subtitle="Pemantauan stok fisik, penyesuaian opname, dan prediksi kebutuhan restock"
        />
        <!-- Tab Navigasi -->
        <div
            class="mb-5 flex flex-wrap items-center gap-2 border-b border-slate-200 pb-3 dark:border-slate-800"
        >
            <button
                type="button"
                :class="[
                    'inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold transition cursor-pointer',
                    activeTab === 'fisik'
                        ? 'bg-blue-700 text-white shadow-xs dark:bg-blue-600'
                        : 'bg-white text-slate-600 hover:bg-slate-100 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800',
                ]"
                @click="switchTab('fisik')"
            >
                <Boxes class="h-4 w-4" />
                <span>Daftar Stok Fisik</span>
            </button>
            <button
                type="button"
                :class="[
                    'relative inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold transition cursor-pointer',
                    activeTab === 'prediksi'
                        ? 'bg-gradient-to-r from-amber-600 to-orange-600 text-white shadow-xs'
                        : 'bg-white text-slate-600 hover:bg-slate-100 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800',
                ]"
                @click="switchTab('prediksi')"
            >
                <Sparkles
                    class="h-4 w-4"
                    :class="
                        activeTab === 'prediksi'
                            ? 'text-amber-200'
                            : 'text-amber-500'
                    "
                />
                <span>Prediksi Kebutuhan Restock</span>
                <span
                    v-if="alertCount > 0"
                    class="ml-1 inline-flex items-center rounded-full bg-rose-500 px-2 py-0.5 text-[10px] font-extrabold text-white animate-pulse"
                >
                    {{ alertCount }} Perlu Restock
                </span>
            </button>
        </div>

        <!-- ================= TAB 1: KELOLA STOK FISIK ================= -->
        <div v-if="activeTab === 'fisik'">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div class="flex-1">
                    <SearchBar
                        v-model="search"
                        @update:model-value="onSearch"
                    />
                </div>
                <label
                    class="inline-flex cursor-pointer items-center gap-1.5 text-sm text-slate-600 dark:text-slate-300"
                >
                    <input
                        v-model="hanyaRendah"
                        type="checkbox"
                        class="h-4 w-4 rounded accent-blue-700 cursor-pointer"
                    />
                    Hanya tampilkan stok menipis (≤ 10)
                </label>
            </div>

            <div v-if="loading" class="mt-4 space-y-2">
                <div
                    v-for="i in 5"
                    :key="i"
                    class="h-16 animate-pulse rounded-xl bg-white dark:bg-slate-900"
                />
            </div>
            <EmptyState
                v-else-if="rows.length === 0"
                class="mt-4"
                title="Belum Ada Data Stok"
            />
            <template v-else>
                <!-- Unified Responsive Card Grid -->
                <div
                    class="mt-4 grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5"
                >
                    <div
                        v-for="b in rows"
                        :key="b.id_barang"
                        class="relative flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-xs transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900 min-w-0"
                    >
                        <!-- Top: Icon, Name, Category, Status -->
                        <div>
                            <div class="flex items-start gap-3 min-w-0">
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 font-bold text-sm text-blue-700 dark:bg-blue-950/60 dark:text-blue-400"
                                >
                                    <Boxes class="h-5 w-5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <h3
                                            class="font-bold text-sm text-slate-800 truncate dark:text-slate-100"
                                        >
                                            {{ b.nama }}
                                        </h3>
                                        <span
                                            v-if="b.stok <= 0"
                                            class="shrink-0 rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-bold text-red-600 dark:bg-red-950/60 dark:text-red-400"
                                        >
                                            Habis
                                        </span>
                                        <span
                                            v-else-if="b.stok <= 10"
                                            class="shrink-0 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-600 dark:bg-amber-950/60 dark:text-amber-400"
                                        >
                                            Rendah
                                        </span>
                                        <span
                                            v-else
                                            class="shrink-0 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400"
                                        >
                                            Aman
                                        </span>
                                    </div>
                                    <p
                                        class="text-xs text-slate-400 truncate dark:text-slate-500"
                                    >
                                        {{ b.kategori?.nama ?? 'Umum' }} ·
                                        {{ b.satuan }}
                                    </p>
                                </div>
                            </div>

                            <!-- Stock Status Box -->
                            <div
                                class="mt-3 flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-slate-800/50"
                            >
                                <span
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                    >Jumlah Stok:</span
                                >
                                <span
                                    class="text-base font-extrabold"
                                    :class="
                                        b.stok <= 10
                                            ? 'text-red-600 dark:text-red-400'
                                            : 'text-slate-800 dark:text-slate-100'
                                    "
                                >
                                    {{ b.stok }}
                                    <span
                                        class="text-xs font-normal text-slate-400 dark:text-slate-500"
                                        >{{ b.satuan }}</span
                                    >
                                </span>
                            </div>
                        </div>

                        <!-- Footer: Category Badge & Action Button -->
                        <div
                            class="mt-3.5 flex items-center justify-between gap-2 border-t border-slate-100 pt-3 dark:border-slate-800/80"
                        >
                            <span
                                class="inline-block truncate rounded-lg bg-blue-50 px-2 py-1 text-[11px] font-semibold text-blue-700 dark:bg-blue-950/60 dark:text-blue-400 max-w-32"
                            >
                                {{ b.kategori?.nama ?? 'Umum' }}
                            </span>
                            <button
                                type="button"
                                class="cursor-pointer inline-flex items-center gap-1.5 rounded-lg bg-blue-700 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500 shadow-xs"
                                @click="bukaAdjust(b)"
                            >
                                <ArrowDownUp class="h-3.5 w-3.5" />
                                <span>Atur Stok</span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
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
        </div>

        <!-- ================= TAB 2: PREDIKSI STOK HABIS (SMART RESTOCK) ================= -->
        <div v-else class="space-y-5">
            <!-- 1. Statistik Ringkasan Prediksi -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Card Kritis -->
                <div
                    class="relative overflow-hidden rounded-2xl border border-red-200 bg-red-50/50 p-4 shadow-xs dark:border-red-900/50 dark:bg-red-950/20"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-red-700 dark:text-red-400">
                            Stok Kritis
                        </span>
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-xl bg-red-100 text-red-700 dark:bg-red-900/60 dark:text-red-300"
                        >
                            <AlertCircle class="h-4 w-4" />
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-black text-red-700 dark:text-red-300">
                            {{ prediksiData?.summary.total_kritis ?? 0 }}
                        </span>
                        <span class="text-xs text-red-600 dark:text-red-400">Produk</span>
                    </div>
                    <p class="mt-1 text-[11px] text-red-600/80 dark:text-red-400/80">
                        Habis atau diprediksi habis ≤ 2 hari
                    </p>
                </div>

                <!-- Card Waspada -->
                <div
                    class="relative overflow-hidden rounded-2xl border border-amber-200 bg-amber-50/50 p-4 shadow-xs dark:border-amber-900/50 dark:bg-amber-950/20"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">
                            Stok Waspada
                        </span>
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300"
                        >
                            <AlertTriangle class="h-4 w-4" />
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-black text-amber-700 dark:text-amber-300">
                            {{ prediksiData?.summary.total_waspada ?? 0 }}
                        </span>
                        <span class="text-xs text-amber-600 dark:text-amber-400">Produk</span>
                    </div>
                    <p class="mt-1 text-[11px] text-amber-600/80 dark:text-amber-400/80">
                        Diprediksi habis dalam 3–5 hari
                    </p>
                </div>

                <!-- Card Aman -->
                <div
                    class="relative overflow-hidden rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 shadow-xs dark:border-emerald-900/50 dark:bg-emerald-950/20"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                            Stok Aman
                        </span>
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300"
                        >
                            <CheckCircle2 class="h-4 w-4" />
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-black text-emerald-700 dark:text-emerald-300">
                            {{ prediksiData?.summary.total_aman ?? 0 }}
                        </span>
                        <span class="text-xs text-emerald-600 dark:text-emerald-400">Produk</span>
                    </div>
                    <p class="mt-1 text-[11px] text-emerald-600/80 dark:text-emerald-400/80">
                        Stok mencukupi > 5 hari / statis
                    </p>
                </div>

                <!-- Card Estimasi Anggaran Restock -->
                <div
                    class="relative overflow-hidden rounded-2xl border border-blue-200 bg-blue-50/50 p-4 shadow-xs dark:border-blue-900/50 dark:bg-blue-950/20"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-blue-700 dark:text-blue-400">
                            Estimasi Anggaran Restock
                        </span>
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-100 text-blue-700 dark:bg-blue-900/60 dark:text-blue-300"
                        >
                            <DollarSign class="h-4 w-4" />
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-xl font-black text-blue-800 dark:text-blue-300">
                            {{ rupiah(prediksiData?.summary.estimasi_anggaran_restock ?? 0) }}
                        </span>
                    </div>
                    <p class="mt-1 text-[11px] text-blue-600/80 dark:text-blue-400/80">
                        Kebutuhan buffer 14 hari ke depan
                    </p>
                </div>
            </div>

            <!-- 2. Callout Penjelasan Algoritma -->
            <div
                class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50/60 p-4 dark:border-amber-900/40 dark:bg-amber-950/20"
            >
                <Sparkles class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                <div class="text-xs leading-relaxed text-slate-700 dark:text-slate-300">
                    <p class="font-bold text-slate-900 dark:text-slate-100">
                        Smart Restock Engine:
                    </p>
                    <p class="mt-0.5">
                        Menghitung kecepatan penjualan harian (<em>velocity burn rate</em>) dari riwayat transaksi kasir
                        dalam rentang waktu yang Anda pilih. Sistem memproyeksikan sisa hari hingga stok habis dan
                        menghitung otomatis rekomendasi kuantiti belanja yang siap dipesan langsung ke supplier.
                    </p>
                </div>
            </div>

            <!-- 3. Filter Bar (Search, Status Filter, Days Selector) -->
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <!-- Search & Status Pills -->
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div class="w-full sm:w-64">
                        <SearchBar
                            v-model="prediksiSearch"
                            placeholder="Cari produk / barcode…"
                            @update:model-value="onPrediksiSearch"
                        />
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button
                            v-for="s in [
                                { key: 'all', label: 'Semua' },
                                { key: 'kritis', label: '🔴 Kritis' },
                                { key: 'waspada', label: '🟡 Waspada' },
                                { key: 'aman', label: '🟢 Aman' },
                            ]"
                            :key="s.key"
                            type="button"
                            :class="[
                                'rounded-xl px-3 py-1.5 text-xs font-bold transition cursor-pointer',
                                prediksiFilter === s.key
                                    ? 'bg-slate-900 text-white shadow-xs dark:bg-slate-100 dark:text-slate-900'
                                    : 'bg-white text-slate-600 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700',
                            ]"
                            @click="prediksiFilter = s.key as any"
                        >
                            {{ s.label }}
                        </button>
                    </div>
                </div>

                <!-- Days Selector & Refresh Button -->
                <div class="flex items-center gap-2 self-end md:self-auto">
                    <span class="text-xs font-medium text-slate-500 dark:text-slate-400">
                        Periode Analisis:
                    </span>
                    <select
                        v-model.number="prediksiDays"
                        class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 cursor-pointer"
                    >
                        <option :value="7">7 Hari Terakhir (Default)</option>
                        <option :value="14">14 Hari Terakhir</option>
                        <option :value="30">30 Hari Terakhir</option>
                    </select>
                    <button
                        type="button"
                        title="Muat Ulang Analisis"
                        class="rounded-xl border border-slate-200 bg-white p-2 text-slate-500 hover:bg-slate-50 hover:text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 transition cursor-pointer"
                        :disabled="prediksiLoading"
                        @click="loadPrediksi"
                    >
                        <RefreshCw
                            class="h-4 w-4"
                            :class="{ 'animate-spin': prediksiLoading }"
                        />
                    </button>
                </div>
            </div>

            <!-- 4. Loading State -->
            <div v-if="prediksiLoading" class="space-y-3">
                <div
                    v-for="i in 5"
                    :key="i"
                    class="h-16 animate-pulse rounded-2xl bg-white dark:bg-slate-900"
                />
            </div>

            <!-- 5. Empty State -->
            <EmptyState
                v-else-if="!prediksiData || prediksiData.items.length === 0"
                title="Tidak ada produk ditemukan"
                message="Semua produk dalam status aman atau tidak sesuai dengan kata kunci filter."
            />

            <!-- 6. Table / List Card View -->
            <template v-else>
                <!-- Desktop & Tablet Table View -->
                <div
                    class="hidden md:block overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-700 dark:text-slate-200">
                            <thead
                                class="border-b border-slate-200 bg-slate-50/80 text-[11px] uppercase tracking-wider font-bold text-slate-500 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-400"
                            >
                                <tr>
                                    <th class="px-4 py-3.5">Produk</th>
                                    <th class="px-4 py-3.5">Sisa Stok</th>
                                    <th class="px-4 py-3.5">Kecepatan Penjualan</th>
                                    <th class="px-4 py-3.5">Estimasi Habis</th>
                                    <th class="px-4 py-3.5">Rekomendasi Restock</th>
                                    <th class="px-4 py-3.5">Supplier</th>
                                    <th class="px-4 py-3.5 text-right">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                                <tr
                                    v-for="item in prediksiData.items"
                                    :key="item.id_barang"
                                    class="transition hover:bg-slate-50/60 dark:hover:bg-slate-800/40"
                                >
                                    <!-- Kolom Produk -->
                                    <td class="px-4 py-3.5">
                                        <div class="font-bold text-slate-900 dark:text-slate-100">
                                            {{ item.nama }}
                                        </div>
                                        <div class="mt-0.5 text-[11px] text-slate-400 dark:text-slate-500">
                                            {{ item.kategori?.nama ?? 'Umum' }}
                                            <span v-if="item.barcode"> · {{ item.barcode }}</span>
                                        </div>
                                    </td>

                                    <!-- Kolom Sisa Stok -->
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="font-extrabold text-sm"
                                                :class="
                                                    item.stok <= 0
                                                        ? 'text-red-600 dark:text-red-400'
                                                        : item.stok <= 10
                                                        ? 'text-amber-600 dark:text-amber-400'
                                                        : 'text-slate-800 dark:text-slate-100'
                                                "
                                            >
                                                {{ item.stok }}
                                            </span>
                                            <span class="text-[11px] text-slate-400">
                                                {{ item.satuan }}
                                            </span>
                                        </div>
                                        <span
                                            v-if="item.status === 'kritis'"
                                            class="mt-1 inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-bold text-red-700 dark:bg-red-950/60 dark:text-red-300"
                                        >
                                            <AlertCircle class="h-3 w-3" />
                                            {{ item.stok <= 0 ? 'Habis' : 'Kritis' }}
                                        </span>
                                        <span
                                            v-else-if="item.status === 'waspada'"
                                            class="mt-1 inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-950/60 dark:text-amber-300"
                                        >
                                            <AlertTriangle class="h-3 w-3" />
                                            Waspada
                                        </span>
                                        <span
                                            v-else
                                            class="mt-1 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300"
                                        >
                                            <CheckCircle2 class="h-3 w-3" />
                                            Aman
                                        </span>
                                    </td>

                                    <!-- Kolom Kecepatan Penjualan -->
                                    <td class="px-4 py-3.5">
                                        <div class="font-semibold text-slate-800 dark:text-slate-200">
                                            {{ item.total_terjual }} {{ item.satuan }} terjual
                                        </div>
                                        <div class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                            Laju: <strong class="font-bold">{{ item.laju_harian }}</strong> {{ item.satuan }}/hari
                                        </div>
                                    </td>

                                    <!-- Kolom Estimasi Habis -->
                                    <td class="px-4 py-3.5">
                                        <div
                                            v-if="item.stok <= 0"
                                            class="font-black text-red-600 dark:text-red-400"
                                        >
                                            Sudah Habis!
                                        </div>
                                        <div
                                            v-else-if="item.estimasi_hari_habis !== null && item.estimasi_hari_habis <= 2"
                                            class="font-black text-rose-600 dark:text-rose-400"
                                        >
                                            ~{{ item.estimasi_hari_habis }} Hari Lagi
                                        </div>
                                        <div
                                            v-else-if="item.estimasi_hari_habis !== null && item.estimasi_hari_habis <= 5"
                                            class="font-bold text-amber-600 dark:text-amber-400"
                                        >
                                            ~{{ item.estimasi_hari_habis }} Hari Lagi
                                        </div>
                                        <div
                                            v-else-if="item.estimasi_hari_habis !== null"
                                            class="font-semibold text-emerald-600 dark:text-emerald-400"
                                        >
                                            ~{{ item.estimasi_hari_habis }} Hari
                                        </div>
                                        <div
                                            v-else
                                            class="text-[11px] text-slate-400 dark:text-slate-500"
                                        >
                                            Statis / Tanpa Penjualan
                                        </div>
                                    </td>

                                    <!-- Kolom Rekomendasi Restock -->
                                    <td class="px-4 py-3.5">
                                        <div
                                            v-if="item.rekomendasi_restock > 0"
                                            class="space-y-0.5"
                                        >
                                            <span class="inline-flex items-center gap-1 rounded-lg bg-blue-50 px-2 py-0.5 text-xs font-black text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">
                                                +{{ item.rekomendasi_restock }} {{ item.satuan }}
                                            </span>
                                            <div class="text-[10px] text-slate-400">
                                                Estimasi modal: {{ rupiah(item.rekomendasi_restock * item.harga_beli) }}
                                            </div>
                                        </div>
                                        <div
                                            v-else
                                            class="text-[11px] text-slate-400 dark:text-slate-500"
                                        >
                                            Mencukupi
                                        </div>
                                    </td>

                                    <!-- Kolom Supplier -->
                                    <td class="px-4 py-3.5">
                                        <div class="font-medium text-slate-800 dark:text-slate-200">
                                            {{ item.supplier?.nama ?? '-' }}
                                        </div>
                                        <div
                                            v-if="item.supplier?.no_telepon"
                                            class="text-[10px] text-slate-400"
                                        >
                                            {{ item.supplier.no_telepon }}
                                        </div>
                                    </td>

                                    <!-- Kolom Tindakan -->
                                    <td class="px-4 py-3.5 text-right">
                                        <div class="inline-flex items-center gap-1.5">
                                            <button
                                                type="button"
                                                title="Pesan restock langsung ke supplier"
                                                class="cursor-pointer inline-flex items-center gap-1 rounded-lg bg-orange-600 px-2.5 py-1.5 text-xs font-bold text-white shadow-xs transition hover:bg-orange-700"
                                                @click="beliKeSupplier(item)"
                                            >
                                                <Truck class="h-3.5 w-3.5" />
                                                <span>+ Restock</span>
                                            </button>
                                            <button
                                                type="button"
                                                title="Sesuaikan stok manual"
                                                class="cursor-pointer inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 transition"
                                                @click="bukaAdjustItem(item)"
                                            >
                                                <ArrowDownUp class="h-3.5 w-3.5" />
                                                <span>Atur</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Mobile Card View -->
                <div class="grid grid-cols-1 gap-3 md:hidden">
                    <div
                        v-for="item in prediksiData.items"
                        :key="item.id_barang"
                        class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h4 class="font-bold text-sm text-slate-900 dark:text-white">
                                    {{ item.nama }}
                                </h4>
                                <p class="text-xs text-slate-400">
                                    {{ item.kategori?.nama ?? 'Umum' }}
                                    <span v-if="item.barcode"> · {{ item.barcode }}</span>
                                </p>
                            </div>
                            <span
                                v-if="item.status === 'kritis'"
                                class="shrink-0 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-700 dark:bg-red-950/60 dark:text-red-300"
                            >
                                {{ item.stok <= 0 ? 'Habis' : 'Kritis (≤ 2 Hari)' }}
                            </span>
                            <span
                                v-else-if="item.status === 'waspada'"
                                class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-950/60 dark:text-amber-300"
                            >
                                Waspada (3–5 Hari)
                            </span>
                            <span
                                v-else
                                class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300"
                            >
                                Aman
                            </span>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-slate-50 p-2.5 text-xs dark:bg-slate-800/60">
                            <div>
                                <span class="text-[10px] text-slate-400">Sisa Stok:</span>
                                <p class="font-extrabold text-slate-800 dark:text-slate-100">
                                    {{ item.stok }} {{ item.satuan }}
                                </p>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400">Estimasi Habis:</span>
                                <p
                                    class="font-extrabold"
                                    :class="
                                        item.status === 'kritis'
                                            ? 'text-red-600 dark:text-red-400'
                                            : item.status === 'waspada'
                                            ? 'text-amber-600 dark:text-amber-400'
                                            : 'text-emerald-600 dark:text-emerald-400'
                                    "
                                >
                                    {{
                                        item.stok <= 0
                                            ? 'Habis!'
                                            : item.estimasi_hari_habis !== null
                                            ? `~${item.estimasi_hari_habis} Hari`
                                            : 'Statis'
                                    }}
                                </p>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400">Laju Penjualan:</span>
                                <p class="font-semibold text-slate-700 dark:text-slate-300">
                                    {{ item.laju_harian }} {{ item.satuan }}/hari
                                </p>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400">Rekomendasi Restock:</span>
                                <p class="font-bold text-blue-600 dark:text-blue-400">
                                    {{ item.rekomendasi_restock > 0 ? `+${item.rekomendasi_restock} ${item.satuan}` : 'Cukup' }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 flex items-center justify-between gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                            <span class="text-[11px] text-slate-500 truncate max-w-36">
                                {{ item.supplier?.nama ?? 'Belum ada supplier' }}
                            </span>
                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    class="cursor-pointer inline-flex items-center gap-1 rounded-lg bg-orange-600 px-2.5 py-1.5 text-xs font-bold text-white shadow-xs hover:bg-orange-700"
                                    @click="beliKeSupplier(item)"
                                >
                                    <Truck class="h-3.5 w-3.5" />
                                    <span>+ Restock</span>
                                </button>
                                <button
                                    type="button"
                                    class="cursor-pointer rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                    @click="bukaAdjustItem(item)"
                                >
                                    <ArrowDownUp class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>


        <Modal
            :open="showAdjust"
            :title="`Atur Stok — ${target?.nama ?? ''}`"
            @close="showAdjust = false"
        >
            <form class="space-y-3" @submit.prevent="simpanAdjust">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Stok saat ini:
                    <strong class="text-slate-900 dark:text-white"
                        >{{ target?.stok }} {{ target?.satuan }}</strong
                    >
                </p>
                <div class="grid grid-cols-3 gap-1.5">
                    <button
                        v-for="t in ['masuk', 'keluar', 'opname'] as const"
                        :key="t"
                        type="button"
                        :class="[
                            'cursor-pointer rounded-xl border px-4 py-3 text-sm font-semibold capitalize transition',
                            tipe === t
                                ? 'border-blue-700 bg-blue-700 text-white shadow-xs dark:border-blue-600 dark:bg-blue-600'
                                : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800',
                        ]"
                        @click="tipe = t"
                    >
                        {{ t }}
                    </button>
                </div>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                    >Jumlah
                    <input
                        v-model.number="jumlah"
                        type="number"
                        min="0"
                        required
                        class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    />
                </label>
                <div class="flex gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button
                        type="button"
                        class="flex-1 cursor-pointer rounded-xl border border-slate-200 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 transition"
                        @click="showAdjust = false"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="flex-1 cursor-pointer rounded-xl bg-blue-700 py-2.5 text-sm font-bold text-white hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500 transition disabled:opacity-60 shadow-xs"
                    >
                        {{ saving ? 'Menyimpan…' : 'Simpan' }}
                    </button>
                </div>
            </form>
        </Modal>
    </PosLayout>
</template>
