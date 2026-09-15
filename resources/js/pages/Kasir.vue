<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Printer, ShoppingCart, X } from '@lucide/vue';
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
                    <CartPanel :bayar-loading="bayarLoading" @bayar="bayar" />
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
                    <CartPanel :bayar-loading="bayarLoading" @bayar="bayar" />
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
