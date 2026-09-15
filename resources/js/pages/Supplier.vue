<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 , Store } from '@lucide/vue';
import { onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import EmptyState from '@/components/pos/EmptyState.vue';
import Modal from '@/components/pos/Modal.vue';
import PageHeader from '@/components/pos/PageHeader.vue';
import Pagination from '@/components/pos/Pagination.vue';
import SearchBar from '@/components/pos/SearchBar.vue';
import PosLayout from '@/layouts/PosLayout.vue';
import { friendlyError } from '@/services/api';
import {
    createSupplier,
    deleteSupplier,
    fetchSupplier,
    updateSupplier,
} from '@/services/supplierService';
import { usePosStore } from '@/stores/pos';
import type { Supplier } from '@/types/pos';

const pos = usePosStore();
const loading = ref(true);
const rows = ref<Supplier[]>([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const search = ref('');

const showForm = ref(false);
const editing = ref<Supplier | null>(null);
const saving = ref(false);
const form = ref({ nama: '', no_telepon: '', alamat_supplier: '' });

let timer: ReturnType<typeof setTimeout> | null = null;

async function load() {
    loading.value = true;
    try {
        const res = await fetchSupplier({
            id_sekolah: pos.idSekolah,
            search: search.value || undefined,
            per_page: 12,
            page: page.value,
        });
        rows.value = res.data;
        page.value = res.current_page;
        lastPage.value = res.last_page;
        total.value = res.total;
    } catch (e) {
        toast.error(friendlyError(e, 'Daftar supplier gagal dimuat.'));
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
    form.value = { nama: '', no_telepon: '', alamat_supplier: '' };
    showForm.value = true;
}

function bukaEdit(s: Supplier) {
    editing.value = s;
    form.value = {
        nama: s.nama,
        no_telepon: s.no_telepon ?? '',
        alamat_supplier: s.alamat_supplier ?? '',
    };
    showForm.value = true;
}

async function simpan() {
    saving.value = true;
    try {
        if (editing.value) {
            const res = await updateSupplier(
                editing.value.id_supplier,
                form.value,
            );
            toast.success(res.message);
        } else {
            const res = await createSupplier({
                ...form.value,
                id_sekolah: pos.idSekolah,
            });
            toast.success(res.message);
        }
        showForm.value = false;
        await load();
    } catch (e) {
        toast.error(
            friendlyError(e, 'Supplier gagal disimpan. Silakan coba lagi.'),
        );
    } finally {
        saving.value = false;
    }
}

async function hapus(s: Supplier) {
    if (!confirm(`Hapus permanen supplier "${s.nama}"?`)) return;
    try {
        const res = await deleteSupplier(s.id_supplier);
        toast.success(res.message ?? 'Supplier berhasil dihapus.');
        await load();
    } catch (e) {
        toast.error(friendlyError(e, 'Supplier gagal dihapus.'));
    }
}

onMounted(() => {
    void (async () => {
        await pos.init();
        await load();
    })();
});
watch(
    () => pos.idSekolah,
    () => {
        page.value = 1;
        void load();
    },
);
</script>

<template>
    <Head title="Supplier" />
    <PosLayout>
        <PageHeader
            title="Supplier"
            :icon="Store"
            subtitle="Mitra pemasok barang"
        >
            <template #actions>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-800"
                    @click="bukaTambah"
                >
                    <Plus class="h-4 w-4" /> Tambah Supplier
                </button>
            </template>
        </PageHeader>

        <SearchBar
            v-model="search"
            placeholder="Cari supplier…"
            @update:model-value="onSearch"
        />

        <div v-if="loading" class="mt-4 space-y-2">
            <div
                v-for="i in 4"
                :key="i"
                class="h-16 animate-pulse rounded-xl bg-white"
            />
        </div>
        <EmptyState
            v-else-if="rows.length === 0"
            class="mt-4"
            title="Belum ada supplier"
        />
        <div v-else class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="s in rows"
                :key="s.id_supplier"
                class="rounded-2xl border border-slate-200 bg-white p-4"
            >
                <div class="flex items-start justify-between gap-1.5">
                    <div>
                        <p class="font-bold text-slate-800">{{ s.nama }}</p>
                        <p class="text-sm text-slate-500">
                            {{ s.no_telepon ?? '—' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ s.alamat_supplier ?? '' }}
                        </p>
                    </div>
                    <div class="flex gap-1">
                        <button
                            type="button"
                            class="rounded-lg p-2 text-slate-400 hover:bg-blue-50 hover:text-blue-700"
                            @click="bukaEdit(s)"
                        >
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
                            @click="hapus(s)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
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
            :title="editing ? 'Edit Supplier' : 'Tambah Supplier'"
            @close="showForm = false"
        >
            <form class="space-y-2.5" @submit.prevent="simpan">
                <label class="text-xs font-medium text-slate-600"
                    >Nama supplier*
                    <input
                        v-model="form.nama"
                        required
                        class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    />
                </label>
                <label class="text-xs font-medium text-slate-600"
                    >No. telepon
                    <input
                        v-model="form.no_telepon"
                        class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    />
                </label>
                <label class="text-xs font-medium text-slate-600"
                    >Alamat
                    <textarea
                        v-model="form.alamat_supplier"
                        rows="2"
                        class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    />
                </label>
                <button
                    type="submit"
                    :disabled="saving"
                    class="w-full rounded-xl bg-blue-700 py-2.5 text-sm font-bold text-white disabled:opacity-60"
                >
                    Simpan
                </button>
            </form>
        </Modal>
    </PosLayout>
</template>
