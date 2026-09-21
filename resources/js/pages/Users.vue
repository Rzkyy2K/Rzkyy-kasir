<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Pencil, Plus, Power, RotateCcw, School, Users } from '@lucide/vue';
import { onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import EmptyState from '@/components/pos/EmptyState.vue';
import Modal from '@/components/pos/Modal.vue';
import Pagination from '@/components/pos/Pagination.vue';
import PosLayout from '@/layouts/PosLayout.vue';
import { friendlyError } from '@/services/api';
import {
    createPosUser,
    createSekolah,
    deletePosUser,
    deleteSekolah,
    fetchPosUsers,
    fetchRoles,
    fetchSekolah,
    updatePosUser,
    updateSekolah,
} from '@/services/masterService';
import { usePosStore } from '@/stores/pos';
import type { PosUser, Role, Sekolah } from '@/types/pos';

const pos = usePosStore();
const roles = ref<Role[]>([]);

// ----- Tab -----
const tab = ref<'user' | 'sekolah'>('user');

// ----- User state -----
const loading = ref(true);
const rows = ref<PosUser[]>([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const showForm = ref(false);
const editing = ref<PosUser | null>(null);
const saving = ref(false);
const form = ref({
    nama_lengkap: '',
    username: '',
    password: '',
    id_role: 3,
    is_active: true,
});

// ----- Sekolah state -----
const loadingSekolah = ref(true);
const rowsSekolah = ref<Sekolah[]>([]);
const showFormSekolah = ref(false);
const editingSekolah = ref<Sekolah | null>(null);
const savingSekolah = ref(false);
const formSekolah = ref({
    kode_sekolah: '',
    nama_sekolah: '',
    alamat_sekolah: '',
    alamat: '',
    website: '',
    is_active: true,
});
const buatAkun = ref(true);
const akun = ref({ nama_lengkap: '', username: '', password: '' });

async function load() {
    loading.value = true;
    try {
        const res = await fetchPosUsers({
            id_sekolah: pos.idSekolah,
            per_page: 12,
            page: page.value,
        });
        rows.value = res.data;
        page.value = res.current_page;
        lastPage.value = res.last_page;
        total.value = res.total;
    } catch (e) {
        toast.error(friendlyError(e, 'Gagal memuat daftar pengguna.'));
    } finally {
        loading.value = false;
    }
}

async function loadSekolah() {
    loadingSekolah.value = true;
    try {
        rowsSekolah.value = await fetchSekolah(true);
    } catch (e) {
        toast.error(friendlyError(e, 'Gagal memuat daftar instansi sekolah.'));
    } finally {
        loadingSekolah.value = false;
    }
}

function bukaTambah() {
    editing.value = null;
    form.value = {
        nama_lengkap: '',
        username: '',
        password: '',
        id_role: 3,
        is_active: true,
    };
    showForm.value = true;
}

function bukaEdit(u: PosUser) {
    editing.value = u;
    form.value = {
        nama_lengkap: u.nama_lengkap,
        username: u.username,
        password: '',
        id_role: u.id_role,
        is_active: !!u.is_active,
    };
    showForm.value = true;
}

async function simpan() {
    saving.value = true;
    try {
        const payload: Record<string, unknown> = {
            ...form.value,
            id_sekolah: pos.idSekolah,
        };
        if (editing.value) {
            if (!payload.password) delete payload.password;
            const res = await updatePosUser(editing.value.id_user, payload);
            toast.success(res.message);
        } else {
            const res = await createPosUser(payload);
            toast.success(res.message);
        }
        showForm.value = false;
        await load();
        await pos.loadUsers();
    } catch (e) {
        toast.error(
            friendlyError(e, 'Gagal menyimpan data pengguna. Silakan coba kembali.'),
        );
    } finally {
        saving.value = false;
    }
}

async function nonaktifkan(u: PosUser) {
    if (!confirm(`Hapus permanen pengguna "${u.nama_lengkap}" (${u.username})? Tindakan ini tidak dapat dibatalkan.`)) return;
    try {
        const res = await deletePosUser(u.id_user);
        toast.success(res.message ?? 'Pengguna berhasil dihapus.');
        await load();
    } catch (e) {
        toast.error(friendlyError(e, 'Gagal menghapus pengguna.'));
    }
}

// ----- Sekolah funcs -----
function bukaTambahSekolah() {
    editingSekolah.value = null;
    formSekolah.value = {
        kode_sekolah: '',
        nama_sekolah: '',
        alamat_sekolah: '',
        alamat: '',
        website: '',
        is_active: true,
    };
    buatAkun.value = true;
    akun.value = { nama_lengkap: '', username: '', password: '' };
    showFormSekolah.value = true;
}

function bukaEditSekolah(s: Sekolah) {
    editingSekolah.value = s;
    formSekolah.value = {
        kode_sekolah: s.kode_sekolah,
        nama_sekolah: s.nama_sekolah,
        alamat_sekolah: s.alamat_sekolah ?? '',
        alamat: s.alamat ?? '',
        website: s.website ?? '',
        is_active: s.is_active ?? true,
    };
    showFormSekolah.value = true;
}

async function simpanSekolah() {
    savingSekolah.value = true;
    try {
        const payload: Record<string, unknown> = {
            kode_sekolah: formSekolah.value.kode_sekolah.trim(),
            nama_sekolah: formSekolah.value.nama_sekolah.trim(),
            alamat_sekolah: formSekolah.value.alamat_sekolah.trim() || null,
            alamat: formSekolah.value.alamat.trim() || null,
            website: formSekolah.value.website.trim() || null,
            is_active: formSekolah.value.is_active,
        };
        if (editingSekolah.value) {
            const res = await updateSekolah(editingSekolah.value.id_sekolah, payload);
            toast.success(res.message);
        } else {
            const res = await createSekolah(payload);
            if (buatAkun.value) {
                const superRole =
                    roles.value.find((r) => r.nama_role === 'super admin') ??
                    roles.value.find((r) => r.id_role === 1);
                if (!superRole) {
                    toast.error('Peran super admin tidak ditemukan, akun super admin batal dibuat.');
                } else {
                    await createPosUser({
                        id_sekolah: res.data.id_sekolah,
                        id_role: superRole.id_role,
                        nama_lengkap: akun.value.nama_lengkap.trim(),
                        username: akun.value.username.trim(),
                        password: akun.value.password,
                        is_active: true,
                    });
                    toast.success(
                        `${res.message} Akun super admin ${akun.value.username.trim()} siap dipakai login.`,
                    );
                }
            } else {
                toast.success(res.message);
            }
        }
        showFormSekolah.value = false;
        await loadSekolah();
        await pos.refreshSekolah();
    } catch (e) {
        toast.error(
            friendlyError(e, 'Gagal menyimpan data sekolah. Silakan coba kembali.'),
        );
    } finally {
        savingSekolah.value = false;
    }
}

async function hapusSekolah(s: Sekolah) {
    if (!confirm(`Hapus data instansi sekolah "${s.nama_sekolah}" (${s.kode_sekolah})? Tindakan ini tidak dapat dibatalkan.`)) return;
    try {
        const res = await deleteSekolah(s.id_sekolah);
        toast.success(res.message);
        await loadSekolah();
        await pos.refreshSekolah();
    } catch (e) {
        toast.error(friendlyError(e, 'Gagal menghapus data sekolah.'));
    }
}

async function aktifkanSekolah(s: Sekolah) {
    try {
        const res = await updateSekolah(s.id_sekolah, { is_active: true });
        toast.success(res.message);
        await loadSekolah();
        await pos.refreshSekolah();
    } catch (e) {
        toast.error(friendlyError(e, 'Gagal mengaktifkan kembali instansi sekolah.'));
    }
}

onMounted(() => {
    void (async () => {
        await pos.init();
        roles.value = await fetchRoles();
        await load();
        if (pos.can('sekolah')) await loadSekolah();
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
    <Head title="Manajemen Pengguna" />
    <PosLayout>
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between min-w-0">
            <div class="flex items-center gap-3 min-w-0">
                <div
                    class="flex h-10 w-10 sm:h-11 sm:w-11 shrink-0 items-center justify-center rounded-2xl bg-[#0f2a5c] text-white shadow dark:border dark:border-blue-900/50 dark:bg-blue-950/80 dark:text-blue-400"
                >
                    <Users class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white truncate">Manajemen Pengguna</h1>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 truncate">
                        Kelola akun pengguna, hak akses peran, dan data instansi sekolah
                    </p>
                </div>
            </div>
            <div class="flex w-full sm:w-auto items-center gap-2">
                <button
                    v-if="tab === 'user'"
                    type="button"
                    class="inline-flex w-full sm:w-auto justify-center cursor-pointer items-center gap-2 rounded-xl bg-blue-700 px-4 py-2 text-sm font-bold text-white shadow-xs hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500 transition"
                    @click="bukaTambah"
                >
                    <Plus class="h-4 w-4" /> Tambah Pengguna
                </button>
                <button
                    v-else-if="pos.can('sekolah')"
                    type="button"
                    class="inline-flex w-full sm:w-auto justify-center cursor-pointer items-center gap-2 rounded-xl bg-blue-700 px-4 py-2 text-sm font-bold text-white shadow-xs hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500 transition"
                    @click="bukaTambahSekolah"
                >
                    <Plus class="h-4 w-4" /> Tambah Sekolah
                </button>
            </div>
        </div>

        <EmptyState
            v-if="!pos.loading && !pos.can('users')"
            title="Akses Dibatasi"
            message="Halaman ini memerlukan hak akses tingkat Developer atau Administrator."
        />
        <template v-else>
            <!-- Tabs: User | Sekolah (sekolah hanya untuk developer) -->
            <div
                v-if="pos.can('sekolah')"
                class="mb-4 flex w-fit rounded-xl bg-slate-100 p-1 dark:bg-slate-800"
            >
                <button
                    type="button"
                    class="flex cursor-pointer items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs sm:text-sm font-semibold transition"
                    :class="tab === 'user' ? 'bg-white shadow-xs text-[#0f2a5c] dark:bg-slate-900 dark:text-blue-400' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                    @click="tab = 'user'"
                >
                    <Users class="h-4 w-4" /> Pengguna
                </button>
                <button
                    type="button"
                    class="flex cursor-pointer items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs sm:text-sm font-semibold transition"
                    :class="tab === 'sekolah' ? 'bg-white shadow-xs text-[#0f2a5c] dark:bg-slate-900 dark:text-blue-400' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                    @click="tab = 'sekolah'"
                >
                    <School class="h-4 w-4" /> Sekolah
                </button>
            </div>

            <!-- Tab User -->
            <template v-if="tab === 'user'">
                <div v-if="loading" class="space-y-2">
                    <div
                        v-for="i in 4"
                        :key="i"
                        class="h-16 animate-pulse rounded-xl bg-white dark:bg-slate-900"
                    />
                </div>
                <EmptyState
                    v-else-if="rows.length === 0"
                    title="Belum Ada Pengguna"
                    message="Belum ada data akun pengguna kasir yang terdaftar."
                />
                <template v-else>
                    <!-- Card View (Responsive: Mobile, Tablet & Laptop) -->
                    <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                        <div
                            v-for="u in rows"
                            :key="u.id_user"
                            class="relative flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-xs transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900 min-w-0"
                        >
                            <!-- Top: Avatar, Name, Username, and Status Badge -->
                            <div class="flex items-start gap-3 min-w-0">
                                <div
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 font-bold text-sm text-blue-700 shadow-inner dark:bg-blue-950/60 dark:text-blue-400"
                                >
                                    {{ u.nama_lengkap ? u.nama_lengkap.charAt(0).toUpperCase() : 'U' }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-1.5">
                                        <h3 class="font-bold text-sm text-slate-800 truncate dark:text-slate-100" :title="u.nama_lengkap">
                                            {{ u.nama_lengkap }}
                                        </h3>
                                        <span
                                            :class="
                                                u.is_active
                                                    ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400'
                                                    : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                                            "
                                            class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold"
                                        >
                                            {{ u.is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-400 truncate dark:text-slate-500">
                                        @{{ u.username }}
                                    </p>
                                </div>
                            </div>

                            <!-- Bottom: Role Badge & Action Buttons -->
                            <div class="mt-4 flex items-center justify-between gap-2 border-t border-slate-100 pt-3 dark:border-slate-800/80">
                                <span
                                    class="inline-block truncate rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-950/60 dark:text-blue-400 max-w-32"
                                >
                                    {{ u.role?.nama_role ?? '-' }}
                                </span>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <button
                                        type="button"
                                        title="Edit Pengguna"
                                        class="cursor-pointer inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 shadow-2xs hover:bg-blue-50 hover:text-blue-700 hover:border-blue-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 dark:hover:text-blue-400 transition"
                                        @click="bukaEdit(u)"
                                    >
                                        <Pencil class="h-3.5 w-3.5" />
                                        <span>Edit</span>
                                    </button>
                                    <button
                                        type="button"
                                        title="Hapus Pengguna"
                                        class="cursor-pointer inline-flex items-center gap-1 rounded-lg border border-red-200/80 bg-red-50/50 px-2.5 py-1 text-xs font-medium text-red-600 shadow-2xs hover:bg-red-100 hover:text-red-700 hover:border-red-300 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-400 dark:hover:bg-red-900/50 transition"
                                        @click="nonaktifkan(u)"
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
            </template>

            <!-- Tab Sekolah -->
            <template v-else>
                <div v-if="loadingSekolah" class="space-y-2">
                    <div
                        v-for="i in 4"
                        :key="i"
                        class="h-16 animate-pulse rounded-xl bg-white dark:bg-slate-900"
                    />
                </div>
                <EmptyState
                    v-else-if="rowsSekolah.length === 0"
                    title="Belum Ada Data Sekolah"
                    message="Belum ada data instansi sekolah yang terdaftar."
                />
                <template v-else>
                    <!-- Card View (Responsive: Mobile, Tablet & Laptop) -->
                    <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                        <div
                            v-for="s in rowsSekolah"
                            :key="s.id_sekolah"
                            class="relative flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-xs transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900 min-w-0"
                        >
                            <!-- Top: Icon, Code, Status, Name, Address -->
                            <div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-mono text-xs font-bold text-slate-600 dark:text-slate-300">
                                        {{ s.kode_sekolah }}
                                    </span>
                                    <span
                                        :class="
                                            s.is_active
                                                ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400'
                                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                                        "
                                        class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold"
                                    >
                                        {{ s.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </div>
                                <div class="mt-2.5 flex items-center gap-3 min-w-0">
                                    <div
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 font-bold text-blue-700 shadow-inner dark:bg-blue-950/60 dark:text-blue-400"
                                    >
                                        <School class="h-5 w-5" />
                                    </div>
                                    <h3 class="font-bold text-sm text-slate-800 truncate dark:text-slate-100" :title="s.nama_sekolah">
                                        {{ s.nama_sekolah }}
                                    </h3>
                                </div>
                                <div
                                    v-if="s.alamat_sekolah || s.alamat || s.website"
                                    class="mt-2.5 space-y-0.5 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    <p v-if="s.alamat_sekolah || s.alamat" class="truncate text-[11px]" :title="s.alamat_sekolah ?? s.alamat ?? ''">
                                        {{ s.alamat_sekolah ?? s.alamat }}
                                    </p>
                                    <a
                                        v-if="s.website"
                                        :href="s.website"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-block truncate text-[11px] text-blue-600 hover:underline dark:text-blue-400 max-w-full"
                                    >
                                        {{ s.website }}
                                    </a>
                                </div>
                            </div>

                            <!-- Bottom: Actions -->
                            <div class="mt-4 flex items-center justify-end gap-1.5 border-t border-slate-100 pt-3 dark:border-slate-800/80">
                                <button
                                    type="button"
                                    title="Edit Sekolah"
                                    class="cursor-pointer inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 shadow-2xs hover:bg-blue-50 hover:text-blue-700 hover:border-blue-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 dark:hover:text-blue-400 transition"
                                    @click="bukaEditSekolah(s)"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                    <span>Edit</span>
                                </button>
                                <button
                                    v-if="s.is_active"
                                    type="button"
                                    title="Hapus Sekolah"
                                    class="cursor-pointer inline-flex items-center gap-1 rounded-lg border border-red-200/80 bg-red-50/50 px-2.5 py-1 text-xs font-medium text-red-600 shadow-2xs hover:bg-red-100 hover:text-red-700 hover:border-red-300 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-400 dark:hover:bg-red-900/50 transition"
                                    @click="hapusSekolah(s)"
                                >
                                    <Power class="h-3.5 w-3.5" />
                                    <span>Hapus</span>
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    title="Aktifkan Kembali"
                                    class="cursor-pointer inline-flex items-center gap-1 rounded-lg border border-emerald-200/80 bg-emerald-50/50 px-2.5 py-1 text-xs font-medium text-emerald-600 shadow-2xs hover:bg-emerald-100 hover:text-emerald-700 hover:border-emerald-300 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-400 dark:hover:bg-emerald-900/50 transition"
                                    @click="aktifkanSekolah(s)"
                                >
                                    <RotateCcw class="h-3.5 w-3.5" />
                                    <span>Aktifkan</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </template>

            <!-- Modal User -->
            <Modal
                :open="showForm"
                :title="editing ? 'Edit Data Pengguna' : 'Tambah Pengguna Baru'"
                @close="showForm = false"
            >
                <form class="space-y-3" @submit.prevent="simpan">
                    <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                        >Nama Lengkap Pengguna*
                        <input
                            v-model="form.nama_lengkap"
                            required
                            placeholder="Contoh: Ahmad Kasir / Siti Admin"
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                        />
                    </label>
                    <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                        >Username Akun*{{ editing ? ' (tidak dapat diubah)' : '' }}
                        <input
                            v-model="form.username"
                            required
                            :disabled="!!editing"
                            placeholder="Contoh: kasir01, admin_utama"
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 disabled:bg-slate-50 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:disabled:bg-slate-800/50"
                        />
                    </label>
                    <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                        >Kata Sandi{{
                            editing ? ' (kosongkan jika tidak ingin diubah)' : '*'
                        }}
                        <input
                            v-model="form.password"
                            type="password"
                            :required="!editing"
                            minlength="6"
                            placeholder="Minimal 6 karakter"
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                        />
                    </label>
                    <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                        >Hak Akses / Peran*
                        <select
                            v-model="form.id_role"
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                        >
                            <option
                                v-for="r in roles"
                                :key="r.id_role"
                                :value="r.id_role"
                            >
                                {{ r.nama_role }}
                            </option>
                        </select>
                    </label>
                    <label
                        class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300 cursor-pointer"
                    >
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded accent-blue-700 cursor-pointer"
                        />
                        Status Akun Aktif
                    </label>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="w-full cursor-pointer rounded-xl bg-blue-700 py-2.5 text-sm font-bold text-white hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500 transition disabled:opacity-60 shadow-xs"
                    >
                        {{ saving ? 'Menyimpan…' : editing ? 'Simpan Perubahan' : 'Simpan Pengguna' }}
                    </button>
                </form>
            </Modal>

            <!-- Modal Sekolah -->
            <Modal
                :open="showFormSekolah"
                :title="editingSekolah ? 'Edit Data Sekolah' : 'Tambah Instansi Sekolah Baru'"
                @close="showFormSekolah = false"
            >
                <form class="space-y-3" @submit.prevent="simpanSekolah">
                    <div class="grid gap-2.5 sm:grid-cols-2">
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                            >Kode Sekolah*
                            <input
                                v-model="formSekolah.kode_sekolah"
                                required
                                maxlength="20"
                                placeholder="Contoh: SMKN01"
                                class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 font-mono text-sm uppercase text-slate-800 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                            />
                        </label>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                            >Nama Lengkap Sekolah*
                            <input
                                v-model="formSekolah.nama_sekolah"
                                required
                                maxlength="150"
                                placeholder="Contoh: SMKN 1 Tasikmalaya"
                                class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                            />
                        </label>
                    </div>
                    <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                        >Alamat Lengkap Sekolah
                        <input
                            v-model="formSekolah.alamat_sekolah"
                            placeholder="Contoh: Jl. Merdeka No. 100, Kota Tasikmalaya"
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                        />
                    </label>
                    <div class="grid gap-2.5 sm:grid-cols-2">
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                            >Website Resmi Sekolah
                            <input
                                v-model="formSekolah.website"
                                maxlength="200"
                                placeholder="Contoh: https://smkn1tasik.sch.id"
                                class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                            />
                        </label>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                            >Kota / Wilayah (Opsional)
                            <input
                                v-model="formSekolah.alamat"
                                maxlength="255"
                                placeholder="Contoh: Tasikmalaya"
                                class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                            />
                        </label>
                    </div>
                    <label
                        class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300 cursor-pointer"
                    >
                        <input
                            v-model="formSekolah.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded accent-blue-700 cursor-pointer"
                        />
                        Status Instansi Aktif
                    </label>

                    <div
                        v-if="!editingSekolah"
                        class="rounded-2xl border border-blue-100 bg-blue-50/50 p-3.5 dark:border-blue-900/50 dark:bg-blue-950/30"
                    >
                        <label
                            class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200 cursor-pointer"
                        >
                            <input
                                v-model="buatAkun"
                                type="checkbox"
                                class="h-4 w-4 rounded accent-blue-700 cursor-pointer"
                            />
                            Buatkan Akun Super Admin untuk Instansi Ini
                        </label>
                        <div v-if="buatAkun" class="mt-2.5 space-y-2.5">
                            <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                                >Nama Lengkap Super Admin*
                                <input
                                    v-model="akun.nama_lengkap"
                                    :required="buatAkun"
                                    placeholder="Contoh: SuperAdmin SMKN 1"
                                    class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                />
                            </label>
                            <div class="grid gap-2.5 sm:grid-cols-2">
                                <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                                    >Username Super Admin*
                                    <input
                                        v-model="akun.username"
                                        :required="buatAkun"
                                        placeholder="Contoh: admin_smkn1"
                                        class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                    />
                                </label>
                                <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                                    >Kata Sandi Super Admin* (min. 6 karakter)
                                    <input
                                        v-model="akun.password"
                                        type="password"
                                        :required="buatAkun"
                                        minlength="6"
                                        placeholder="Minimal 6 karakter"
                                        class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                    />
                                </label>
                            </div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                Akun ini berperan sebagai Super Admin dan langsung dapat digunakan untuk mengelola toko koperasi di sekolah tersebut.
                            </p>
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="savingSekolah"
                        class="w-full cursor-pointer rounded-xl bg-blue-700 py-2.5 text-sm font-bold text-white hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500 transition disabled:opacity-60 shadow-xs"
                    >
                        {{ savingSekolah ? 'Menyimpan…' : editingSekolah ? 'Simpan Perubahan' : 'Simpan Data Sekolah' }}
                    </button>
                </form>
            </Modal>
        </template>
    </PosLayout>
</template>
