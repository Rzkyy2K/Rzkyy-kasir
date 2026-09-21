<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    AlertCircle,
    Clock,
    LayoutGrid,
    List,
    PauseCircle,
    Printer,
    RotateCcw,
    ScanBarcode,
    ShoppingCart,
    Trash2,
    Volume2,
    VolumeX,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import BarcodeScannerModal from '@/components/pos/BarcodeScannerModal.vue';
import PageHeader from '@/components/pos/PageHeader.vue';
import CartPanel from '@/components/pos/CartPanel.vue';
import CategoryFilter from '@/components/pos/CategoryFilter.vue';
import EmptyState from '@/components/pos/EmptyState.vue';
import LoadingSkeleton from '@/components/pos/LoadingSkeleton.vue';
import Modal from '@/components/pos/Modal.vue';
import ProductCard from '@/components/pos/ProductCard.vue';
import ProductListRow from '@/components/pos/ProductListRow.vue';
import SearchBar from '@/components/pos/SearchBar.vue';
import PosLayout from '@/layouts/PosLayout.vue';
import { playBeep, playErrorBeep } from '@/lib/beep';
import { rupiah, tanggal } from '@/lib/format';
import { speakPaymentSuccess } from '@/lib/voice';
import { friendlyError } from '@/services/api';
import { fetchBarang, fetchBarangByBarcode } from '@/services/barangService';
import { fetchPelanggan } from '@/services/pelangganService';
import { createPenjualan } from '@/services/penjualanService';
import { useCartStore } from '@/stores/cart';
import { useCatalogStore } from '@/stores/catalog';
import { usePosStore } from '@/stores/pos';
import type { Barang, Pelanggan, Penjualan } from '@/types/pos';

const pos = usePosStore();
const cart = useCartStore();
const catalog = useCatalogStore();

const loading = ref(true);
const search = ref('');
const idKelompok = ref<number | null>(null);
const cartOpen = ref(false); // bottom sheet mobile
const bayarLoading = ref(false);
const struk = ref<Penjualan | null>(null);
const pelangganList = ref<Pelanggan[]>([]);
const holdModalOpen = ref(false);
const heldModalOpen = ref(false);
const holdNote = ref('');
const restoreConfirmId = ref<string | null>(null);
const scannerOpen = ref(false);

const viewMode = ref<'grid' | 'list'>('grid');
const voiceEnabled = ref(true);

function getCartQty(idBarang: number): number {
    const item = cart.items.find((i) => i.id_barang === idBarang);
    return item ? item.qty : 0;
}

function toggleVoice() {
    voiceEnabled.value = !voiceEnabled.value;
    try {
        localStorage.setItem('pos_voice_enabled', String(voiceEnabled.value));
    } catch {}
    if (voiceEnabled.value) {
        toast.success('Suara kasir diaktifkan (menyebut nominal kembalian)');
    } else {
        toast.info('Suara kasir dinonaktifkan (mode senyap)');
    }
}

watch(viewMode, (val) => {
    try {
        localStorage.setItem('pos_view_mode', val);
    } catch {}
});

let timer: ReturnType<typeof setTimeout> | null = null;
const hasil = ref<Barang[]>([]);

const kategoriOptions = computed(() => [
    { value: null as number | null, label: 'Semua' },
    ...catalog.kelompok.map((k) => ({
        value: k.id_kelompok as number | null,
        label: k.nama_kelompok,
    })),
]);

async function cari() {
    loading.value = true;
    try {
        const res = await fetchBarang({
            id_sekolah: pos.idSekolah,
            search: search.value || undefined,
            id_kelompok_kategori: idKelompok.value ?? undefined,
            is_active: true,
            per_page: 60,
        });
        hasil.value = res.data;
    } catch (e) {
        toast.error(
            friendlyError(e, 'Produk gagal dimuat. Silakan coba lagi.'),
        );
    } finally {
        loading.value = false;
    }
}

function onSearchInput() {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => void cari(), 400);
}

async function bayar(payload: { nominal: number; cara: string }) {
    if (!pos.idUser) {
        toast.error('Pilih pengguna kasir terlebih dahulu di header.');
        return;
    }
    if (payload.nominal < cart.total) {
        toast.error('Nominal pembayaran kurang dari total belanja.');
        return;
    }
    bayarLoading.value = true;
    try {
        const res = await createPenjualan({
            id_sekolah: pos.idSekolah,
            id_user: pos.idUser,
            id_pelanggan: cart.idPelanggan,
            total_bayar: payload.nominal,
            jenis_transaksi: 'tunai',
            cara_bayar: payload.cara,
            items: cart.items.map((i) => ({
                id_barang: i.id_barang,
                jumlah_barang: i.qty,
                diskon_tipe: cart.diskonPersen > 0 ? 1 : 0,
                diskon_nilai: cart.diskonPersen,
                diskon_nominal: cart.getItemDiscount(i),
            })),
        });
        struk.value = res.data;
        cart.clear();
        cartOpen.value = false;
        toast.success(res.message || 'Transaksi berhasil disimpan.');

        if (voiceEnabled.value && res.data) {
            const total = Number(res.data.total_faktur ?? 0);
            const kembalian = Number(res.data.kembalian ?? 0);
            speakPaymentSuccess(total, kembalian, payload.cara);
        }

        await cari();
        window.dispatchEvent(new CustomEvent('pos:stock-changed'));
    } catch (e) {
        toast.error(
            friendlyError(e, 'Transaksi gagal disimpan. Silakan coba lagi.'),
        );
    } finally {
        bayarLoading.value = false;
    }
}

function totalDiskonStruk(s: Penjualan | null): number {
    if (!s || !s.detail) return 0;
    return s.detail.reduce(
        (acc, d) => acc + Number(d.diskon_nominal || 0),
        0,
    );
}

function cetak() {
    window.print();
}

function openHold() {
    if (cart.count === 0) return;
    const currentPelanggan = pelangganList.value.find(
        (p) => p.id_pelanggan === cart.idPelanggan,
    );
    holdNote.value = currentPelanggan
        ? `Pelanggan: ${currentPelanggan.nama_pelanggan}`
        : '';
    holdModalOpen.value = true;
}

function confirmHold() {
    const currentPelanggan = pelangganList.value.find(
        (p) => p.id_pelanggan === cart.idPelanggan,
    );
    const held = cart.holdCurrentCart(
        holdNote.value,
        currentPelanggan?.nama_pelanggan,
    );
    if (held) {
        toast.success(`Transaksi "${held.catatan}" berhasil ditahan.`);
        holdModalOpen.value = false;
        holdNote.value = '';
        cartOpen.value = false;
    }
}

function promptRestore(id: string) {
    if (cart.count > 0) {
        restoreConfirmId.value = id;
    } else {
        executeRestore(id, false);
    }
}

function executeRestore(id: string, holdCurrentFirst = false) {
    if (holdCurrentFirst && cart.count > 0) {
        const currentPelanggan = pelangganList.value.find(
            (p) => p.id_pelanggan === cart.idPelanggan,
        );
        cart.holdCurrentCart(undefined, currentPelanggan?.nama_pelanggan);
    }
    const restored = cart.restoreHeld(id);
    if (restored) {
        toast.success(`Transaksi "${restored.catatan}" berhasil dipulihkan.`);
        restoreConfirmId.value = null;
        heldModalOpen.value = false;
    }
}

function deleteHeld(id: string, catatan: string) {
    cart.removeHeld(id);
    toast.info(`Transaksi "${catatan}" telah dihapus.`);
    if (restoreConfirmId.value === id) {
        restoreConfirmId.value = null;
    }
}

function formatWaktu(ts: number): string {
    const diff = Math.floor((Date.now() - ts) / 1000);
    const jam = new Date(ts).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    });
    if (diff < 60) return `${jam} (Baru saja)`;
    const m = Math.floor(diff / 60);
    if (m < 60) return `${jam} (${m} menit lalu)`;
    const h = Math.floor(m / 60);
    return `${jam} (${h} jam lalu)`;
}

async function handleBarcodeScan(barcode: string) {
    const code = barcode.trim();
    if (!code) return;

    try {
        const item = await fetchBarangByBarcode(code, pos.idSekolah);
        if (item) {
            if (item.stok <= 0) {
                playErrorBeep();
                toast.error(`Stok "${item.nama}" habis (${item.stok} ${item.satuan ?? 'pcs'}).`);
                return;
            }
            const existing = cart.items.find((i) => i.id_barang === item.id_barang);
            if (existing && existing.qty >= item.stok) {
                playErrorBeep();
                toast.error(`Jumlah "${item.nama}" melebihi stok yang tersedia (${item.stok}).`);
                return;
            }

            cart.add(item);
            playBeep();
            toast.success(`+1 ${item.nama}`);
        } else {
            playErrorBeep();
            toast.error(`Barang dengan barcode "${code}" tidak ditemukan. Silakan cari manual.`);
        }
    } catch {
        playErrorBeep();
        toast.error(`Barang barcode "${code}" belum terdaftar di sekolah ini.`);
    }
}

// Global keydown listener untuk Barcode Scanner Gun (USB / Bluetooth)
let barcodeBuffer = '';
let lastKeypressTime = 0;

function handleGlobalKeydown(e: KeyboardEvent) {
    // Abaikan jika modal scanner kamera atau modal hold/bayar sedang terbuka
    if (scannerOpen.value || holdModalOpen.value || heldModalOpen.value || struk.value) {
        return;
    }

    const activeEl = document.activeElement as HTMLElement | null;
    const isInsideInput = activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.tagName === 'SELECT');

    const now = Date.now();
    // Barcode gun mengetik sangat cepat (selang < 80ms per karakter)
    if (now - lastKeypressTime > 80) {
        barcodeBuffer = '';
    }
    lastKeypressTime = now;

    if (e.key === 'Enter') {
        if (barcodeBuffer.length >= 3) {
            e.preventDefault();
            const scanned = barcodeBuffer.trim();
            barcodeBuffer = '';
            void handleBarcodeScan(scanned);
        }
    } else if (e.key.length === 1 && !isInsideInput) {
        barcodeBuffer += e.key;
    }
}

onMounted(() => {
    try {
        const savedView = localStorage.getItem('pos_view_mode');
        if (savedView === 'grid' || savedView === 'list') {
            viewMode.value = savedView;
        }
        const savedVoice = localStorage.getItem('pos_voice_enabled');
        if (savedVoice !== null) {
            voiceEnabled.value = savedVoice !== 'false';
        }
    } catch {}

    window.addEventListener('keydown', handleGlobalKeydown);
    void (async () => {
        await pos.init();
        await cari();
        try {
            pelangganList.value = (
                await fetchPelanggan({ per_page: 100 })
            ).data;
        } catch {
            /* pelanggan opsional */
        }
    })();
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
});

watch([() => pos.idSekolah, idKelompok], () => void cari());
</script>

<template>
    <Head title="Kasir" />
    <PosLayout>
        <PageHeader
            title="Kasir"
            subtitle="Layanan transaksi penjualan cepat dan cetak struk kasir"
            :icon="ShoppingCart"
        />
        <div class="gap-4 xl:grid xl:grid-cols-[1fr_360px]">
            <!-- Area produk -->
            <div class="min-w-0">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="flex-1">
                        <SearchBar
                            v-model="search"
                            @update:model-value="onSearchInput"
                        />
                    </div>
                    <button
                        type="button"
                        class="inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                        title="Buka pemindai barcode kamera"
                        @click="scannerOpen = true"
                    >
                        <ScanBarcode class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                        <span class="hidden sm:inline">Pindai Barcode</span>
                    </button>
                    <select
                        v-model="cart.idPelanggan"
                        class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 shadow-xs dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200"
                    >
                        <option :value="null">Pelanggan Umum</option>
                        <option
                            v-for="p in pelangganList"
                            :key="p.id_pelanggan"
                            :value="p.id_pelanggan"
                        >
                            {{ p.nama_pelanggan }}
                        </option>
                    </select>
                    <!-- Tombol Cepat Buka Transaksi Tertahan di Bar Atas -->
                    <button
                        v-if="cart.heldCount > 0"
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-3.5 py-2.5 text-xs font-bold text-amber-800 shadow-sm transition hover:bg-amber-100 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-300 dark:hover:bg-amber-900/50"
                        @click="heldModalOpen = true"
                    >
                        <Clock class="h-4 w-4 text-amber-600 dark:text-amber-400 animate-pulse" />
                        <span>{{ cart.heldCount }} Transaksi Tertahan</span>
                    </button>
                </div>
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0 flex-1">
                        <CategoryFilter
                            v-model="idKelompok"
                            :options="kategoriOptions"
                        />
                    </div>

                    <!-- Toolbar Tombol: Suara Kasir & Toggle Tampilan Grid/List -->
                    <div class="flex shrink-0 items-center gap-2">
                        <!-- Toggle Suara Kasir Bicara -->
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-semibold shadow-xs transition"
                            :class="[
                                voiceEnabled
                                    ? 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 dark:border-blue-800 dark:bg-blue-950/50 dark:text-blue-300 dark:hover:bg-blue-900/50'
                                    : 'border-slate-200 bg-white text-slate-400 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-500 dark:hover:bg-slate-800',
                            ]"
                            :title="
                                voiceEnabled
                                    ? 'Suara kasir aktif (kembalian diucapkan). Klik untuk senyap'
                                    : 'Suara kasir senyap. Klik untuk aktifkan'
                            "
                            @click="toggleVoice"
                        >
                            <Volume2
                                v-if="voiceEnabled"
                                class="h-4 w-4 text-blue-600 dark:text-blue-400"
                            />
                            <VolumeX v-else class="h-4 w-4 text-slate-400 dark:text-slate-500" />
                            <span class="hidden sm:inline">
                                {{ voiceEnabled ? 'Suara ON' : 'Mute' }}
                            </span>
                        </button>

                        <!-- Toggle Grid vs List Mode -->
                        <div
                            class="inline-flex rounded-xl border border-slate-200 bg-slate-100 p-0.5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
                        >
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition"
                                :class="[
                                    viewMode === 'grid'
                                        ? 'bg-white text-blue-700 shadow-xs dark:bg-slate-800 dark:text-blue-400'
                                        : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200',
                                ]"
                                title="Tampilan Kartu (Grid)"
                                @click="viewMode = 'grid'"
                            >
                                <LayoutGrid class="h-4 w-4" />
                                <span class="hidden md:inline">Grid</span>
                            </button>
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition"
                                :class="[
                                    viewMode === 'list'
                                        ? 'bg-white text-blue-700 shadow-xs dark:bg-slate-800 dark:text-blue-400'
                                        : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200',
                                ]"
                                title="Tampilan Daftar (List)"
                                @click="viewMode = 'list'"
                            >
                                <List class="h-4 w-4" />
                                <span class="hidden md:inline">List</span>
                            </button>
                        </div>
                    </div>
                </div>

                <LoadingSkeleton v-if="loading" class="mt-4" />
                <EmptyState
                    v-else-if="hasil.length === 0"
                    class="mt-4"
                    title="Belum Ada Produk"
                    message="Tambahkan produk melalui menu Produk atau gunakan kata kunci pencarian lain."
                />
                
                <!-- Grid Cards Mode -->
                <div
                    v-else-if="viewMode === 'grid'"
                    class="mt-4 grid grid-cols-2 gap-3.5 sm:grid-cols-3 xl:grid-cols-3 2xl:grid-cols-4"
                >
                    <ProductCard
                        v-for="b in hasil"
                        :key="b.id_barang"
                        :item="b"
                        :cart-qty="getCartQty(b.id_barang)"
                        @add="cart.add($event)"
                    />
                </div>

                <!-- List View Mode (Compact Table/Rows) -->
                <div
                    v-else
                    class="mt-4 flex flex-col gap-2"
                >
                    <ProductListRow
                        v-for="b in hasil"
                        :key="b.id_barang"
                        :item="b"
                        :cart-qty="getCartQty(b.id_barang)"
                        @add="cart.add($event)"
                    />
                </div>
            </div>

            <!-- Keranjang desktop -->
            <div class="hidden xl:block">
                <div class="sticky top-20 max-h-[calc(100vh-7rem)]">
                    <CartPanel
                        :bayar-loading="bayarLoading"
                        @bayar="bayar"
                        @hold="openHold"
                        @show-held="heldModalOpen = true"
                    />
                </div>
            </div>
        </div>

        <!-- Floating cart (mobile/tablet) -->
        <button
            v-if="cart.count > 0"
            type="button"
            class="fixed right-4 bottom-20 z-40 flex items-center gap-1.5 rounded-full bg-blue-700 px-5 py-3.5 text-sm font-bold text-white shadow-xl xl:hidden active-press tabular-nums"
            @click="cartOpen = true"
        >
            <ShoppingCart class="h-5 w-5" />
            {{ cart.count }} item · {{ rupiah(cart.total) }}
        </button>

        <!-- Bottom sheet keranjang -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="cartOpen" class="fixed inset-0 z-50 xl:hidden">
                    <div
                        class="absolute inset-0 bg-slate-900/60 backdrop-blur-xs"
                        @click="cartOpen = false"
                    />
                    <div
                        class="absolute inset-x-0 bottom-0 max-h-[92vh] overflow-y-auto rounded-t-3xl bg-slate-100 p-3 shadow-2xl dark:bg-slate-900 dark:text-slate-100 border-t border-transparent dark:border-slate-800 animate-in-slide-up"
                    >
                        <!-- Drag handle bar -->
                        <div class="mx-auto mb-2.5 h-1.5 w-12 rounded-full bg-slate-300 dark:bg-slate-700" />

                        <div class="mb-2 flex items-center justify-between px-1">
                            <p class="text-sm font-bold text-slate-800 dark:text-white">Keranjang Belanja</p>
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-200 active-press dark:text-slate-400 dark:hover:bg-slate-800"
                                @click="cartOpen = false"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>
                        <CartPanel
                            :bayar-loading="bayarLoading"
                            @bayar="bayar"
                            @hold="openHold"
                            @show-held="heldModalOpen = true"
                        />
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Struk -->
        <Modal
            :open="struk !== null"
            title="Transaksi Berhasil"
            @close="struk = null"
        >
            <div v-if="struk" id="struk-print" class="text-sm text-slate-700 dark:text-slate-200">
                <div class="text-center">
                    <p class="text-base font-black text-slate-900 dark:text-white">Scholify</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ pos.sekolahAktif?.nama_sekolah }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ tanggal(struk.tanggal_penjualan) }}
                    </p>
                </div>
                <hr class="my-3 border-dashed border-slate-200 dark:border-slate-700" />
                <div
                    v-for="d in struk.detail ?? []"
                    :key="d.id_detail_penjualan"
                    class="py-0.5"
                >
                    <div class="flex justify-between">
                        <span>{{ d.barang?.nama }} × {{ d.jumlah_barang }}</span>
                        <span class="font-semibold text-slate-900 dark:text-white">{{ rupiah(d.subtotal) }}</span>
                    </div>
                    <div
                        v-if="Number(d.diskon_nominal) > 0"
                        class="flex justify-between text-[11px] text-rose-600 dark:text-rose-400"
                    >
                        <span class="pl-2">Diskon ({{ Number(d.diskon_nilai) }}%)</span>
                        <span>-{{ rupiah(d.diskon_nominal) }}</span>
                    </div>
                </div>
                <hr class="my-3 border-dashed border-slate-200 dark:border-slate-700" />
                <div
                    v-if="totalDiskonStruk(struk) > 0"
                    class="flex justify-between text-xs text-slate-500 dark:text-slate-400"
                >
                    <span>Subtotal</span>
                    <span>{{
                        rupiah(
                            Number(struk.total_faktur) +
                                totalDiskonStruk(struk),
                        )
                    }}</span>
                </div>
                <div
                    v-if="totalDiskonStruk(struk) > 0"
                    class="flex justify-between text-xs text-rose-600 dark:text-rose-400"
                >
                    <span>Total Diskon</span>
                    <span>-{{ rupiah(totalDiskonStruk(struk)) }}</span>
                </div>
                <div class="flex justify-between font-bold text-slate-900 dark:text-white">
                    <span>Total</span>
                    <span class="text-blue-700 dark:text-blue-400">{{ rupiah(struk.total_faktur) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Bayar ({{ struk.cara_bayar }})</span
                    ><span class="font-medium text-slate-800 dark:text-slate-200">{{ rupiah(struk.total_bayar) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Kembalian</span
                    ><span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ rupiah(struk.kembalian) }}</span>
                </div>
                <div class="mt-4 flex gap-1.5 print:hidden">
                    <button
                        v-if="voiceEnabled"
                        type="button"
                        class="inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-xl border border-blue-200 bg-blue-50 px-3 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-100 transition dark:border-blue-800 dark:bg-blue-950/50 dark:text-blue-300 dark:hover:bg-blue-900/50"
                        title="Dengarkan ulang pengucapan nominal / kembalian"
                        @click="speakPaymentSuccess(Number(struk.total_faktur), Number(struk.kembalian), struk.cara_bayar)"
                    >
                        <Volume2 class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                        <span class="hidden sm:inline">Ulang Suara</span>
                    </button>
                    <button
                        type="button"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-blue-700 py-2.5 text-sm font-bold text-white cursor-pointer hover:bg-blue-800 transition dark:bg-blue-600 dark:hover:bg-blue-500"
                        @click="cetak"
                    >
                        <Printer class="h-4 w-4" /> Cetak Struk
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-semibold cursor-pointer hover:bg-slate-50 transition dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                        @click="struk = null"
                    >
                        Transaksi Baru
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Modal 1: Tahan Transaksi Sementara -->
        <Modal
            :open="holdModalOpen"
            title="Tahan Transaksi Sementara"
            @close="holdModalOpen = false"
        >
            <div class="space-y-4 text-sm text-slate-700 dark:text-slate-300">
                <div
                    class="space-y-2 rounded-2xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/60"
                >
                    <div
                        class="flex justify-between font-bold text-slate-900 dark:text-white"
                    >
                        <span>Total Transaksi Saat Ini</span>
                        <span class="text-blue-700 dark:text-blue-400">{{
                            rupiah(cart.total)
                        }}</span>
                    </div>
                    <div class="flex justify-between text-xs text-slate-500 dark:text-slate-400">
                        <span>Jumlah Barang</span>
                        <span>{{ cart.count }} item ({{ cart.items.length }} jenis)</span>
                    </div>
                    <div
                        v-if="cart.diskonNominal > 0"
                        class="flex justify-between text-xs text-rose-600 dark:text-rose-400"
                    >
                        <span>Diskon ({{ cart.diskonPersen }}%)</span>
                        <span>-{{ rupiah(cart.diskonNominal) }}</span>
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-300">
                        Catatan / Label Antrean (Opsional)
                    </label>
                    <input
                        v-model="holdNote"
                        type="text"
                        placeholder="Contoh: Siswa XII RPL - Budi / Seragam Pramuka"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500"
                        autofocus
                        @keydown.enter="confirmHold"
                    />
                    <p class="mt-1.5 text-[11px] text-slate-400 dark:text-slate-500">
                        Keranjang saat ini akan disimpan dan dikosongkan sementara agar kasir dapat segera melayani pelanggan berikutnya.
                    </p>
                </div>

                <div class="flex gap-2 pt-2">
                    <button
                        type="button"
                        class="flex-1 cursor-pointer rounded-xl bg-amber-600 py-2.5 text-xs font-bold uppercase tracking-wider text-white shadow-md transition hover:bg-amber-700 dark:bg-amber-600 dark:hover:bg-amber-500"
                        @click="confirmHold"
                    >
                        Tahan Transaksi
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                        @click="holdModalOpen = false"
                    >
                        Batal
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Modal 2: Daftar Transaksi Tertahan -->
        <Modal
            :open="heldModalOpen"
            title="Daftar Transaksi Tertahan"
            wide
            @close="heldModalOpen = false"
        >
            <div class="space-y-4">
                <!-- Peringatan jika keranjang aktif masih ada isinya saat memulihkan -->
                <div
                    v-if="restoreConfirmId"
                    class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-200"
                >
                    <div class="flex items-start gap-3">
                        <AlertCircle
                            class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400"
                        />
                        <div class="flex-1 space-y-2">
                            <p class="font-bold">
                                Keranjang aktif saat ini masih berisi {{ cart.count }} item ({{
                                    rupiah(cart.total)
                                }}).
                            </p>
                            <p class="text-xs text-amber-800 dark:text-amber-300">
                                Apa yang ingin Anda lakukan terhadap transaksi aktif saat ini sebelum memulihkan antrean yang dipilih?
                            </p>
                            <div class="flex flex-wrap gap-2 pt-1">
                                <button
                                    type="button"
                                    class="cursor-pointer rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-bold text-white shadow-xs transition hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-400 dark:text-slate-950"
                                    @click="
                                        executeRestore(restoreConfirmId, true)
                                    "
                                >
                                    Tahan Keranjang Aktif Lalu Pulihkan
                                </button>
                                <button
                                    type="button"
                                    class="cursor-pointer rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-900 transition hover:bg-amber-100 dark:border-amber-700/70 dark:bg-slate-900 dark:text-amber-300 dark:hover:bg-slate-800"
                                    @click="
                                        executeRestore(restoreConfirmId, false)
                                    "
                                >
                                    Timpa Keranjang Aktif
                                </button>
                                <button
                                    type="button"
                                    class="cursor-pointer rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-500 transition hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200"
                                    @click="restoreConfirmId = null"
                                >
                                    Batal
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="cart.heldList.length === 0"
                    class="space-y-2 py-10 text-center text-slate-400 dark:text-slate-500"
                >
                    <Clock class="mx-auto h-10 w-10 text-slate-300 dark:text-slate-600" />
                    <p class="text-sm font-semibold">
                        Tidak ada transaksi yang sedang ditahan.
                    </p>
                    <p class="text-xs text-slate-400 dark:text-slate-500">
                        Tekan tombol "Tahan" di panel keranjang untuk menyimpan transaksi sementara.
                    </p>
                </div>

                <div
                    v-else
                    class="max-h-[60vh] space-y-3 overflow-y-auto pr-1"
                >
                    <div
                        v-for="h in cart.heldList"
                        :key="h.id"
                        class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs transition hover:border-blue-300 hover:shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:hover:border-blue-500/50"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 pb-3 dark:border-slate-800"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-slate-900 dark:text-white">
                                        {{ h.catatan }}
                                    </span>
                                    <span
                                        class="rounded-md border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-300"
                                    >
                                        {{
                                            h.items.reduce(
                                                (n, i) => n + i.qty,
                                                0,
                                            )
                                        }}
                                        item
                                    </span>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">
                                    Ditahan: {{ formatWaktu(h.timestamp) }}
                                </p>
                            </div>

                            <div class="text-right">
                                <p
                                    class="text-base font-extrabold text-blue-700 dark:text-blue-400"
                                >
                                    {{ rupiah(h.total) }}
                                </p>
                                <p
                                    v-if="h.diskonPersen > 0"
                                    class="text-[11px] font-medium text-rose-500 dark:text-rose-400"
                                >
                                    Diskon {{ h.diskonPersen }}% (-{{
                                        rupiah(h.diskonNominal)
                                    }})
                                </p>
                            </div>
                        </div>

                        <!-- Ringkasan Barang -->
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            <span
                                v-for="item in h.items"
                                :key="item.id_barang"
                                class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                            >
                                {{ item.nama }}
                                <strong class="ml-1 text-slate-900 dark:text-white"
                                    >×{{ item.qty }}</strong
                                >
                            </span>
                        </div>

                        <!-- Tombol Aksi -->
                        <div
                            class="mt-4 flex items-center justify-end gap-2 border-t border-slate-50 pt-3 dark:border-slate-800"
                        >
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-100 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300 dark:hover:bg-red-900/60"
                                @click="deleteHeld(h.id, h.catatan)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                                <span>Hapus</span>
                            </button>
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-blue-700 px-4 py-1.5 text-xs font-bold text-white shadow-xs transition hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500"
                                @click="promptRestore(h.id)"
                            >
                                <RotateCcw class="h-3.5 w-3.5" />
                                <span>Buka Kembali</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Modal Scanner Barcode Kamera Kasir (Dual Method) -->
        <BarcodeScannerModal
            :open="scannerOpen"
            title="Pindai Barcode Kasir"
            subtitle="Arahkan kamera ke kode barcode produk untuk memasukkannya langsung ke keranjang belanja"
            @scan="handleBarcodeScan"
            @close="scannerOpen = false"
        />
    </PosLayout>
</template>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #struk-print,
    #struk-print * {
        visibility: visible;
    }
    #struk-print {
        position: absolute;
        inset: 0;
        padding: 16px;
    }
}
</style>
