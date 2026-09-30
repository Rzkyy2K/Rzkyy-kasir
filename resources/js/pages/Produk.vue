<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Camera,
    LayoutGrid,
    Loader2,
    Pencil,
    Plus,
    Power,
    ScanBarcode,
    ShoppingBag,
    Table,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
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
    extractImageFromUrl,
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

const viewMode = ref<'card' | 'table'>('card');
if (typeof window !== 'undefined') {
    const saved = localStorage.getItem('pos_produk_view_mode');
    if (saved === 'card' || saved === 'table') {
        viewMode.value = saved;
    }
}

function setViewMode(mode: 'card' | 'table') {
    viewMode.value = mode;
    try {
        localStorage.setItem('pos_produk_view_mode', mode);
    } catch {
        // no-op
    }
}

const showForm = ref(false);
const editing = ref<Barang | null>(null);
const saving = ref(false);
const form = ref<Record<string, any>>({});
const kategoriList = ref<Kategori[]>([]);
const supplierList = ref<Supplier[]>([]);

const baseSatuanList = ['pcs', 'pak', 'box', 'renceng', 'botol', 'porsi', 'bungkus'];
const customSatuanMode = ref(false);
const fotoPreviewError = ref(false);

const groupedKategori = computed(() => {
    const map = new Map<number, { id_kelompok: number; nama_kelompok: string; items: Kategori[] }>();
    const groups: { id_kelompok: number; nama_kelompok: string; items: Kategori[] }[] = [];

    // Prioritaskan urutan kelompok dari catalog
    for (const kel of catalog.kelompok) {
        const grp = {
            id_kelompok: kel.id_kelompok,
            nama_kelompok: kel.nama_kelompok,
            items: [] as Kategori[],
        };
        map.set(kel.id_kelompok, grp);
        groups.push(grp);
    }

    const unassigned: Kategori[] = [];

    for (const kat of kategoriList.value) {
        const idKel = kat.id_kelompok;
        if (idKel && map.has(idKel)) {
            map.get(idKel)!.items.push(kat);
        } else {
            // Cek jika kategori memiliki kelompok relasi yang belum terdaftar
            const fallbackId = idKel || 0;
            if (!map.has(fallbackId)) {
                const grp = {
                    id_kelompok: fallbackId,
                    nama_kelompok: kat.kelompok?.nama_kelompok ?? 'Lainnya / Umum',
                    items: [] as Kategori[],
                };
                map.set(fallbackId, grp);
                groups.push(grp);
            }
            map.get(fallbackId)!.items.push(kat);
        }
    }

    // Hanya tampilkan grup yang memiliki daftar kategori
    return groups.filter((g) => g.items.length > 0);
});

function onKategoriChange(e: Event) {
    const idKat = Number((e.target as HTMLSelectElement).value);
    form.value.id_kategori = idKat;
    const found = kategoriList.value.find((k) => k.id_kategori === idKat);
    if (found) {
        form.value.id_kelompok_kategori = found.id_kelompok;
    }
}

function cleanImageUrl(url: string): string {
    if (!url) return '';
    let trimmed = url.trim();

    // Ekstrak URL asli bila user copy link dari hasil pencarian Google Images (misal: imgres?imgurl=...)
    if (trimmed.includes('google.') && (trimmed.includes('imgurl=') || trimmed.includes('url='))) {
        try {
            const parsed = new URL(trimmed);
            const imgUrl = parsed.searchParams.get('imgurl') || parsed.searchParams.get('url');
            if (imgUrl && (imgUrl.startsWith('http://') || imgUrl.startsWith('https://'))) {
                return decodeURIComponent(imgUrl);
            }
        } catch {
            const match = trimmed.match(/[?&](?:imgurl|url)=(https?%3A%2F%2F[^&]+|https?:\/\/[^&]+)/i);
            if (match && match[1]) {
                return decodeURIComponent(match[1]);
            }
        }
    }

    return trimmed;
}

const extractingImage = ref(false);
let fotoDebounceTimer: ReturnType<typeof setTimeout> | null = null;

async function resolveImageUrl(rawUrl: string, autoExtract = true) {
    if (!rawUrl || !rawUrl.trim()) return;
    const cleaned = cleanImageUrl(rawUrl);
    form.value.foto = cleaned;
    fotoPreviewError.value = false;

    // Cek apakah ini URL halaman web (bukan gambar langsung dan bukan base64)
    const isDirectImage =
        cleaned.startsWith('data:image/') ||
        Boolean(cleaned.match(/\.(jpg|jpeg|png|webp|gif|svg|avif)(\?.*)?$/i));

    if (
        autoExtract &&
        !isDirectImage &&
        (cleaned.startsWith('http://') || cleaned.startsWith('https://'))
    ) {
        extractingImage.value = true;
        try {
            const res = await extractImageFromUrl(cleaned);
            if (res.data?.image_url) {
                form.value.foto = res.data.image_url;
                fotoPreviewError.value = false;
                toast.success('Foto produk berhasil diekstrak dari halaman web!');
            }
        } catch {
            // Biarkan user melihat gambar atau coba link lain
        } finally {
            extractingImage.value = false;
        }
    }
}

function onFotoInput(e: Event) {
    const val = (e.target as HTMLInputElement).value;
    form.value.foto = val;
    fotoPreviewError.value = false;

    if (fotoDebounceTimer) clearTimeout(fotoDebounceTimer);
    fotoDebounceTimer = setTimeout(() => {
        void resolveImageUrl(form.value.foto, true);
    }, 600);
}

const satuanList = computed(() => {
    const current = form.value.satuan ? String(form.value.satuan).trim() : '';
    if (current && !baseSatuanList.includes(current) && current !== '__custom__') {
        return [current, ...baseSatuanList];
    }
    return baseSatuanList;
});

function onSatuanChange(e: Event) {
    const val = (e.target as HTMLSelectElement).value;
    if (val === '__custom__') {
        customSatuanMode.value = true;
        form.value.satuan = '';
    } else {
        form.value.satuan = val;
    }
}

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
    customSatuanMode.value = false;
    fotoPreviewError.value = false;
    form.value = {
        id_sekolah: pos.idSekolah,
        nama: '',
        barcode: '',
        foto: '',
        id_kategori: undefined,
        id_kelompok_kategori: undefined,
        id_supplier: undefined,
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

            // Jika ada info foto dari database/katalog publik, bantu isikan juga
            if (res.data.foto && (!form.value.foto || form.value.foto === '')) {
                form.value.foto = res.data.foto;
                fotoPreviewError.value = false;
            }

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
                const found = kategoriList.value.find((k) => k.id_kategori === res.data.id_kategori);
                if (found) {
                    form.value.id_kelompok_kategori = found.id_kelompok;
                }
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
    customSatuanMode.value = false;
    fotoPreviewError.value = false;
    form.value = { ...b, foto: b.foto ?? '' };
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

async function toggleStatus(b: Barang) {
    if (
        pos.checkDemo(
            'Akses Dibatasi: Akun Demo tidak memiliki izin untuk mengubah status data.',
        )
    )
        return;

    const newStatus = !b.is_active;
    try {
        await updateBarang(b.id_barang, { is_active: newStatus });
        b.is_active = newStatus;
        toast.success(
            newStatus
                ? `"${b.nama}" berhasil diaktifkan untuk penjualan.`
                : `"${b.nama}" dinonaktifkan (tidak untuk aktif dijual).`,
        );
    } catch (e) {
        toast.error(friendlyError(e, 'Gagal memperbarui status barang.'));
    }
}

const deleteModalOpen = ref(false);
const barangToDelete = ref<Barang | null>(null);
const actionLoading = ref(false);

const isTradedAndActive = computed(() => {
    if (!barangToDelete.value) return false;
    const count =
        (barangToDelete.value.detail_penjualan_count ?? 0) +
        (barangToDelete.value.detail_pembelian_count ?? 0);
    return count > 0 && barangToDelete.value.is_active;
});

const isTraded = computed(() => {
    if (!barangToDelete.value) return false;
    const count =
        (barangToDelete.value.detail_penjualan_count ?? 0) +
        (barangToDelete.value.detail_pembelian_count ?? 0);
    return count > 0;
});

function bukaHapusModal(b: Barang) {
    if (
        pos.checkDemo(
            'Akses Dibatasi: Akun Demo tidak memiliki izin untuk menghapus data. Silakan masuk menggunakan akun resmi.',
        )
    )
        return;

    barangToDelete.value = b;
    deleteModalOpen.value = true;
}

async function nonaktifkanDariModal() {
    if (!barangToDelete.value) return;
    actionLoading.value = true;
    try {
        await updateBarang(barangToDelete.value.id_barang, { is_active: false });
        barangToDelete.value.is_active = false;
        toast.success(
            `"${barangToDelete.value.nama}" berhasil dinonaktifkan (tidak untuk aktif dijual).`,
        );
        await load();
    } catch (e) {
        toast.error(friendlyError(e, 'Gagal memperbarui status barang.'));
    } finally {
        actionLoading.value = false;
    }
}

async function eksekusiHapus() {
    if (!barangToDelete.value) return;
    actionLoading.value = true;
    try {
        const res = await deleteBarang(barangToDelete.value.id_barang);
        toast.success(res.message ?? 'Barang berhasil dihapus.');
        deleteModalOpen.value = false;
        barangToDelete.value = null;
        await load();
    } catch (e) {
        toast.error(friendlyError(e, 'Barang gagal dihapus.'));
    } finally {
        actionLoading.value = false;
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

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="flex-1">
                <SearchBar
                    v-model="search"
                    placeholder="Cari nama produk atau kode barcode…"
                    @update:model-value="onSearch"
                />
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 transition"
                    title="Pindai barcode untuk mencari produk"
                    @click="bukaScannerSearch"
                >
                    <ScanBarcode class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                    <span class="hidden sm:inline">Pindai Barcode</span>
                </button>

                <!-- Switcher Mode Tampilan: Card & Tabel -->
                <div class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-100/90 p-1 dark:border-slate-800 dark:bg-slate-900">
                    <button
                        type="button"
                        :class="[
                            'inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition',
                            viewMode === 'card'
                                ? 'bg-white text-blue-700 shadow-2xs dark:bg-slate-800 dark:text-blue-400'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'
                        ]"
                        title="Tampilan Card"
                        @click="setViewMode('card')"
                    >
                        <LayoutGrid class="h-4 w-4" />
                        <span>Card</span>
                    </button>
                    <button
                        type="button"
                        :class="[
                            'inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition',
                            viewMode === 'table'
                                ? 'bg-white text-blue-700 shadow-2xs dark:bg-slate-800 dark:text-blue-400'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'
                        ]"
                        title="Tampilan Tabel"
                        @click="setViewMode('table')"
                    >
                        <Table class="h-4 w-4" />
                        <span>Tabel</span>
                    </button>
                </div>
            </div>
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
            <!-- 1. Tampilan Card / Grid -->
            <div
                v-if="viewMode === 'card'"
                class="mt-4 grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5"
            >
                <div
                    v-for="b in rows"
                    :key="b.id_barang"
                    class="relative flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-xs transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900 min-w-0"
                >
                    <!-- Header: Icon, Name, Barcode, Status -->
                    <div>
                        <div class="flex items-start gap-3 min-w-0">
                            <div
                                class="relative flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-100 bg-slate-50 font-bold text-sm text-blue-700 dark:border-slate-800 dark:bg-slate-800 dark:text-blue-400"
                            >
                                <img
                                    v-if="b.foto"
                                    :src="b.foto"
                                    :alt="b.nama"
                                    class="h-full w-full object-contain p-0.5"
                                    loading="lazy"
                                    referrerpolicy="no-referrer"
                                    @error="(e) => (e.target as HTMLElement).style.display = 'none'"
                                />
                                <ShoppingBag v-else class="h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="font-bold text-sm text-slate-800 truncate dark:text-slate-100">
                                        {{ b.nama }}
                                    </h3>
                                    <button
                                        type="button"
                                        @click="toggleStatus(b)"
                                        :title="b.is_active ? 'Klik untuk menonaktifkan barang' : 'Klik untuk mengaktifkan barang'"
                                        :class="
                                            b.is_active
                                                ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-400 dark:hover:bg-emerald-900/60'
                                                : 'bg-slate-100 text-slate-500 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700'
                                        "
                                        class="shrink-0 rounded-full px-2.5 py-0.5 text-[10px] font-bold transition cursor-pointer"
                                    >
                                        {{ b.is_active ? '● Aktif' : '○ Nonaktif' }}
                                    </button>
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
                                @click="bukaHapusModal(b)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                                <span>Hapus</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Tampilan Tabel -->
            <div
                v-else
                class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="overflow-x-auto">
                    <table class="w-full min-w-190 text-left text-xs text-slate-700 dark:text-slate-200">
                        <thead class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3.5">Produk</th>
                                <th class="px-4 py-3.5">Kategori</th>
                                <th class="px-4 py-3.5 text-right">Harga Beli</th>
                                <th class="px-4 py-3.5 text-right">Harga Jual</th>
                                <th class="px-4 py-3.5 text-right">Margin / Laba</th>
                                <th class="px-4 py-3.5 text-center">Stok</th>
                                <th class="px-4 py-3.5 text-center">Status</th>
                                <th class="px-4 py-3.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                            <tr
                                v-for="b in rows"
                                :key="b.id_barang"
                                class="transition hover:bg-slate-50/60 dark:hover:bg-slate-800/40"
                            >
                                <!-- Produk (Thumbnail, Nama, Barcode, Satuan) -->
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="relative flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-100 bg-slate-50 font-bold text-xs text-blue-700 dark:border-slate-800 dark:bg-slate-800 dark:text-blue-400"
                                        >
                                            <img
                                                v-if="b.foto"
                                                :src="b.foto"
                                                :alt="b.nama"
                                                class="h-full w-full object-contain p-0.5"
                                                loading="lazy"
                                                referrerpolicy="no-referrer"
                                                @error="(e) => (e.target as HTMLElement).style.display = 'none'"
                                            />
                                            <ShoppingBag v-else class="h-4.5 w-4.5" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-bold text-slate-900 dark:text-slate-100">
                                                {{ b.nama }}
                                            </div>
                                            <div class="text-[11px] text-slate-400 dark:text-slate-500">
                                                {{ b.barcode || 'Tanpa Barcode' }} · {{ b.satuan }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <!-- Kategori -->
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-block rounded-lg bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700 dark:bg-blue-950/60 dark:text-blue-400"
                                    >
                                        {{ b.kategori?.nama ?? 'Umum' }}
                                    </span>
                                </td>
                                <!-- Harga Beli -->
                                <td class="px-4 py-3 text-right font-medium text-slate-600 dark:text-slate-300">
                                    {{ rupiah(b.harga_beli) }}
                                </td>
                                <!-- Harga Jual -->
                                <td class="px-4 py-3 text-right font-bold text-blue-700 dark:text-blue-400">
                                    {{ rupiah(b.harga_jual) }}
                                </td>
                                <!-- Margin / Laba -->
                                <td class="px-4 py-3 text-right">
                                    <span
                                        :class="
                                            Number(b.harga_jual) - Number(b.harga_beli) >= 0
                                                ? 'text-emerald-600 dark:text-emerald-400 font-bold'
                                                : 'text-rose-600 dark:text-rose-400 font-bold'
                                        "
                                        class="text-xs"
                                    >
                                        {{ Number(b.harga_jual) - Number(b.harga_beli) >= 0 ? '+' : '' }}{{ rupiah(Number(b.harga_jual) - Number(b.harga_beli)) }}
                                    </span>
                                </td>
                                <!-- Stok -->
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="inline-flex items-center justify-center rounded-full px-2.5 py-0.5 text-xs font-bold"
                                        :class="
                                            b.stok <= 0
                                                ? 'bg-red-50 text-red-600 dark:bg-red-950/60 dark:text-red-400'
                                                : b.stok <= 10
                                                ? 'bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400'
                                                : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                        "
                                    >
                                        {{ b.stok }} {{ b.satuan }}
                                    </span>
                                </td>
                                <!-- Status -->
                                <td class="px-4 py-3 text-center">
                                    <button
                                        type="button"
                                        @click="toggleStatus(b)"
                                        :title="b.is_active ? 'Klik untuk menonaktifkan barang' : 'Klik untuk mengaktifkan barang'"
                                        :class="
                                            b.is_active
                                                ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-400 dark:hover:bg-emerald-900/60'
                                                : 'bg-slate-100 text-slate-500 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700'
                                        "
                                        class="rounded-full px-2.5 py-0.5 text-[10px] font-bold transition cursor-pointer"
                                    >
                                        {{ b.is_active ? '● Aktif' : '○ Nonaktif' }}
                                    </button>
                                </td>
                                <!-- Aksi -->
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center justify-end gap-1.5">
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
                                            @click="bukaHapusModal(b)"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                            <span>Hapus</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
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

                <!-- Foto Produk (Link URL) -->
                <div>
                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                        Foto Produk (Link URL)
                        <span class="text-[11px] font-normal text-slate-400 dark:text-slate-500"> — Opsional</span>
                        <div class="mt-1 relative flex items-center">
                            <input
                                :value="form.foto"
                                type="text"
                                placeholder="Tempel link gambar (.jpg/.png) atau tautan halaman web produk..."
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 pr-28 text-xs sm:text-sm text-slate-800 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500"
                                @input="onFotoInput"
                                @paste="() => setTimeout(() => resolveImageUrl(form.foto, true), 60)"
                            />
                            <div class="absolute right-1.5 top-1/2 -translate-y-1/2 flex items-center gap-1">
                                <button
                                    v-if="form.foto && !form.foto.match(/\.(jpg|jpeg|png|webp|gif|svg|avif)(\?.*)?$/i) && !form.foto.startsWith('data:image/')"
                                    type="button"
                                    :disabled="extractingImage"
                                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-blue-200 bg-blue-50 px-2 py-1 text-[11px] font-bold text-blue-700 hover:bg-blue-100 dark:border-blue-900 dark:bg-blue-950/60 dark:text-blue-300 transition disabled:opacity-50"
                                    title="Ekstrak foto produk dari halaman web"
                                    @click="resolveImageUrl(form.foto, true)"
                                >
                                    <Loader2 v-if="extractingImage" class="h-3 w-3 animate-spin" />
                                    <span>{{ extractingImage ? 'Mengekstrak…' : 'Ekstrak Foto' }}</span>
                                </button>
                                <button
                                    v-if="form.foto"
                                    type="button"
                                    class="cursor-pointer rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700 dark:hover:text-slate-200 transition"
                                    title="Hapus URL foto"
                                    @click="form.foto = ''; fotoPreviewError = false;"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </label>

                    <!-- Live Preview Foto Produk -->
                    <div
                        v-if="form.foto && String(form.foto).trim()"
                        class="mt-2 flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/80 p-2.5 dark:border-slate-700/80 dark:bg-slate-800/50"
                    >
                        <div class="relative h-14 w-14 shrink-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xs dark:border-slate-700 dark:bg-slate-900">
                            <img
                                v-show="!fotoPreviewError"
                                :src="String(form.foto).trim()"
                                alt="Preview Foto Produk"
                                class="h-full w-full object-cover"
                                referrerpolicy="no-referrer"
                                @load="fotoPreviewError = false"
                                @error="fotoPreviewError = true"
                            />
                            <div
                                v-if="fotoPreviewError"
                                class="flex h-full w-full flex-col items-center justify-center p-1 text-center text-rose-500 bg-rose-50 dark:bg-rose-950/40"
                                title="Gagal memuat gambar dari URL ini"
                            >
                                <AlertTriangle class="h-4 w-4 shrink-0" />
                                <span class="text-[9px] font-bold mt-0.5 leading-tight">Gagal</span>
                            </div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-xs font-bold"
                                :class="fotoPreviewError ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'"
                            >
                                {{ fotoPreviewError ? 'Gambar tidak dapat diakses / link tidak valid' : '✓ Pratinjau Foto Berhasil Terhubung' }}
                            </p>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 truncate mt-0.5" :title="form.foto">
                                {{ form.foto }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Kategori & Kelompok (Disatukan) dan Pemasok (Supplier) (2 Kolom) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3">
                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                        Kategori & Kelompok*
                        <select
                            :value="form.id_kategori"
                            required
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-xs sm:text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                            @change="onKategoriChange"
                        >
                            <option :value="undefined" disabled>-- Pilih Kategori & Kelompok --</option>
                            <optgroup
                                v-for="grp in groupedKategori"
                                :key="grp.id_kelompok"
                                :label="grp.nama_kelompok"
                            >
                                <option
                                    v-for="k in grp.items"
                                    :key="k.id_kategori"
                                    :value="k.id_kategori"
                                >
                                    {{ k.nama }}
                                </option>
                            </optgroup>
                        </select>
                    </label>

                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                        Pemasok (Supplier)*
                        <select
                            v-model="form.id_supplier"
                            required
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-xs sm:text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                        >
                            <option :value="undefined" disabled>-- Pilih Pemasok --</option>
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

                <!-- Satuan & Stok Awal (2 Kolom Sejajar & Ramping) -->
                <div class="grid grid-cols-2 gap-2 sm:gap-3">
                    <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                        Satuan Unit*
                        <div class="mt-1">
                            <select
                                v-if="!customSatuanMode"
                                :value="form.satuan || 'pcs'"
                                required
                                class="w-full rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-xs sm:text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                @change="onSatuanChange"
                            >
                                <option v-for="s in satuanList" :key="s" :value="s">{{ s }}</option>
                                <option value="__custom__">+ Lainnya (Ketik Manual)…</option>
                            </select>
                            <div v-else class="relative w-full">
                                <input
                                    v-model="form.satuan"
                                    required
                                    placeholder="Ketik satuan…"
                                    class="w-full rounded-xl border border-blue-500 bg-white px-3 py-2 pr-14 text-xs sm:text-sm text-slate-800 dark:border-blue-500 dark:bg-slate-800 dark:text-slate-100"
                                />
                                <button
                                    type="button"
                                    class="absolute right-1.5 top-1/2 -translate-y-1/2 cursor-pointer rounded-lg px-2 py-1 text-[11px] font-semibold text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950/50"
                                    title="Pilih dari daftar"
                                    @click="customSatuanMode = false; if (!form.satuan) form.satuan = 'pcs';"
                                >
                                    Daftar
                                </button>
                            </div>
                        </div>
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

        <!-- Pop-up Modal Peringatan / Konfirmasi Hapus Barang -->
        <Teleport to="body">
            <div
                v-if="deleteModalOpen && barangToDelete"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
            >
                <!-- Backdrop with smooth blur -->
                <div
                    class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
                    @click="deleteModalOpen = false"
                />

                <!-- Modal Dialog Card -->
                <div
                    class="relative w-full max-w-md overflow-hidden rounded-3xl border border-slate-200/80 bg-white p-6 shadow-2xl transition-all animate-in fade-in zoom-in-95 duration-200 dark:border-slate-800 dark:bg-slate-900"
                >
                    <!-- Close button -->
                    <button
                        type="button"
                        class="absolute top-4 right-4 rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200 transition cursor-pointer"
                        @click="deleteModalOpen = false"
                    >
                        <X class="h-4 w-4 sm:h-5 sm:w-5" />
                    </button>

                    <!-- KONDISI 1: Barang sudah pernah diperjualbelikan & saat ini MASIH AKTIF -->
                    <div v-if="isTradedAndActive" class="flex flex-col items-center text-center">
                        <div
                            class="flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-50 border border-amber-200 text-amber-600 dark:bg-amber-950/40 dark:border-amber-800 dark:text-amber-400 mb-3.5 shadow-xs"
                        >
                            <AlertTriangle class="h-8 w-8" />
                        </div>

                        <h3 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white">
                            Pertimbangkan Lagi
                        </h3>

                        <!-- Kalimat Peringatan Khusus Sesuai Permintaan User -->
                        <div
                            class="mt-3.5 w-full rounded-2xl bg-amber-500/10 border border-amber-500/25 p-3.5 text-amber-900 dark:text-amber-200 text-xs sm:text-sm font-bold leading-relaxed shadow-2xs"
                        >
                            “data barang tersebut masih diperjual belikan, mohon pertimbangkan lagi”
                        </div>

                        <div class="mt-3.5 w-full rounded-2xl bg-slate-50 border border-slate-100 p-3 text-left dark:bg-slate-800/60 dark:border-slate-800">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-500 dark:text-slate-400">Nama Barang:</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200 truncate max-w-[200px]">
                                    {{ barangToDelete.nama }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-xs mt-1.5">
                                <span class="text-slate-500 dark:text-slate-400">Status Saat Ini:</span>
                                <span class="font-bold text-emerald-600 dark:text-emerald-400">
                                    ● Aktif Dijual di Kasir
                                </span>
                            </div>
                        </div>

                        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400 leading-relaxed text-center">
                            Barang ini memiliki histori transaksi. Anda harus <strong>menonaktifkannya terlebih dahulu</strong> agar kasir tidak dapat menjual barang ini lagi sebelum dapat dihapus.
                        </p>

                        <!-- Action Buttons -->
                        <div class="mt-5 flex w-full flex-col-reverse sm:flex-row items-center gap-2">
                            <button
                                type="button"
                                class="w-full sm:flex-1 rounded-xl border border-slate-200 bg-white py-2.5 px-4 text-xs sm:text-sm font-semibold text-slate-700 hover:bg-slate-50 active-press dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 transition cursor-pointer"
                                @click="deleteModalOpen = false"
                            >
                                Batal
                            </button>
                            <button
                                type="button"
                                :disabled="actionLoading"
                                class="w-full sm:flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white py-2.5 px-4 text-xs sm:text-sm font-bold shadow-md shadow-amber-600/20 active-press transition cursor-pointer disabled:opacity-60"
                                @click="nonaktifkanDariModal"
                            >
                                <Power class="h-4 w-4" />
                                <span>{{ actionLoading ? 'Memproses…' : 'Nonaktifkan Sekarang' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- KONDISI 2: Barang sudah Nonaktif ATAU belum pernah diperjualbelikan (Boleh Dihapus) -->
                    <div v-else class="flex flex-col items-center text-center">
                        <div
                            class="flex h-16 w-16 items-center justify-center rounded-2xl bg-rose-50 border border-rose-200 text-rose-600 dark:bg-rose-950/40 dark:border-rose-900/50 dark:text-rose-400 mb-3.5 shadow-xs"
                        >
                            <Trash2 class="h-8 w-8" />
                        </div>

                        <h3 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white">
                            Konfirmasi Hapus Barang
                        </h3>

                        <div class="mt-3.5 w-full rounded-2xl bg-slate-50 border border-slate-100 p-3 text-left dark:bg-slate-800/60 dark:border-slate-800">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-500 dark:text-slate-400">Nama Barang:</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200 truncate max-w-[200px]">
                                    {{ barangToDelete.nama }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-xs mt-1.5">
                                <span class="text-slate-500 dark:text-slate-400">Status Produk:</span>
                                <span class="font-bold text-slate-600 dark:text-slate-300">
                                    {{ isTraded ? '○ Nonaktif (Siap Dihapus)' : 'Barang Baru (Belum Ditransaksikan)' }}
                                </span>
                            </div>
                        </div>

                        <p class="mt-3 text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed text-center">
                            <template v-if="isTraded">
                                Menghapus barang ini akan mengarsipkannya dari katalog master produk, dan seluruh histori laporan transaksi masa lalu tetap tersimpan dengan aman.
                            </template>
                            <template v-else>
                                Yakin ingin menghapus permanen barang ini? Barang belum memiliki riwayat transaksi dan data barcode dapat dipakai kembali.
                            </template>
                        </p>

                        <div class="mt-5 flex w-full flex-col-reverse sm:flex-row items-center gap-2">
                            <button
                                type="button"
                                class="w-full sm:flex-1 rounded-xl border border-slate-200 bg-white py-2.5 px-4 text-xs sm:text-sm font-semibold text-slate-700 hover:bg-slate-50 active-press dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 transition cursor-pointer"
                                @click="deleteModalOpen = false"
                            >
                                Batal
                            </button>
                            <button
                                type="button"
                                :disabled="actionLoading"
                                class="w-full sm:flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white py-2.5 px-4 text-xs sm:text-sm font-bold shadow-md shadow-rose-600/20 active-press transition cursor-pointer disabled:opacity-60"
                                @click="eksekusiHapus"
                            >
                                <Trash2 class="h-4 w-4" />
                                <span>{{ actionLoading ? 'Menghapus…' : 'Ya, Hapus Barang' }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </PosLayout>
</template>
