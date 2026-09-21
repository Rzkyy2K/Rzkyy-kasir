<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 , Tags } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import EmptyState from '@/components/pos/EmptyState.vue';
import Modal from '@/components/pos/Modal.vue';
import PageHeader from '@/components/pos/PageHeader.vue';
import PosLayout from '@/layouts/PosLayout.vue';
import { friendlyError } from '@/services/api';
import {
    createKategori,
    createKelompokKategori,
    deleteKategori,
    deleteKelompokKategori,
    fetchKategori,
    fetchKelompokKategori,
    pindahkanKelompokKategori,
    updateKategori,
    updateKelompokKategori,
} from '@/services/kategoriService';
import { usePosStore } from '@/stores/pos';
import type { Kategori, KelompokKategori } from '@/types/pos';

const pos = usePosStore();
const loading = ref(true);
const kelompok = ref<KelompokKategori[]>([]);
const kategori = ref<Kategori[]>([]);
const filterKelompok = ref<number | null>(null);

const showKelompok = ref(false);
const namaKelompok = ref('');
const editingKelompok = ref<KelompokKategori | null>(null);
const showForm = ref(false);
const editing = ref<Kategori | null>(null);
const form = ref({ id_kelompok: 0 as number, nama: '' });
const saving = ref(false);

const tampil = computed(() =>
    filterKelompok.value
        ? kategori.value.filter((k) => k.id_kelompok === filterKelompok.value)
        : kategori.value,
);

async function load() {
    loading.value = true;
    try {
        kelompok.value = await fetchKelompokKategori(pos.idSekolah);
        kategori.value = (await fetchKategori({ per_page: 100 })).data.filter(
            (k) =>
                kelompok.value.some((kk) => kk.id_kelompok === k.id_kelompok),
        );
    } catch (e) {
        toast.error(friendlyError(e, 'Gagal memuat data kategori.'));
    } finally {
        loading.value = false;
    }
}

async function simpanKelompok() {
    if (!namaKelompok.value.trim()) {
        toast.error('Nama kelompok kategori wajib diisi.');
        return;
    }
    saving.value = true;
    try {
        if (editingKelompok.value) {
            const res = await updateKelompokKategori(
                editingKelompok.value.id_kelompok,
                { nama_kelompok: namaKelompok.value.trim() },
            );
            toast.success(res.message);
        } else {
            const res = await createKelompokKategori({
                id_sekolah: pos.idSekolah,
                nama_kelompok: namaKelompok.value.trim(),
            });
            toast.success(res.message);
        }
        batalEditKelompok();
        await load();
    } catch (e) {
        toast.error(friendlyError(e, 'Gagal menyimpan kelompok kategori.'));
    } finally {
        saving.value = false;
    }
}

function bukaEditKelompok(k: KelompokKategori) {
    editingKelompok.value = k;
    namaKelompok.value = k.nama_kelompok;
}

function batalEditKelompok() {
    editingKelompok.value = null;
    namaKelompok.value = '';
}

async function hapusKelompok(k: KelompokKategori) {
    if (!confirm(`Hapus kelompok kategori "${k.nama_kelompok}"?`)) return;
    try {
        const res = await deleteKelompokKategori(k.id_kelompok);
        toast.success(res.message);
        if (filterKelompok.value === k.id_kelompok) filterKelompok.value = null;
        await load();
    } catch (e) {
        const info = (
            e as {
                response?: { data?: { errors?: Record<string, number> } };
            }
        )?.response?.data?.errors;
        if (info && (info.total ?? 0) > 0) {
            bukaPindahKelompok(k, info);
        } else {
            toast.error(friendlyError(e, 'Gagal menghapus kelompok kategori.'));
        }
    }
}

const showPindah = ref(false);
const pindahKelompok = ref<KelompokKategori | null>(null);
const pindahInfo = ref<Record<string, number>>({});
const pindahTujuan = ref(0);
const pindahLoading = ref(false);

function bukaPindahKelompok(k: KelompokKategori, info: Record<string, number>) {
    pindahKelompok.value = k;
    pindahInfo.value = info;
    pindahTujuan.value =
        kelompok.value.find((x) => x.id_kelompok !== k.id_kelompok)
            ?.id_kelompok ?? 0;
    showPindah.value = true;
}

async function eksekusiPindah() {
    if (!pindahKelompok.value || !pindahTujuan.value) {
        toast.error('Silakan pilih kelompok tujuan pemindahan terlebih dahulu.');
        return;
    }
    pindahLoading.value = true;
    try {
        const res = await pindahkanKelompokKategori(
            pindahKelompok.value.id_kelompok,
            pindahTujuan.value,
        );
        toast.success(res.message);
        showPindah.value = false;
        showKelompok.value = false;
        if (filterKelompok.value === pindahKelompok.value.id_kelompok)
            filterKelompok.value = null;
        await load();
    } catch (e) {
        toast.error(friendlyError(e, 'Gagal memindahkan data ke kelompok tujuan. Silakan coba kembali.'));
    } finally {
        pindahLoading.value = false;
    }
}

function bukaTambah() {
    editing.value = null;
    form.value = { id_kelompok: kelompok.value[0]?.id_kelompok ?? 0, nama: '' };
    showForm.value = true;
}

function bukaEdit(k: Kategori) {
    editing.value = k;
    form.value = { id_kelompok: k.id_kelompok, nama: k.nama };
    showForm.value = true;
}

async function simpan() {
    if (!form.value.id_kelompok) {
        toast.error('Silakan pilih kelompok kategori.');
        return;
    }
    saving.value = true;
    try {
        if (editing.value) {
            const res = await updateKategori(
                editing.value.id_kategori,
                form.value,
            );
            toast.success(res.message);
        } else {
            const res = await createKategori(form.value);
            toast.success(res.message);
        }
        showForm.value = false;
        await load();
    } catch (e) {
        toast.error(friendlyError(e, 'Gagal menyimpan kategori.'));
    } finally {
        saving.value = false;
    }
}

async function hapus(k: Kategori) {
    if (!confirm(`Hapus permanen kategori "${k.nama}"? Kategori yang dihapus tidak dapat dipulihkan kembali.`)) return;
    try {
        const res = await deleteKategori(k.id_kategori);
        toast.success(res.message ?? 'Kategori berhasil dihapus.');
        await load();
    } catch (e) {
        toast.error(friendlyError(e, 'Gagal menghapus kategori.'));
    }
}

onMounted(() => {
    void (async () => {
        await pos.init();
        await load();
    })();
});
</script>

<template>
    <Head title="Kategori" />
    <PosLayout>
        <PageHeader
            title="Kategori & Kelompok"
            :icon="Tags"
            subtitle="Struktur klasifikasi dan pengelompokan produk koperasi"
        >
            <template #actions>
                <button
                    type="button"
                    class="cursor-pointer rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 transition"
                    @click="showKelompok = true"
                >
                    Kelola Kelompok Kategori
                </button>
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white shadow-xs hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500 transition"
                    @click="bukaTambah"
                >
                    <Plus class="h-4 w-4" /> Tambah Kategori Baru
                </button>
            </template>
        </PageHeader>

        <div v-if="loading" class="grid gap-4 sm:grid-cols-2">
            <div
                v-for="i in 4"
                :key="i"
                class="h-16 animate-pulse rounded-xl bg-white dark:bg-slate-900"
            />
        </div>
        <template v-else>
            <div class="flex gap-2 overflow-x-auto pb-1">
                <button
                    type="button"
                    :class="[
                        'shrink-0 cursor-pointer rounded-full border px-4 py-2 text-sm font-semibold transition',
                        filterKelompok === null
                            ? 'border-blue-700 bg-blue-700 text-white shadow-xs dark:border-blue-600 dark:bg-blue-600'
                            : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800',
                    ]"
                    @click="filterKelompok = null"
                >
                    Semua ({{ kategori.length }})
                </button>
                <button
                    v-for="k in kelompok"
                    :key="k.id_kelompok"
                    type="button"
                    :class="[
                        'shrink-0 cursor-pointer rounded-full border px-4 py-2 text-sm font-semibold transition',
                        filterKelompok === k.id_kelompok
                            ? 'border-blue-700 bg-blue-700 text-white shadow-xs dark:border-blue-600 dark:bg-blue-600'
                            : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800',
                    ]"
                    @click="filterKelompok = k.id_kelompok"
                >
                    {{ k.nama_kelompok }}
                </button>
            </div>

            <EmptyState
                v-if="tampil.length === 0"
                class="mt-4"
                title="Belum Ada Kategori"
                message="Belum ada data kategori produk dalam kelompok ini."
            />
            <div v-else class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="k in tampil"
                    :key="k.id_kategori"
                    class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-xs dark:border-slate-800 dark:bg-slate-900"
                >
                    <div>
                        <p class="font-semibold text-slate-800 dark:text-slate-100">{{ k.nama }}</p>
                        <p class="text-xs text-slate-400 dark:text-slate-500">
                            {{
                                k.kelompok?.nama_kelompok ??
                                kelompok.find(
                                    (x) => x.id_kelompok === k.id_kelompok,
                                )?.nama_kelompok
                            }}
                        </p>
                    </div>
                    <div class="flex gap-1">
                        <button
                            type="button"
                            title="Edit Kategori"
                            class="cursor-pointer rounded-lg p-2 text-slate-400 hover:bg-blue-50 hover:text-blue-700 dark:text-slate-500 dark:hover:bg-slate-800 dark:hover:text-blue-400 transition"
                            @click="bukaEdit(k)"
                        >
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            title="Hapus Kategori"
                            class="cursor-pointer rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:text-slate-500 dark:hover:bg-slate-800 dark:hover:text-red-400 transition"
                            @click="hapus(k)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <Modal
            :open="showKelompok"
            title="Kelola Kelompok Kategori"
            @close="showKelompok = false"
        >
            <form class="space-y-2.5" @submit.prevent="simpanKelompok">
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                    >{{
                        editingKelompok
                            ? 'Ubah Nama Kelompok*'
                            : 'Nama Kelompok Baru*'
                    }}
                    <div class="mt-1 flex gap-1.5">
                        <input
                            v-model="namaKelompok"
                            required
                            placeholder="Contoh: Alat Tulis, Makanan, Minuman"
                            class="flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                        />
                        <button
                            type="submit"
                            :disabled="saving"
                            class="shrink-0 cursor-pointer rounded-xl bg-blue-700 px-4 py-2 text-sm font-bold text-white hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500 transition disabled:opacity-60 shadow-xs"
                        >
                            {{
                                saving
                                    ? '…'
                                    : editingKelompok
                                      ? 'Simpan Perubahan'
                                      : '+ Tambah'
                            }}
                        </button>
                    </div>
                </label>
                <button
                    v-if="editingKelompok"
                    type="button"
                    class="cursor-pointer text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
                    @click="batalEditKelompok"
                >
                    Batal Edit
                </button>
            </form>

            <div class="mt-4 space-y-2">
                <p class="text-xs font-medium text-slate-500 uppercase dark:text-slate-400">
                    Daftar Kelompok Kategori ({{ kelompok.length }})
                </p>
                <EmptyState
                    v-if="kelompok.length === 0"
                    title="Belum Ada Kelompok Kategori"
                    message="Tambahkan kelompok kategori baru pada formulir di atas."
                />
                <div
                    v-for="k in kelompok"
                    :key="k.id_kelompok"
                    class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 dark:border-slate-800 dark:bg-slate-800/50"
                >
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                        {{ k.nama_kelompok }}
                    </p>
                    <div class="flex gap-1">
                        <button
                            type="button"
                            title="Edit"
                            class="cursor-pointer rounded-lg p-2 text-slate-400 hover:bg-blue-100 hover:text-blue-700 dark:text-slate-500 dark:hover:bg-slate-700 dark:hover:text-blue-400 transition"
                            @click="bukaEditKelompok(k)"
                        >
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            title="Hapus"
                            class="cursor-pointer rounded-lg p-2 text-slate-400 hover:bg-red-100 hover:text-red-600 dark:text-slate-500 dark:hover:bg-slate-700 dark:hover:text-red-400 transition"
                            @click="hapusKelompok(k)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 dark:text-slate-500">
                    Kelompok yang masih memiliki kategori atau produk terkait tidak dapat langsung dihapus. Silakan pindahkan datanya terlebih dahulu.
                </p>
            </div>
        </Modal>

        <Modal
            :open="showPindah"
            title="Pindahkan & Hapus Kelompok"
            @close="showPindah = false"
        >
            <div class="space-y-3 text-sm text-slate-600 dark:text-slate-300">
                <p>
                    Kelompok
                    <strong class="text-slate-900 dark:text-white">{{
                        pindahKelompok?.nama_kelompok
                    }}</strong>
                    masih menahan data berikut (termasuk data nonaktif yang
                    tidak tampil di daftar):
                </p>
                <ul class="list-inside list-disc space-y-0.5 text-sm">
                    <li>{{ pindahInfo.kategori_aktif ?? 0 }} kategori aktif</li>
                    <li>{{ pindahInfo.barang_aktif ?? 0 }} barang aktif</li>
                    <li v-if="(pindahInfo.nonaktif ?? 0) > 0">
                        {{ pindahInfo.nonaktif }} data nonaktif
                    </li>
                </ul>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-300"
                    >Pindahkan semua ke kelompok*
                    <select
                        v-model="pindahTujuan"
                        class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    >
                        <option
                            v-for="k in kelompok.filter(
                                (x) =>
                                    x.id_kelompok !==
                                    pindahKelompok?.id_kelompok,
                            )"
                            :key="k.id_kelompok"
                            :value="k.id_kelompok"
                        >
                            {{ k.nama_kelompok }}
                        </option>
                    </select>
                </label>
                <p
                    v-if="
                        kelompok.filter(
                            (x) =>
                                x.id_kelompok !== pindahKelompok?.id_kelompok,
                        ).length === 0
                    "
                    class="rounded-xl bg-amber-50 p-3 text-xs text-amber-700 dark:border dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-300"
                >
                    Buat kelompok lain terlebih dahulu sebelum memindahkan.
                </p>
                <div class="flex gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button
                        type="button"
                        class="flex-1 cursor-pointer rounded-xl border border-slate-200 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 transition"
                        @click="showPindah = false"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        :disabled="pindahLoading || !pindahTujuan"
                        class="flex-1 cursor-pointer rounded-xl bg-blue-700 py-2.5 text-sm font-bold text-white hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500 transition disabled:opacity-60 shadow-xs"
                        @click="eksekusiPindah"
                    >
                        {{ pindahLoading ? 'Memproses…' : 'Pindahkan & Hapus' }}
                    </button>
                </div>
            </div>
        </Modal>

        <Modal
            :open="showForm"
            :title="editing ? 'Edit Kategori' : 'Tambah Kategori Baru'"
            @close="showForm = false"
        >
            <form class="space-y-3" @submit.prevent="simpan">
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                    >Kelompok Kategori*
                    <select
                        v-model="form.id_kelompok"
                        required
                        class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    >
                        <option
                            v-for="k in kelompok"
                            :key="k.id_kelompok"
                            :value="k.id_kelompok"
                        >
                            {{ k.nama_kelompok }}
                        </option>
                    </select>
                </label>
                <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                    >Nama Kategori*
                    <input
                        v-model="form.nama"
                        required
                        placeholder="Contoh: Buku Tulis, Pulpen, Seragam"
                        class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    />
                </label>
                <button
                    type="submit"
                    :disabled="saving"
                    class="w-full cursor-pointer rounded-xl bg-blue-700 py-2.5 text-sm font-bold text-white hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500 transition disabled:opacity-60 shadow-xs"
                >
                    {{ saving ? 'Menyimpan…' : editing ? 'Simpan Perubahan' : 'Simpan Kategori' }}
                </button>
            </form>
        </Modal>
    </PosLayout>
</template>
