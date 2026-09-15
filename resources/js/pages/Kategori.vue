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
        toast.error(friendlyError(e, 'Data kategori gagal dimuat.'));
    } finally {
        loading.value = false;
    }
}

async function simpanKelompok() {
    if (!namaKelompok.value.trim()) {
        toast.error('Nama kelompok wajib diisi.');
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
        toast.error(friendlyError(e, 'Kelompok gagal disimpan.'));
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
    if (!confirm(`Hapus kelompok "${k.nama_kelompok}"?`)) return;
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
            toast.error(friendlyError(e, 'Kelompok gagal dihapus.'));
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
        toast.error('Pilih kelompok tujuan terlebih dahulu.');
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
        toast.error(friendlyError(e, 'Pemindahan gagal. Silakan coba lagi.'));
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
        toast.error('Pilih kelompok kategori.');
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
        toast.error(friendlyError(e, 'Kategori gagal disimpan.'));
    } finally {
        saving.value = false;
    }
}

async function hapus(k: Kategori) {
    if (!confirm(`Hapus permanen kategori "${k.nama}"? Data & kode akan hilang dan bisa dipakai lagi.`)) return;
    try {
        const res = await deleteKategori(k.id_kategori);
        toast.success(res.message ?? 'Kategori berhasil dihapus.');
        await load();
    } catch (e) {
        toast.error(friendlyError(e, 'Kategori gagal dihapus.'));
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
            subtitle="Data berasal dari API/database"
        >
            <template #actions>
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold hover:border-blue-300"
                    @click="showKelompok = true"
                >
                    Kelola Kelompok
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-800"
                    @click="bukaTambah"
                >
                    <Plus class="h-4 w-4" /> Tambah Kategori
                </button>
            </template>
        </PageHeader>

        <div v-if="loading" class="grid gap-4 sm:grid-cols-2">
            <div
                v-for="i in 4"
                :key="i"
                class="h-16 animate-pulse rounded-xl bg-white"
            />
        </div>
        <template v-else>
            <div class="flex gap-2 overflow-x-auto pb-1">
                <button
                    type="button"
                    :class="[
                        'shrink-0 rounded-full border px-4 py-2 text-sm font-medium',
                        filterKelompok === null
                            ? 'border-blue-700 bg-blue-700 text-white'
                            : 'border-slate-200 bg-white',
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
                        'shrink-0 rounded-full border px-4 py-2 text-sm font-medium',
                        filterKelompok === k.id_kelompok
                            ? 'border-blue-700 bg-blue-700 text-white'
                            : 'border-slate-200 bg-white',
                    ]"
                    @click="filterKelompok = k.id_kelompok"
                >
                    {{ k.nama_kelompok }}
                </button>
            </div>

            <EmptyState
                v-if="tampil.length === 0"
                class="mt-4"
                title="Belum ada kategori"
            />
            <div v-else class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="k in tampil"
                    :key="k.id_kategori"
                    class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4"
                >
                    <div>
                        <p class="font-semibold text-slate-800">{{ k.nama }}</p>
                        <p class="text-xs text-slate-400">
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
                            class="rounded-lg p-2 text-slate-400 hover:bg-blue-50 hover:text-blue-700"
                            @click="bukaEdit(k)"
                        >
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
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
                <label class="text-xs font-medium text-slate-600"
                    >{{
                        editingKelompok
                            ? 'Ubah nama kelompok*'
                            : 'Nama kelompok baru*'
                    }}
                    <div class="mt-1 flex gap-1.5">
                        <input
                            v-model="namaKelompok"
                            required
                            placeholder="cth: Alat Tulis"
                            class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm"
                        />
                        <button
                            type="submit"
                            :disabled="saving"
                            class="shrink-0 rounded-xl bg-blue-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-60"
                        >
                            {{
                                saving
                                    ? '…'
                                    : editingKelompok
                                      ? 'Ubah'
                                      : '+ Tambah'
                            }}
                        </button>
                    </div>
                </label>
                <button
                    v-if="editingKelompok"
                    type="button"
                    class="text-xs font-semibold text-slate-500 hover:text-slate-700"
                    @click="batalEditKelompok"
                >
                    Batalkan ubahan
                </button>
            </form>

            <div class="mt-4 space-y-2">
                <p class="text-xs font-medium text-slate-500 uppercase">
                    Daftar kelompok ({{ kelompok.length }})
                </p>
                <EmptyState
                    v-if="kelompok.length === 0"
                    title="Belum ada kelompok"
                />
                <div
                    v-for="k in kelompok"
                    :key="k.id_kelompok"
                    class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 px-3 py-2"
                >
                    <p class="text-sm font-semibold text-slate-700">
                        {{ k.nama_kelompok }}
                    </p>
                    <div class="flex gap-1">
                        <button
                            type="button"
                            title="Ubah"
                            class="rounded-lg p-2 text-slate-400 hover:bg-blue-100 hover:text-blue-700"
                            @click="bukaEditKelompok(k)"
                        >
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            title="Hapus"
                            class="rounded-lg p-2 text-slate-400 hover:bg-red-100 hover:text-red-600"
                            @click="hapusKelompok(k)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>
                <p class="text-[11px] text-slate-400">
                    Kelompok yang masih dipakai kategori/barang tidak dapat
                    dihapus langsung — pindahkan dulu datanya.
                </p>
            </div>
        </Modal>

        <Modal
            :open="showPindah"
            title="Pindahkan & Hapus Kelompok"
            @close="showPindah = false"
        >
            <div class="space-y-3 text-sm text-slate-600">
                <p>
                    Kelompok
                    <strong class="text-slate-900">{{
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
                <label class="block text-xs font-medium text-slate-600"
                    >Pindahkan semua ke kelompok*
                    <select
                        v-model="pindahTujuan"
                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
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
                    class="rounded-xl bg-amber-50 p-3 text-xs text-amber-700"
                >
                    Buat kelompok lain terlebih dahulu sebelum memindahkan.
                </p>
                <div class="flex gap-1.5">
                    <button
                        type="button"
                        class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-semibold"
                        @click="showPindah = false"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        :disabled="pindahLoading || !pindahTujuan"
                        class="flex-1 rounded-xl bg-blue-700 py-2.5 text-sm font-bold text-white disabled:opacity-60"
                        @click="eksekusiPindah"
                    >
                        {{ pindahLoading ? 'Memproses…' : 'Pindahkan & Hapus' }}
                    </button>
                </div>
            </div>
        </Modal>

        <Modal
            :open="showForm"
            :title="editing ? 'Edit Kategori' : 'Tambah Kategori'"
            @close="showForm = false"
        >
            <form class="space-y-2.5" @submit.prevent="simpan">
                <label class="text-xs font-medium text-slate-600"
                    >Kelompok*
                    <select
                        v-model="form.id_kelompok"
                        required
                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
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
                <label class="text-xs font-medium text-slate-600"
                    >Nama kategori*
                    <input
                        v-model="form.nama"
                        required
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
