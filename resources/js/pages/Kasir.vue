<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    AlertCircle,
    Clock,
    PauseCircle,
    Printer,
    RotateCcw,
    ShoppingCart,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import PageHeader from '@/components/pos/PageHeader.vue';
import CartPanel from '@/components/pos/CartPanel.vue';
import CategoryFilter from '@/components/pos/CategoryFilter.vue';
import EmptyState from '@/components/pos/EmptyState.vue';
import LoadingSkeleton from '@/components/pos/LoadingSkeleton.vue';
import Modal from '@/components/pos/Modal.vue';
import ProductCard from '@/components/pos/ProductCard.vue';
import SearchBar from '@/components/pos/SearchBar.vue';
import PosLayout from '@/layouts/PosLayout.vue';
import { rupiah, tanggal } from '@/lib/format';
import { friendlyError } from '@/services/api';
import { fetchBarang } from '@/services/barangService';
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

onMounted(() => {
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
watch([() => pos.idSekolah, idKelompok], () => void cari());
</script>

<template>
    <Head title="Kasir" />
    <PosLayout>
        <PageHeader
            title="Kasir"
            subtitle="Transaksi cepat & cetak struk"
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
                    <select
                        v-model="cart.idPelanggan"
                        class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                    >
                        <option :value="null">Pelanggan umum</option>
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
                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-3.5 py-2.5 text-xs font-bold text-amber-800 shadow-sm transition hover:bg-amber-100"
                        @click="heldModalOpen = true"
                    >
                        <Clock class="h-4 w-4 text-amber-600 animate-pulse" />
                        <span>{{ cart.heldCount }} Transaksi Tertahan</span>
                    </button>
                </div>
                <div class="mt-4">
                    <CategoryFilter
                        v-model="idKelompok"
                        :options="kategoriOptions"
                    />
                </div>

                <LoadingSkeleton v-if="loading" class="mt-4" />
                <EmptyState
                    v-else-if="hasil.length === 0"
                    class="mt-4"
                    title="Belum ada produk"
                    message="Tambahkan barang lewat menu Produk, atau ubah kata kunci pencarian."
                />
                <div
                    v-else
                    class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 2xl:grid-cols-4"
                >
                    <ProductCard
                        v-for="b in hasil"
                        :key="b.id_barang"
                        :item="b"
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
            class="fixed right-4 bottom-20 z-40 flex items-center gap-1.5 rounded-full bg-blue-700 px-5 py-3.5 text-sm font-bold text-white shadow-xl xl:hidden"
            @click="cartOpen = true"
        >
            <ShoppingCart class="h-5 w-5" />
            {{ cart.count }} item · {{ rupiah(cart.total) }}
        </button>

        <!-- Bottom sheet keranjang -->
        <Teleport to="body">
            <div v-if="cartOpen" class="fixed inset-0 z-50 xl:hidden">
                <div
                    class="absolute inset-0 bg-slate-900/50"
                    @click="cartOpen = false"
                />
                <div
                    class="absolute inset-x-0 bottom-0 max-h-[92vh] overflow-y-auto rounded-t-3xl bg-slate-100 p-3"
                >
                    <div class="mb-2 flex items-center justify-between px-1">
                        <p class="text-sm font-bold">Keranjang Belanja</p>
                        <button
                            type="button"
                            class="rounded-lg p-1.5 hover:bg-slate-200"
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
        </Teleport>

        <!-- Struk -->
        <Modal
            :open="struk !== null"
            title="Transaksi Berhasil"
            @close="struk = null"
        >
            <div v-if="struk" id="struk-print" class="text-sm text-slate-700">
                <div class="text-center">
                    <p class="text-base font-black text-slate-900">EduMart</p>
                    <p class="text-xs text-slate-500">
                        {{ pos.sekolahAktif?.nama_sekolah }}
                    </p>
                    <p class="mt-1 text-xs">
                        {{ tanggal(struk.tanggal_penjualan) }}
                    </p>
                </div>
                <hr class="my-3 border-dashed" />
                <div
                    v-for="d in struk.detail ?? []"
                    :key="d.id_detail_penjualan"
                    class="py-0.5"
                >
                    <div class="flex justify-between">
                        <span>{{ d.barang?.nama }} × {{ d.jumlah_barang }}</span>
                        <span class="font-semibold">{{ rupiah(d.subtotal) }}</span>
                    </div>
                    <div
                        v-if="Number(d.diskon_nominal) > 0"
                        class="flex justify-between text-[11px] text-rose-600"
                    >
                        <span class="pl-2">Diskon ({{ Number(d.diskon_nilai) }}%)</span>
                        <span>-{{ rupiah(d.diskon_nominal) }}</span>
                    </div>
                </div>
                <hr class="my-3 border-dashed" />
                <div
                    v-if="totalDiskonStruk(struk) > 0"
                    class="flex justify-between text-xs text-slate-500"
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
                    class="flex justify-between text-xs text-rose-600"
                >
                    <span>Total Diskon</span>
                    <span>-{{ rupiah(totalDiskonStruk(struk)) }}</span>
                </div>
                <div class="flex justify-between font-bold text-slate-900">
                    <span>Total</span>
                    <span>{{ rupiah(struk.total_faktur) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Bayar ({{ struk.cara_bayar }})</span
                    ><span>{{ rupiah(struk.total_bayar) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Kembalian</span
                    ><span>{{ rupiah(struk.kembalian) }}</span>
                </div>
                <div class="mt-4 flex gap-1.5 print:hidden">
                    <button
                        type="button"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-blue-700 py-2.5 text-sm font-bold text-white"
                        @click="cetak"
                    >
                        <Printer class="h-4 w-4" /> Cetak Struk
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-semibold"
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
            <div class="space-y-4 text-sm text-slate-700">
                <div
                    class="space-y-2 rounded-2xl border border-slate-100 bg-slate-50 p-4"
                >
                    <div
                        class="flex justify-between font-bold text-slate-900"
                    >
                        <span>Total Transaksi Saat Ini</span>
                        <span class="text-blue-700">{{
                            rupiah(cart.total)
                        }}</span>
                    </div>
                    <div class="flex justify-between text-xs text-slate-500">
                        <span>Jumlah Barang</span>
                        <span>{{ cart.count }} item ({{ cart.items.length }} jenis)</span>
                    </div>
                    <div
                        v-if="cart.diskonNominal > 0"
                        class="flex justify-between text-xs text-rose-600"
                    >
                        <span>Diskon ({{ cart.diskonPersen }}%)</span>
                        <span>-{{ rupiah(cart.diskonNominal) }}</span>
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-bold text-slate-700">
                        Catatan / Label Antrean (Opsional)
                    </label>
                    <input
                        v-model="holdNote"
                        type="text"
                        placeholder="cth: Siswa XII RPL - Budi / Seragam Pramuka"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        autofocus
                        @keydown.enter="confirmHold"
                    />
                    <p class="mt-1.5 text-[11px] text-slate-400">
                        Keranjang saat ini akan disimpan dan dikosongkan sementara agar kasir dapat segera melayani pelanggan berikutnya.
                    </p>
                </div>

                <div class="flex gap-2 pt-2">
                    <button
                        type="button"
                        class="flex-1 cursor-pointer rounded-xl bg-amber-600 py-2.5 text-xs font-bold uppercase tracking-wider text-white shadow-md transition hover:bg-amber-700"
                        @click="confirmHold"
                    >
                        Tahan Transaksi
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
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
                    class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900"
                >
                    <div class="flex items-start gap-3">
                        <AlertCircle
                            class="mt-0.5 h-5 w-5 shrink-0 text-amber-600"
                        />
                        <div class="flex-1 space-y-2">
                            <p class="font-bold">
                                Keranjang aktif saat ini masih berisi {{ cart.count }} item ({{
                                    rupiah(cart.total)
                                }}).
                            </p>
                            <p class="text-xs text-amber-800">
                                Apa yang ingin Anda lakukan terhadap transaksi aktif saat ini sebelum memulihkan antrean yang dipilih?
                            </p>
                            <div class="flex flex-wrap gap-2 pt-1">
                                <button
                                    type="button"
                                    class="cursor-pointer rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-bold text-white shadow-xs transition hover:bg-amber-700"
                                    @click="
                                        executeRestore(restoreConfirmId, true)
                                    "
                                >
                                    Tahan Keranjang Aktif Lalu Pulihkan
                                </button>
                                <button
                                    type="button"
                                    class="cursor-pointer rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-900 transition hover:bg-amber-100"
                                    @click="
                                        executeRestore(restoreConfirmId, false)
                                    "
                                >
                                    Timpa Keranjang Aktif
                                </button>
                                <button
                                    type="button"
                                    class="cursor-pointer rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-500 transition hover:text-slate-800"
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
                    class="space-y-2 py-10 text-center text-slate-400"
                >
                    <Clock class="mx-auto h-10 w-10 text-slate-300" />
                    <p class="text-sm font-semibold">
                        Tidak ada transaksi yang sedang ditahan.
                    </p>
                    <p class="text-xs text-slate-400">
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
                        class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs transition hover:border-blue-300 hover:shadow-sm"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 pb-3"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-slate-900">
                                        {{ h.catatan }}
                                    </span>
                                    <span
                                        class="rounded-md border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700"
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
                                <p class="mt-0.5 text-xs text-slate-400">
                                    Ditahan: {{ formatWaktu(h.timestamp) }}
                                </p>
                            </div>

                            <div class="text-right">
                                <p
                                    class="text-base font-extrabold text-blue-700"
                                >
                                    {{ rupiah(h.total) }}
                                </p>
                                <p
                                    v-if="h.diskonPersen > 0"
                                    class="text-[11px] font-medium text-rose-500"
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
                                class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700"
                            >
                                {{ item.nama }}
                                <strong class="ml-1 text-slate-900"
                                    >×{{ item.qty }}</strong
                                >
                            </span>
                        </div>

                        <!-- Tombol Aksi -->
                        <div
                            class="mt-4 flex items-center justify-end gap-2 border-t border-slate-50 pt-3"
                        >
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-100"
                                @click="deleteHeld(h.id, h.catatan)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                                <span>Hapus</span>
                            </button>
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-blue-700 px-4 py-1.5 text-xs font-bold text-white shadow-xs transition hover:bg-blue-800"
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
