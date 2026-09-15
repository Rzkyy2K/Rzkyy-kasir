<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Pencil, Plus, Power , ShoppingBag } from '@lucide/vue';
import { onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
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
            subtitle="Tambah, ubah, dan hapus barang dagangan"
        >
            <template #actions>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-800"
                    @click="bukaTambah"
                >
                    <Plus class="h-4 w-4" /> Tambah Barang
                </button>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-3 sm:flex-row">
            <div class="flex-1">
                <SearchBar
                    v-model="search"
                    placeholder="Cari nama / barcode…"
                    @update:model-value="onSearch"
                />
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
                class="h-16 animate-pulse rounded-xl bg-white"
            />
        </div>
        <EmptyState
            v-else-if="rows.length === 0"
            class="mt-4"
            title="Belum ada produk"
            message="Klik Tambah Barang untuk membuat data baru di database."
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
                        <th class="px-4 py-3">Barang</th>
                        <th class="px-4 py-3">Kategori</th>
                        <th class="px-4 py-3 text-right">Harga Beli</th>
                        <th class="px-4 py-3 text-right">Harga Jual</th>
                        <th class="px-4 py-3 text-right">Stok</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="b in rows"
                        :key="b.id_barang"
                        class="border-b border-slate-50 hover:bg-slate-50"
                    >
                        <td class="px-4 py-3">
                            <p class="font-semibold text-slate-800">
                                {{ b.nama }}
                            </p>
                            <p class="text-xs text-slate-400">
                                {{ b.barcode ?? '—' }} · {{ b.satuan }}
                            </p>
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ b.kategori?.nama ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            {{ rupiah(b.harga_beli) }}
                        </td>
                        <td class="px-4 py-3 text-right font-semibold">
                            {{ rupiah(b.harga_jual) }}
                        </td>
                        <td
                            class="px-4 py-3 text-right font-bold"
                            :class="b.stok <= 10 ? 'text-red-500' : ''"
                        >
                            {{ b.stok }}
                        </td>
                        <td class="px-4 py-3">
                            <span
                                :class="
                                    b.is_active
                                        ? 'bg-emerald-50 text-emerald-600'
                                        : 'bg-slate-100 text-slate-500'
                                "
                                class="rounded-full px-2 py-0.5 text-xs font-semibold"
                            >
                                {{ b.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1">
                                <button
                                    type="button"
                                    class="rounded-lg p-2 text-slate-400 hover:bg-blue-50 hover:text-blue-700"
                                    title="Edit"
                                    @click="bukaEdit(b)"
                                >
                                    <Pencil class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                    title="Hapus permanen"
                                    @click="nonaktifkan(b)"
                                >
                                    <Power class="h-4 w-4" />
                                </button>
                            </div>
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
            :open="showForm"
            :title="editing ? 'Edit Barang' : 'Tambah Barang'"
            wide
            @close="showForm = false"
        >
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="simpan">
                <label class="text-xs font-medium text-slate-600"
                    >Nama barang*
                    <input
                        v-model="form.nama"
                        required
                        class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm"
                    />
                </label>
                <label class="text-xs font-medium text-slate-600"
                    >Barcode
                    <input
                        v-model="form.barcode"
                        class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm"
                    />
                </label>
                <label class="text-xs font-medium text-slate-600"
                    >Kategori*
                    <select
                        v-model="form.id_kategori"
                        required
                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm"
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
                <label class="text-xs font-medium text-slate-600"
                    >Kelompok*
                    <select
                        v-model="form.id_kelompok_kategori"
                        required
                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm"
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
                <label class="text-xs font-medium text-slate-600"
                    >Supplier*
                    <select
                        v-model="form.id_supplier"
                        required
                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm"
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
                <label class="text-xs font-medium text-slate-600"
                    >Satuan*
                    <input
                        v-model="form.satuan"
                        required
                        class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm"
                    />
                </label>
                <label class="text-xs font-medium text-slate-600"
                    >Harga beli*
                    <input
                        v-model.number="form.harga_beli"
                        type="number"
                        min="0"
                        required
                        class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm"
                    />
                </label>
                <label class="text-xs font-medium text-slate-600"
                    >Harga jual*
                    <input
                        v-model.number="form.harga_jual"
                        type="number"
                        min="0"
                        required
                        class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm"
                    />
                </label>
                <label class="text-xs font-medium text-slate-600"
                    >Stok awal*
                    <input
                        v-model.number="form.stok"
                        type="number"
                        min="0"
                        required
                        :disabled="!!editing"
                        class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm disabled:bg-slate-50"
                    />
                </label>
                <label class="flex items-center gap-1.5 text-sm text-slate-600">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-4 w-4 accent-blue-700"
                    />
                    Aktif dijual
                </label>
                <div class="flex gap-1.5 sm:col-span-2">
                    <button
                        type="button"
                        class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-semibold"
                        @click="showForm = false"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="flex-1 rounded-xl bg-blue-700 py-2.5 text-sm font-bold text-white disabled:opacity-60"
                    >
                        {{ saving ? 'Menyimpan…' : 'Simpan' }}
                    </button>
                </div>
            </form>
        </Modal>
    </PosLayout>
</template>
