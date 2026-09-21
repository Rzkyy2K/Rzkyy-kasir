<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Camera, Loader2, Pencil, Plus, Power, ScanBarcode, ShoppingBag } from '@lucide/vue';
import { onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import BarcodeScannerModal from '@/components/pos/BarcodeScannerModal.vue';
import CategoryFilter from '@/components/pos/CategoryFilter.vue';
import EmptyState from '@/components/pos/EmptyState.vue';
import Modal from '@/components/pos/Modal.vue';
import PageHeader from '@/components/pos/PageHeader.vue';
import Pagination from '@/components/pos/Pagination.vue';
import SearchBar from '@/components/pos/SearchBar.vue';
import PosLayout from '@/layouts/PosLayout.vue';
import { rupiah } from '@/lib/format';
import { friendlyError } from '@/services/api';
import {
    createBarang,
    deleteBarang,
    fetchBarang,
    lookupBarcode,
    updateBarang,
} from '@/services/barangService';
import { fetchKategori } from '@/services/kategoriService';
import { fetchSupplier } from '@/services/supplierService';
import { useCatalogStore } from '@/stores/catalog';
import { usePosStore } from '@/stores/pos';
import type { Barang, Kategori, Supplier } from '@/types/pos';

const pos = usePosStore();
const catalog = useCatalogStore();

const loading = ref(true);
const rows = ref<Barang[]>([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const search = ref('');
const idKelompok = ref<number | null>(null);

const showForm = ref(false);
const editing = ref<Barang | null>(null);
const saving = ref(false);
const form = ref<Record<string, any>>({});
const kategoriList = ref<Kategori[]>([]);
const supplierList = ref<Supplier[]>([]);

const scannerOpen = ref(false);
const scannerMode = ref<'form' | 'search'>('form');
const lookingUpBarcode = ref(false);

let timer: ReturnType<typeof setTimeout> | null = null;

async function load() {
    loading.value = true;
    try {
        const res = await fetchBarang({
            id_sekolah: pos.idSekolah,
            search: search.value || undefined,
            id_kelompok_kategori: idKelompok.value ?? undefined,
            per_page: 12,
            page: page.value,
        });
        rows.value = res.data;
        page.value = res.current_page;
        lastPage.value = res.last_page;
        total.value = res.total;
    } catch (e) {
        toast.error(friendlyError(e, 'Daftar produk gagal dimuat.'));
    } finally {
        loading.value = false;
    }
}

function onSearch() {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        page.value = 1;
        void load();
    }, 400);
}

function bukaTambah() {
    editing.value = null;
    form.value = {
        id_sekolah: pos.idSekolah,
        satuan: 'pcs',
        stok: 0,
        harga_beli: 0,
        harga_jual: 0,
        is_active: true,
    };
    showForm.value = true;
}

function bukaScannerForm() {
    scannerMode.value = 'form';
    scannerOpen.value = true;
}

function bukaScannerSearch() {
    scannerMode.value = 'search';
    scannerOpen.value = true;
}

async function lookupBarcodeInfo(barcode: string) {
    if (!barcode || !barcode.trim()) return;
    lookingUpBarcode.value = true;
    try {
        const res = await lookupBarcode(barcode.trim(), pos.idSekolah);
        if (res.data.found && res.data.nama) {
            // Otomatis isi nama barang ke kolom input form
            form.value.nama = res.data.nama;

            // Jika ada info harga, satuan, dan kategori dari produk terdaftar, bantu isikan juga
            if (res.data.harga_beli && (!form.value.harga_beli || form.value.harga_beli === 0)) {
                form.value.harga_beli = res.data.harga_beli;
            }
            if (res.data.harga_jual && (!form.value.harga_jual || form.value.harga_jual === 0)) {
                form.value.harga_jual = res.data.harga_jual;
            }
            if (res.data.satuan && (!form.value.satuan || form.value.satuan === 'pcs')) {
                form.value.satuan = res.data.satuan;
            }
            if (res.data.id_kategori && !form.value.id_kategori) {
                form.value.id_kategori = res.data.id_kategori;
            }
            if (res.data.id_kelompok_kategori && !form.value.id_kelompok_kategori) {
                form.value.id_kelompok_kategori = res.data.id_kelompok_kategori;
            }

            if (res.data.source === 'local') {
                toast.success(`Nama produk otomatis terisi: "${res.data.nama}"`);
            } else if (res.data.source === 'edumart_catalog') {
                toast.success(`Ditemukan dari katalog Scholify: "${res.data.nama}"`);
            } else {
                toast.success(`Nama kemasan ditemukan: "${res.data.nama}"`);
            }
        } else {
            toast.info(`Barcode ${barcode} belum ada di sistem. Silakan lengkapi nama & harga.`);
        }
    } catch {
        // Fallback hening jika jaringan offline
    } finally {
        lookingUpBarcode.value = false;
    }
}

function onBarcodeDetected(code: string) {
    if (scannerMode.value === 'form') {
        form.value.barcode = code;
        void lookupBarcodeInfo(code);
    } else {
        search.value = code;
        page.value = 1;
        void load();
        toast.success(`Menampilkan produk dengan barcode: ${code}`);
    }
}

function bukaEdit(b: Barang) {
    editing.value = b;
    form.value = { ...b };
    showForm.value = true;
}

async function simpan() {
    saving.value = true;
    try {
        if (editing.value) {
            const res = await updateBarang(editing.value.id_barang, form.value);
            toast.success(res.message);
        } else {
            const res = await createBarang(form.value);
            toast.success(res.message);
        }
        showForm.value = false;
        await load();
    } catch (e) {
        toast.error(
            friendlyError(e, 'Barang gagal disimpan. Silakan coba lagi.'),
        );
    } finally {
        saving.value = false;
    }
}

async function nonaktifkan(b: Barang) {
    if (!confirm(`Hapus permanen "${b.nama}"? Barang & barcode akan hilang dan bisa dipakai lagi.`)) return;
    try {
        const res = await deleteBarang(b.id_barang);
        toast.success(res.message ?? 'Barang berhasil dihapus.');
        await load();
    } catch (e) {
        toast.error(friendlyError(e, 'Barang gagal dihapus.'));
    }
}

onMounted(() => {
    void (async () => {
        await pos.init();
        kategoriList.value = (await fetchKategori({ per_page: 100 })).data;
        supplierList.value = (
            await fetchSupplier({ id_sekolah: pos.idSekolah, per_page: 100 })
        ).data;
        await load();
    })();
});
watch([() => pos.idSekolah, idKelompok], () => {
    page.value = 1;
    void load();
});
</script>

<template>
    <Head title="Produk" />
    <PosLayout>
        <PageHeader
            title="Kelola Produk"
            :icon="ShoppingBag"
            subtitle="Kelola katalog produk, kode barcode, harga jual, dan ketersediaan stok"
        >
            <template #actions>
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white shadow-xs hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500"
                    @click="bukaTambah"
                >
                    <Plus class="h-4 w-4" /> Tambah Produk
                </button>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-3 sm:flex-row">
            <div class="flex-1">
                <SearchBar
                    v-model="search"
                    placeholder="Cari nama produk atau kode barcode…"
                    @update:model-value="onSearch"
                />
            </div>
            <button
                type="button"
                class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 transition"
                title="Pindai barcode untuk mencari produk"
                @click="bukaScannerSearch"
            >
                <ScanBarcode class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                <span>Pindai Barcode</span>
            </button>
        </div>
        <div class="mt-4">
            <CategoryFilter
                v-model="idKelompok"
                :options="[
                    { value: null, label: 'Semua' },
                    ...catalog.kelompok.map((k) => ({
                        value: k.id_kelompok as number | null,
                        label: k.nama_kelompok,
                    })),
                ]"
            />
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
            title="Belum Ada Produk"
            message="Klik tombol Tambah Produk untuk mendaftarkan barang baru ke dalam katalog."
        />
        <template v-else>
            <!-- Unified Responsive Card Grid -->
            <div class="mt-4 grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                <div
                    v-for="b in rows"
                    :key="b.id_barang"
                    class="relative flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-xs transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900 min-w-0"
                >
                    <!-- Header: Icon, Name, Barcode, Status -->
                    <div>
                        <div class="flex items-start gap-3 min-w-0">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 font-bold text-sm text-blue-700 dark:bg-blue-950/60 dark:text-blue-400"
                            >
                                <ShoppingBag class="h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="font-bold text-sm text-slate-800 truncate dark:text-slate-100">
                                        {{ b.nama }}
                                    </h3>
                                    <span
                                        :class="
                                            b.is_active
                                                ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400'
                                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                                        "
                                        class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold"
                                    >
                                        {{ b.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-400 truncate dark:text-slate-500">
                                    {{ b.barcode || 'Tanpa Barcode' }} · {{ b.satuan }}
                                </p>
                            </div>
                        </div>

                        <!-- Price & Stock Info Box -->
                        <div class="mt-3 grid grid-cols-3 gap-2 rounded-xl bg-slate-50 p-2.5 dark:bg-slate-800/50 text-center">
                            <div class="min-w-0">
                                <span class="block text-[10px] text-slate-400 dark:text-slate-500 truncate">Harga Beli</span>
                                <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 truncate block">
                                    {{ rupiah(b.harga_beli) }}
                                </span>
                            </div>
                            <div class="min-w-0">
                                <span class="block text-[10px] text-slate-400 dark:text-slate-500 truncate">Harga Jual</span>
                                <span class="text-xs font-bold text-blue-700 dark:text-blue-400 truncate block">
                                    {{ rupiah(b.harga_jual) }}
                                </span>
                            </div>
                            <div class="min-w-0">
                                <span class="block text-[10px] text-slate-400 dark:text-slate-500 truncate">Stok</span>
                                <span
                                    class="text-xs font-extrabold truncate block"
                                    :class="b.stok <= 10 ? 'text-red-600 dark:text-red-400' : 'text-slate-800 dark:text-slate-200'"
                                >
                                    {{ b.stok }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer: Kategori & Actions -->
                    <div class="mt-3.5 flex items-center justify-between gap-2 border-t border-slate-100 pt-3 dark:border-slate-800/80">
                        <span
                            class="inline-block truncate rounded-lg bg-blue-50 px-2 py-1 text-[11px] font-semibold text-blue-700 dark:bg-blue-950/60 dark:text-blue-400 max-w-32"
                        >
                            {{ b.kategori?.nama ?? 'Umum' }}
                        </span>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <button
                                type="button"
                                title="Edit barang"
                                class="cursor-pointer inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 shadow-2xs hover:bg-blue-50 hover:text-blue-700 hover:border-blue-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 dark:hover:text-blue-400 transition"
                                @click="bukaEdit(b)"
                            >
                                <Pencil class="h-3.5 w-3.5" />
                                <span>Edit</span>
                            </button>
                            <button
                                type="button"
                                title="Hapus barang"
                                class="cursor-pointer inline-flex items-center gap-1 rounded-lg border border-red-200/80 bg-red-50/50 px-2.5 py-1 text-xs font-medium text-red-600 shadow-2xs hover:bg-red-100 hover:text-red-700 hover:border-red-300 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-400 dark:hover:bg-red-900/50 transition"
                                @click="nonaktifkan(b)"
                            >
                                <Power class="h-3.5 w-3.5" />
                                <span>Hapus</span>
                            </button>
                        </div>
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

        <Modal
            :open="showForm"
            :title="editing ? 'Edit Data Produk' : 'Tambah Produk Baru'"
            wide
            @close="showForm = false"
        >
            <form class="space-y-3" @submit.prevent="simpan">
                <!-- Tip Singkat -->
                <div class="flex items-center gap-2 rounded-xl bg-blue-50/80 px-3 py-2 text-xs text-blue-900 dark:border dark:border-blue-900/50 dark:bg-blue-950/40 dark:text-blue-300">
                    <span class="font-bold shrink-0">💡 Info:</span>
                    <span class="text-[11px] sm:text-xs">
                        Untuk produk kantin atau tanpa kemasan barcode, Anda dapat langsung mengisikan nama produk.
                    </span>
                </div>

                <!-- Barcode Kemasan -->
                <div>
                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                        Kode Barcode (Opsional)
                        <div class="mt-1 flex gap-1.5">
                            <input
                                v-model="form.barcode"
                                type="text"
                                placeholder="Pindai atau masukkan kode barcode..."
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs sm:text-sm text-slate-800 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500"
                                @keydown.enter.prevent="lookupBarcodeInfo(form.barcode)"
                            />
                            <button
                                type="button"
                                class="inline-flex shrink-0 cursor-pointer items-center gap-1 rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-100 transition dark:border-blue-800 dark:bg-blue-950/50 dark:text-blue-300 dark:hover:bg-blue-900/50"
                                title="Buka kamera untuk memindai kemasan produk"
                                @click="bukaScannerForm"
                            >
                                <Camera class="h-3.5 w-3.5" />
                                <span>Pindai</span>
                            </button>
                            <button
                                v-if="form.barcode"
                                type="button"
                                :disabled="lookingUpBarcode"
                                class="inline-flex shrink-0 cursor-pointer items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-50 transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                                title="Cari data produk dari katalog sistem"
                                @click="lookupBarcodeInfo(form.barcode)"
                            >
                                <Loader2 v-if="lookingUpBarcode" class="h-3.5 w-3.5 animate-spin text-blue-600 dark:text-blue-400" />
                                <span v-else>Cek Data</span>
                            </button>
                        </div>
                    </label>
                </div>

                <!-- Nama Barang -->
                <div>
                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                        Nama Produk*
                        <input
                            v-model="form.nama"
                            required
                            placeholder="Contoh: Teh Botol Sosro / Roti Bakar"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs sm:text-sm text-slate-800 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500"
                        />
                    </label>
                </div>

                <!-- Kategori & Kelompok (2 Kolom di Mobile & Desktop) -->
                <div class="grid grid-cols-2 gap-2 sm:gap-3">
                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                        Kategori*
                        <select
                            v-model="form.id_kategori"
                            required
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-xs sm:text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                        >
                            <option
                                v-for="k in kategoriList"
                                :key="k.id_kategori"
                                :value="k.id_kategori"
                            >
                                {{ k.nama }}
                            </option>
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                        Kelompok Kategori*
                        <select
                            v-model="form.id_kelompok_kategori"
                            required
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-xs sm:text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                        >
                            <option
                                v-for="k in catalog.kelompok"
                                :key="k.id_kelompok"
                                :value="k.id_kelompok"
                            >
                                {{ k.nama_kelompok }}
                            </option>
                        </select>
                    </label>
                </div>

                <!-- Supplier -->
                <div>
                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                        Pemasok (Supplier)*
                        <select
                            v-model="form.id_supplier"
                            required
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-xs sm:text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
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
                </div>

                <!-- Harga Beli & Harga Jual (2 Kolom) -->
                <div class="grid grid-cols-2 gap-2 sm:gap-3">
                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                        Harga Beli (Rp)*
                        <input
                            v-model.number="form.harga_beli"
                            type="number"
                            min="0"
                            required
                            placeholder="0"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs sm:text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                        />
                    </label>
                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                        Harga Jual (Rp)*
                        <input
                            v-model.number="form.harga_jual"
                            type="number"
                            min="0"
                            required
                            placeholder="0"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs sm:text-sm font-semibold text-blue-800 dark:border-slate-700 dark:bg-slate-800 dark:text-blue-400"
                        />
                    </label>
                </div>

                <!-- Satuan & Stok Awal (2 Kolom) -->
                <div class="grid grid-cols-2 gap-2 sm:gap-3">
                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                        Satuan Unit*
                        <input
                            v-model="form.satuan"
                            required
                            placeholder="Contoh: pcs, porsi, botol"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs sm:text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                        />
                    </label>
                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                        Stok Awal*
                        <input
                            v-model.number="form.stok"
                            type="number"
                            min="0"
                            required
                            :disabled="!!editing"
                            placeholder="0"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs sm:text-sm text-slate-800 disabled:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:disabled:bg-slate-800/50"
                        />
                    </label>
                </div>

                <!-- Status Aktif & Tombol Aksi -->
                <div class="pt-1">
                    <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded accent-blue-700 cursor-pointer"
                        />
                        <span>Aktifkan untuk penjualan di kasir</span>
                    </label>
                </div>

                <div class="flex gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button
                        type="button"
                        class="flex-1 cursor-pointer rounded-xl border border-slate-200 py-2.5 text-xs sm:text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 transition"
                        @click="showForm = false"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="flex-1 cursor-pointer rounded-xl bg-blue-700 py-2.5 text-xs sm:text-sm font-bold text-white hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500 transition disabled:opacity-60 shadow-xs"
                    >
                        {{ saving ? 'Menyimpan…' : (editing ? 'Perbarui Data' : 'Simpan Produk') }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Modal Scanner Barcode Kamera (Dual Method) -->
        <BarcodeScannerModal
            :open="scannerOpen"
            :title="scannerMode === 'form' ? 'Pindai Barcode Produk' : 'Cari Produk via Barcode'"
            :subtitle="
                scannerMode === 'form'
                    ? 'Arahkan kamera ke barcode kemasan produk untuk mengisi formulir secara otomatis'
                    : 'Arahkan kamera ke barcode produk untuk mencari dalam daftar katalog'
            "
            @scan="onBarcodeDetected"
            @close="scannerOpen = false"
        />
    </PosLayout>
</template>
