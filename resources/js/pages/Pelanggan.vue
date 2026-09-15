<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 , Users } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import EmptyState from '@/components/pos/EmptyState.vue';
import Modal from '@/components/pos/Modal.vue';
import PageHeader from '@/components/pos/PageHeader.vue';
import Pagination from '@/components/pos/Pagination.vue';
import SearchBar from '@/components/pos/SearchBar.vue';
import PosLayout from '@/layouts/PosLayout.vue';
import { friendlyError } from '@/services/api';
import {
    createPelanggan,
    deletePelanggan,
    fetchKelompokPelanggan,
    fetchPelanggan,
    updatePelanggan,
} from '@/services/pelangganService';
import { usePosStore } from '@/stores/pos';
import type { KelompokPelanggan, Pelanggan } from '@/types/pos';

const pos = usePosStore();
const loading = ref(true);
const rows = ref<Pelanggan[]>([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const search = ref('');
const kelompok = ref<KelompokPelanggan[]>([]);

const showForm = ref(false);
const editing = ref<Pelanggan | null>(null);
const saving = ref(false);
const form = ref({
    nama_pelanggan: '',
    telepon: '',
    alamat: '',
    id_kelompok_pelanggan: null as number | null,
});

let timer: ReturnType<typeof setTimeout> | null = null;

async function load() {
    loading.value = true;
    try {
        const res = await fetchPelanggan({
            search: search.value || undefined,
            per_page: 12,
            page: page.value,
        });
        rows.value = res.data;
        page.value = res.current_page;
        lastPage.value = res.last_page;
        total.value = res.total;
    } catch (e) {
        toast.error(friendlyError(e, 'Daftar pelanggan gagal dimuat.'));
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
        nama_pelanggan: '',
        telepon: '',
        alamat: '',
        id_kelompok_pelanggan: null,
    };
    showForm.value = true;
}

function bukaEdit(p: Pelanggan) {
    editing.value = p;
    form.value = {
        nama_pelanggan: p.nama_pelanggan,
        telepon: p.telepon ?? '',
        alamat: p.alamat ?? '',
        id_kelompok_pelanggan: p.id_kelompok_pelanggan ?? null,
    };
    showForm.value = true;
}

async function simpan() {
    saving.value = true;
    try {
        if (editing.value) {
            const res = await updatePelanggan(
                editing.value.id_pelanggan,
                form.value,
            );
            toast.success(res.message);
        } else {
            const res = await createPelanggan(form.value);
            toast.success(res.message);
        }
        showForm.value = false;
        await load();
    } catch (e) {
        toast.error(
            friendlyError(e, 'Pelanggan gagal disimpan. Silakan coba lagi.'),
        );
    } finally {
        saving.value = false;
    }
}

async function hapus(p: Pelanggan) {
    if (!confirm(`Hapus permanen pelanggan "${p.nama_pelanggan}"?`)) return;
    try {
        const res = await deletePelanggan(p.id_pelanggan);
        toast.success(res.message ?? 'Pelanggan berhasil dihapus.');
        await load();
    } catch (e) {
        toast.error(friendlyError(e, 'Pelanggan gagal dihapus.'));
    }
}

onMounted(() => {
    void (async () => {
        await pos.init();
        try {
            kelompok.value = await fetchKelompokPelanggan(pos.idSekolah);
        } catch {
            /* opsional */
        }
        await load();
    })();
});
</script>

<template>
    <Head title="Pelanggan" />
    <PosLayout>
        <PageHeader
            title="Pelanggan"
            :icon="Users"
            subtitle="Data pembeli / siswa"
        >
            <template #actions>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-800"
                    @click="bukaTambah"
                >
                    <Plus class="h-4 w-4" /> Tambah Pelanggan
                </button>
            </template>
        </PageHeader>

        <SearchBar
            v-model="search"
            placeholder="Cari nama / telepon…"
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
            title="Belum ada pelanggan"
        />
        <div v-else class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="p in rows"
                :key="p.id_pelanggan"
                class="rounded-2xl border border-slate-200 bg-white p-4"
            >
                <div class="flex items-start justify-between gap-1.5">
                    <div>
                        <p class="font-bold text-slate-800">
                            {{ p.nama_pelanggan }}
                        </p>
                        <p class="text-sm text-slate-500">
                            {{ p.telepon ?? '—' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ p.kelompok?.nama_kelompok ?? '' }}
                            {{ p.alamat ?? '' }}
                        </p>
                    </div>
                    <div class="flex gap-1">
                        <button
                            type="button"
                            class="rounded-lg p-2 text-slate-400 hover:bg-blue-50 hover:text-blue-700"
                            @click="bukaEdit(p)"
                        >
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
                            @click="hapus(p)"
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
            :title="editing ? 'Edit Pelanggan' : 'Tambah Pelanggan'"
            @close="showForm = false"
        >
            <form class="space-y-2.5" @submit.prevent="simpan">
                <label class="text-xs font-medium text-slate-600"
                    >Nama pelanggan*
                    <input
                        v-model="form.nama_pelanggan"
                        required
                        class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    />
                </label>
                <label class="text-xs font-medium text-slate-600"
                    >Telepon
                    <input
                        v-model="form.telepon"
                        class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    />
                </label>
                <label class="text-xs font-medium text-slate-600"
                    >Kelompok
                    <select
                        v-model="form.id_kelompok_pelanggan"
                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                    >
                        <option :value="null">— Umum —</option>
                        <option
                            v-for="k in kelompok"
                            :key="k.id_kelompok_pelanggan"
                            :value="k.id_kelompok_pelanggan"
                        >
                            {{ k.nama_kelompok }}
                        </option>
                    </select>
                </label>
                <label class="text-xs font-medium text-slate-600"
                    >Alamat
                    <textarea
                        v-model="form.alamat"
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
