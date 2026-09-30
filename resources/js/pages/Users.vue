<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Eye,
    LayoutGrid,
    Pencil,
    Plus,
    Power,
    RefreshCw,
    RotateCcw,
    School,
    Search,
    Shield,
    Table,
    Users,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import EmptyState from '@/components/pos/EmptyState.vue';
import Modal from '@/components/pos/Modal.vue';
import PageHeader from '@/components/pos/PageHeader.vue';
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

// View mode for Sekolah (Card vs Tabel)
const viewModeSekolah = ref<'card' | 'table'>('card');
if (typeof window !== 'undefined') {
    const saved = localStorage.getItem('pos_sekolah_view_mode');
    if (saved === 'card' || saved === 'table') {
        viewModeSekolah.value = saved;
    }
}
function setViewModeSekolah(mode: 'card' | 'table') {
    viewModeSekolah.value = mode;
    try {
        localStorage.setItem('pos_sekolah_view_mode', mode);
    } catch {
        // no-op
    }
}

// State untuk melihat akun terdaftar di setiap sekolah (Mode Read-Only)
const selectedSekolah = ref<Sekolah | null>(null);
const showUsersModal = ref(false);
const usersList = ref<PosUser[]>([]);
const usersLoading = ref(false);
const searchAkun = ref('');

async function bukaLihatAkun(s: Sekolah) {
    selectedSekolah.value = s;
    showUsersModal.value = true;
    searchAkun.value = '';
    if (s.users && s.users.length > 0) {
        usersList.value = s.users;
    } else {
        usersList.value = [];
    }
    await muatAkunSekolah(s.id_sekolah);
}

async function muatAkunSekolah(id_sekolah: number) {
    usersLoading.value = true;
    try {
        const res = await fetchPosUsers({ id_sekolah, per_page: 100 });
        usersList.value = res.data;
        const found = rowsSekolah.value.find((item) => item.id_sekolah === id_sekolah);
        if (found) {
            found.users = res.data;
            found.users_count = res.total;
        }
    } catch (e) {
        toast.error(friendlyError(e, 'Gagal memuat akun pengguna sekolah.'));
    } finally {
        usersLoading.value = false;
    }
}

const filteredUsers = computed(() => {
    const q = searchAkun.value.trim().toLowerCase();
    if (!q) return usersList.value;
    return usersList.value.filter((u) => {
        const nameMatch = (u.nama_lengkap ?? '').toLowerCase().includes(q);
        const usernameMatch = (u.username ?? '').toLowerCase().includes(q);
        const roleMatch = (u.role?.nama_role ?? '').toLowerCase().includes(q);
        return nameMatch || usernameMatch || roleMatch;
    });
});

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
        if (pos.isDev) {
            await loadSekolah();
        } else {
            await load();
        }
    })();
});
watch(
    () => pos.idSekolah,
    () => {
        if (!pos.isDev) {
            page.value = 1;
            void load();
        }
    },
);
</script>

<template>
    <Head :title="pos.isDev ? 'Manajemen Sekolah' : 'Manajemen Pengguna'" />
    <PosLayout>
        <PageHeader
            v-if="pos.isDev"
            title="Manajemen Sekolah"
            :icon="School"
            subtitle="Kelola data instansi sekolah dan status aktifnya"
        >
            <template #actions>
                <div class="flex items-center gap-2">
                    <!-- Switcher Mode: Card & Tabel -->
                    <div class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-100/90 p-1 dark:border-slate-800 dark:bg-slate-900">
                        <button
                            type="button"
                            :class="[
                                'inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition',
                                viewModeSekolah === 'card'
                                    ? 'bg-white text-blue-700 shadow-2xs dark:bg-slate-800 dark:text-blue-400'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'
                            ]"
                            title="Tampilan Card"
                            @click="setViewModeSekolah('card')"
                        >
                            <LayoutGrid class="h-4 w-4" />
                            <span class="hidden sm:inline">Card</span>
                        </button>
                        <button
                            type="button"
                            :class="[
                                'inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition',
                                viewModeSekolah === 'table'
                                    ? 'bg-white text-blue-700 shadow-2xs dark:bg-slate-800 dark:text-blue-400'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'
                            ]"
                            title="Tampilan Tabel"
                            @click="setViewModeSekolah('table')"
                        >
                            <Table class="h-4 w-4" />
                            <span class="hidden sm:inline">Tabel</span>
                        </button>
                    </div>

                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white shadow-xs hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500 transition"
                        @click="bukaTambahSekolah"
                    >
                        <Plus class="h-4 w-4" /> Tambah Sekolah
                    </button>
                </div>
            </template>
        </PageHeader>
        <PageHeader
            v-else
            title="Manajemen Pengguna"
            :icon="Users"
            subtitle="Kelola akun pengguna kasir dan hak akses peran"
        >
            <template #actions>
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white shadow-xs hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500 transition"
                    @click="bukaTambah"
                >
                    <Plus class="h-4 w-4" /> Tambah Pengguna
                </button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="!pos.loading && !pos.can('users')"
            title="Akses Dibatasi"
            message="Halaman ini memerlukan hak akses tingkat Developer atau Administrator."
        />
        <template v-else>
            <!-- Tampilan Pengguna (Hanya untuk Non-Developer / Super Admin) -->
            <template v-if="!pos.isDev">
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

            <!-- Tampilan Sekolah (Hanya untuk Developer) -->
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
                    <!-- 1. Tampilan Card (Grid) -->
                    <div
                        v-if="viewModeSekolah === 'card'"
                        class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5"
                    >
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

                                <!-- Akun Terdaftar Box / Button -->
                                <div class="mt-3">
                                    <button
                                        type="button"
                                        class="w-full cursor-pointer inline-flex items-center justify-between gap-1.5 rounded-xl border border-blue-200 bg-blue-50/70 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-100 hover:border-blue-300 dark:border-blue-900/50 dark:bg-blue-950/40 dark:text-blue-300 dark:hover:bg-blue-900/60 transition shadow-2xs"
                                        title="Klik untuk melihat akun yang terdaftar pada sekolah ini"
                                        @click="bukaLihatAkun(s)"
                                    >
                                        <span class="inline-flex items-center gap-1.5">
                                            <Users class="h-3.5 w-3.5 text-blue-600 dark:text-blue-400" />
                                            <span>Akun Terdaftar:</span>
                                        </span>
                                        <span class="rounded-lg bg-blue-100/80 px-2 py-0.5 text-[11px] font-bold dark:bg-blue-900/60">
                                            {{ s.users?.length ?? s.users_count ?? 0 }} Akun
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <!-- Bottom: Actions -->
                            <div class="mt-4 flex items-center justify-end gap-1.5 border-t border-slate-100 pt-3 dark:border-slate-800/80">
                                <button
                                    type="button"
                                    title="Lihat Akun Terdaftar"
                                    class="cursor-pointer inline-flex items-center gap-1 rounded-lg border border-blue-200 bg-white px-2.5 py-1 text-xs font-semibold text-blue-700 shadow-2xs hover:bg-blue-50 hover:border-blue-300 dark:border-blue-900/50 dark:bg-slate-800 dark:text-blue-300 dark:hover:bg-slate-700 transition"
                                    @click="bukaLihatAkun(s)"
                                >
                                    <Eye class="h-3.5 w-3.5" />
                                    <span>Akun</span>
                                </button>
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

                    <!-- 2. Tampilan Tabel -->
                    <div
                        v-else
                        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-170 text-left text-sm text-slate-700 dark:text-slate-200">
                                <thead class="border-b border-slate-100 bg-slate-50 text-xs uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-400">
                                    <tr>
                                        <th class="px-4 py-3">Kode</th>
                                        <th class="px-4 py-3">Nama Sekolah</th>
                                        <th class="px-4 py-3">Akun Terdaftar</th>
                                        <th class="px-4 py-3">Alamat & Website</th>
                                        <th class="px-4 py-3 text-center">Status</th>
                                        <th class="px-4 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                                    <tr
                                        v-for="s in rowsSekolah"
                                        :key="s.id_sekolah"
                                        class="transition hover:bg-slate-50/60 dark:hover:bg-slate-800/40"
                                    >
                                        <td class="px-4 py-3 font-mono text-xs font-bold text-slate-600 dark:text-slate-400">
                                            {{ s.kode_sekolah }}
                                        </td>
                                        <td class="px-4 py-3 font-semibold text-slate-900 dark:text-slate-100">
                                            <div class="flex items-center gap-2.5">
                                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-blue-50 font-bold text-blue-700 dark:bg-blue-950/60 dark:text-blue-400">
                                                    <School class="h-4 w-4" />
                                                </div>
                                                <span>{{ s.nama_sekolah }}</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <button
                                                type="button"
                                                class="cursor-pointer inline-flex items-center gap-1.5 rounded-xl border border-blue-200 bg-blue-50/70 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100 hover:border-blue-300 dark:border-blue-900/50 dark:bg-blue-950/40 dark:text-blue-300 dark:hover:bg-blue-900/60 transition shadow-2xs"
                                                title="Lihat akun yang terdaftar pada sekolah ini"
                                                @click="bukaLihatAkun(s)"
                                            >
                                                <Users class="h-3.5 w-3.5 text-blue-600 dark:text-blue-400" />
                                                <span>{{ s.users?.length ?? s.users_count ?? 0 }} Akun</span>
                                            </button>
                                        </td>
                                        <td class="max-w-60 px-4 py-3 text-slate-500 dark:text-slate-400">
                                            <p class="truncate text-xs">
                                                {{ s.alamat_sekolah ?? s.alamat ?? '—' }}
                                            </p>
                                            <p class="truncate text-xs text-blue-600 dark:text-blue-400">
                                                {{ s.website ?? '' }}
                                            </p>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span
                                                :class="
                                                    s.is_active
                                                        ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400'
                                                        : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                                                "
                                                class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                            >
                                                {{ s.is_active ? 'Aktif' : 'Nonaktif' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="inline-flex items-center justify-end gap-1">
                                                <button
                                                    type="button"
                                                    title="Lihat Akun Terdaftar"
                                                    class="cursor-pointer inline-flex items-center gap-1 rounded-lg border border-blue-200 bg-blue-50/70 px-2 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100 hover:border-blue-300 dark:border-blue-900/50 dark:bg-slate-800 dark:text-blue-300 dark:hover:bg-slate-700 transition"
                                                    @click="bukaLihatAkun(s)"
                                                >
                                                    <Eye class="h-3.5 w-3.5" />
                                                    <span class="hidden sm:inline">Akun</span>
                                                </button>
                                                <button
                                                    type="button"
                                                    title="Edit Sekolah"
                                                    class="cursor-pointer rounded-lg p-2 text-slate-400 hover:bg-blue-50 hover:text-blue-700 dark:hover:bg-blue-950/40 dark:hover:text-blue-400 transition"
                                                    @click="bukaEditSekolah(s)"
                                                >
                                                    <Pencil class="h-4 w-4" />
                                                </button>
                                                <button
                                                    v-if="s.is_active"
                                                    type="button"
                                                    title="Hapus Sekolah"
                                                    class="cursor-pointer rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40 dark:hover:text-red-400 transition"
                                                    @click="hapusSekolah(s)"
                                                >
                                                    <Power class="h-4 w-4" />
                                                </button>
                                                <button
                                                    v-else
                                                    type="button"
                                                    title="Aktifkan Kembali"
                                                    class="cursor-pointer rounded-lg p-2 text-slate-400 hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-950/40 dark:hover:text-emerald-400 transition"
                                                    @click="aktifkanSekolah(s)"
                                                >
                                                    <RotateCcw class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
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

            <!-- Modal Tampilan Akun Terdaftar (Read-Only) -->
            <Modal
                :open="showUsersModal"
                :title="selectedSekolah ? `Daftar Akun — ${selectedSekolah.nama_sekolah}` : 'Daftar Akun Pengguna'"
                wide
                @close="showUsersModal = false"
            >
                <div class="space-y-4">
                    <!-- Info Banner Read-Only -->
                    <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50/70 p-3.5 text-xs text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300">
                        <Shield class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" />
                        <div>
                            <span class="font-bold">Mode Hanya Lihat (Read-Only)</span>
                            <p class="mt-0.5 text-amber-800 dark:text-amber-300/90 text-[11px] leading-relaxed">
                                Anda hanya dapat melihat akun-akun yang sudah terdaftar pada instansi sekolah ini. Pengeditan kata sandi dan penghapusan akun dinonaktifkan demi keamanan.
                            </p>
                        </div>
                    </div>

                    <!-- Bar Pencarian & Ringkasan -->
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="relative flex-1">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                            <input
                                v-model="searchAkun"
                                type="text"
                                placeholder="Cari nama pengguna, username, atau peran..."
                                class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-9 pr-3 text-xs sm:text-sm text-slate-800 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500"
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                Total: {{ usersList.length }} Akun
                            </span>
                            <button
                                type="button"
                                title="Muat Ulang Data Akun"
                                class="cursor-pointer rounded-xl border border-slate-200 bg-white p-2 text-slate-500 hover:bg-slate-50 hover:text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 transition"
                                :disabled="usersLoading"
                                @click="selectedSekolah && muatAkunSekolah(selectedSekolah.id_sekolah)"
                            >
                                <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': usersLoading }" />
                            </button>
                        </div>
                    </div>

                    <!-- Loading Skeleton -->
                    <div v-if="usersLoading" class="space-y-2">
                        <div
                            v-for="i in 3"
                            :key="i"
                            class="h-12 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800"
                        />
                    </div>

                    <!-- Empty State -->
                    <EmptyState
                        v-else-if="filteredUsers.length === 0"
                        title="Tidak Ada Akun Ditemukan"
                        :message="searchAkun ? 'Tidak ada akun yang sesuai dengan kata kunci pencarian.' : 'Belum ada akun pengguna yang terdaftar untuk sekolah ini.'"
                    />

                    <!-- Table Akun Terdaftar -->
                    <div
                        v-else
                        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-140 text-left text-xs text-slate-700 dark:text-slate-200">
                                <thead class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-400">
                                    <tr>
                                        <th class="px-4 py-3">Pengguna</th>
                                        <th class="px-4 py-3">Username</th>
                                        <th class="px-4 py-3">Peran (Role)</th>
                                        <th class="px-4 py-3 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                                    <tr
                                        v-for="u in filteredUsers"
                                        :key="u.id_user"
                                        class="transition hover:bg-slate-50/60 dark:hover:bg-slate-800/40"
                                    >
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2.5">
                                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-700 font-bold text-xs uppercase dark:bg-blue-900/60 dark:text-blue-300">
                                                    {{ u.nama_lengkap ? u.nama_lengkap.charAt(0) : 'U' }}
                                                </div>
                                                <div>
                                                    <div class="font-bold text-slate-800 dark:text-slate-100">
                                                        {{ u.nama_lengkap }}
                                                    </div>
                                                    <div class="text-[10px] text-slate-400">
                                                        ID: #{{ u.id_user }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 font-mono font-medium text-slate-600 dark:text-slate-300">
                                            @{{ u.username }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                class="inline-block rounded-lg px-2 py-0.5 text-[11px] font-bold capitalize"
                                                :class="
                                                    u.role?.nama_role === 'super admin'
                                                        ? 'bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300'
                                                        : u.role?.nama_role === 'admin'
                                                        ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300'
                                                        : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                                "
                                            >
                                                {{ u.role?.nama_role ?? 'Pengguna' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span
                                                :class="
                                                    u.is_active
                                                        ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400'
                                                        : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                                                "
                                                class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                                            >
                                                {{ u.is_active ? 'Aktif' : 'Nonaktif' }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tombol Tutup -->
                    <div class="flex justify-end pt-2 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="button"
                            class="cursor-pointer rounded-xl bg-slate-100 px-4 py-2 text-xs sm:text-sm font-semibold text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 transition"
                            @click="showUsersModal = false"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </Modal>
        </template>
    </PosLayout>
</template>
