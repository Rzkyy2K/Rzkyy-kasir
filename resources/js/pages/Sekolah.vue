<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Eye,
    Pencil,
    Plus,
    Power,
    RefreshCw,
    RotateCcw,
    School,
    Search,
    Shield,
    Users,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import EmptyState from '@/components/pos/EmptyState.vue';
import Modal from '@/components/pos/Modal.vue';
import PageHeader from '@/components/pos/PageHeader.vue';
import PosLayout from '@/layouts/PosLayout.vue';
import { friendlyError } from '@/services/api';
import {
    createPosUser,
    createSekolah,
    deleteSekolah,
    fetchPosUsers,
    fetchRoles,
    fetchSekolah,
    updateSekolah,
} from '@/services/masterService';
import { usePosStore } from '@/stores/pos';
import type { PosUser, Role, Sekolah } from '@/types/pos';

const pos = usePosStore();
const loading = ref(true);
const rows = ref<Sekolah[]>([]);
const roles = ref<Role[]>([]);

const showForm = ref(false);
const editing = ref<Sekolah | null>(null);
const saving = ref(false);
const form = ref({
    kode_sekolah: '',
    nama_sekolah: '',
    alamat_sekolah: '',
    alamat: '',
    website: '',
    is_active: true,
});

// Akun super admin awal untuk sekolah baru (opsional).
const buatAkun = ref(true);
const akun = ref({ nama_lengkap: '', username: '', password: '' });

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
        const found = rows.value.find((item) => item.id_sekolah === id_sekolah);
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
        rows.value = await fetchSekolah(true);
    } catch (e) {
        toast.error(friendlyError(e, 'Gagal memuat daftar instansi sekolah.'));
    } finally {
        loading.value = false;
    }
}

function bukaTambah() {
    editing.value = null;
    form.value = {
        kode_sekolah: '',
        nama_sekolah: '',
        alamat_sekolah: '',
        alamat: '',
        website: '',
        is_active: true,
    };
    buatAkun.value = true;
    akun.value = { nama_lengkap: '', username: '', password: '' };
    showForm.value = true;
}

function bukaEdit(s: Sekolah) {
    editing.value = s;
    form.value = {
        kode_sekolah: s.kode_sekolah,
        nama_sekolah: s.nama_sekolah,
        alamat_sekolah: s.alamat_sekolah ?? '',
        alamat: s.alamat ?? '',
        website: s.website ?? '',
        is_active: s.is_active ?? true,
    };
    showForm.value = true;
}

async function simpan() {
    saving.value = true;
    try {
        const payload: Record<string, unknown> = {
            kode_sekolah: form.value.kode_sekolah.trim(),
            nama_sekolah: form.value.nama_sekolah.trim(),
            alamat_sekolah: form.value.alamat_sekolah.trim() || null,
            alamat: form.value.alamat.trim() || null,
            website: form.value.website.trim() || null,
            is_active: form.value.is_active,
        };
        if (editing.value) {
            const res = await updateSekolah(editing.value.id_sekolah, payload);
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
        showForm.value = false;
        await load();
        await pos.refreshSekolah();
    } catch (e) {
        toast.error(
            friendlyError(e, 'Gagal menyimpan data sekolah. Silakan coba kembali.'),
        );
    } finally {
        saving.value = false;
    }
}

async function nonaktifkan(s: Sekolah) {
    if (!confirm(`Hapus data instansi sekolah "${s.nama_sekolah}" (${s.kode_sekolah})? Tindakan ini tidak dapat dibatalkan.`)) return;
    try {
        const res = await deleteSekolah(s.id_sekolah);
        toast.success(res.message);
        await load();
        await pos.refreshSekolah();
    } catch (e) {
        toast.error(friendlyError(e, 'Gagal menghapus data sekolah.'));
    }
}

async function aktifkan(s: Sekolah) {
    try {
        const res = await updateSekolah(s.id_sekolah, { is_active: true });
        toast.success(res.message);
        await load();
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
    })();
});
</script>

<template>
    <Head title="Manajemen Sekolah" />
    <PosLayout>
        <PageHeader
            title="Manajemen Sekolah"
            :icon="School"
            subtitle="Kelola data instansi sekolah mitra dan akun super admin"
        >
            <template #actions>
                <button
                    v-if="pos.can('sekolah')"
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-800 shadow-xs dark:bg-blue-600 dark:hover:bg-blue-500 transition"
                    @click="bukaTambah"
                >
                    <Plus class="h-4 w-4" /> Tambah Sekolah Baru
                </button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="!pos.loading && !pos.can('sekolah')"
            title="Akses Dibatasi"
            message="Halaman ini memerlukan hak akses tingkat Developer."
        />
        <template v-else>
            <div v-if="loading" class="space-y-2">
                <div
                    v-for="i in 4"
                    :key="i"
                    class="h-16 animate-pulse rounded-xl bg-white dark:bg-slate-900"
                />
            </div>
            <EmptyState
                v-else-if="rows.length === 0"
                title="Belum Ada Data Sekolah"
                message="Belum ada data instansi sekolah yang terdaftar."
            />
            <div
                v-else
                class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900"
            >
                <table class="w-full min-w-170 text-left text-sm">
                    <thead>
                        <tr
                            class="border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/60 text-xs text-slate-500 dark:text-slate-400 uppercase"
                        >
                            <th class="px-4 py-3">Kode Sekolah</th>
                            <th class="px-4 py-3">Nama Sekolah</th>
                            <th class="px-4 py-3">Akun Terdaftar</th>
                            <th class="px-4 py-3">Alamat & Website</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="s in rows"
                            :key="s.id_sekolah"
                            class="border-b border-slate-50 dark:border-slate-800/60 hover:bg-slate-50/50 dark:hover:bg-slate-800/30"
                        >
                            <td class="px-4 py-3 font-mono text-xs font-bold text-slate-600 dark:text-slate-400">
                                {{ s.kode_sekolah }}
                            </td>
                            <td class="px-4 py-3 font-semibold text-slate-900 dark:text-slate-100">
                                {{ s.nama_sekolah }}
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
                            <td class="px-4 py-3">
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
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <button
                                        type="button"
                                        title="Lihat Akun Terdaftar"
                                        class="cursor-pointer rounded-lg p-2 text-slate-400 hover:bg-blue-50 hover:text-blue-700 dark:hover:bg-blue-950/40 dark:hover:text-blue-400 transition"
                                        @click="bukaLihatAkun(s)"
                                    >
                                        <Eye class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        title="Edit Sekolah"
                                        class="cursor-pointer rounded-lg p-2 text-slate-400 hover:bg-blue-50 hover:text-blue-700 dark:hover:bg-blue-950/40 dark:hover:text-blue-400 transition"
                                        @click="bukaEdit(s)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="s.is_active"
                                        type="button"
                                        title="Hapus Sekolah"
                                        class="cursor-pointer rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40 dark:hover:text-red-400 transition"
                                        @click="nonaktifkan(s)"
                                    >
                                        <Power class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        title="Aktifkan Kembali"
                                        class="cursor-pointer rounded-lg p-2 text-slate-400 hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-950/40 dark:hover:text-emerald-400 transition"
                                        @click="aktifkan(s)"
                                    >
                                        <RotateCcw class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Modal
                :open="showForm"
                :title="editing ? 'Edit Data Sekolah' : 'Tambah Instansi Sekolah Baru'"
                @close="showForm = false"
            >
                <form class="space-y-2.5" @submit.prevent="simpan">
                    <div class="grid gap-2.5 sm:grid-cols-2">
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                            >Kode Sekolah*
                            <input
                                v-model="form.kode_sekolah"
                                required
                                maxlength="20"
                                placeholder="Contoh: SMKN01"
                                class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 font-mono text-sm uppercase text-slate-900 placeholder:text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500"
                            />
                        </label>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                            >Nama Lengkap Sekolah*
                            <input
                                v-model="form.nama_sekolah"
                                required
                                maxlength="150"
                                placeholder="Contoh: SMKN 1 Tasikmalaya"
                                class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500"
                            />
                        </label>
                    </div>
                    <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                        >Alamat Lengkap Sekolah
                        <input
                            v-model="form.alamat_sekolah"
                            placeholder="Contoh: Jl. Merdeka No. 100, Kota Tasikmalaya"
                            class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500"
                        />
                    </label>
                    <div class="grid gap-2.5 sm:grid-cols-2">
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                            >Website Resmi Sekolah
                            <input
                                v-model="form.website"
                                maxlength="200"
                                placeholder="Contoh: https://smkn1tasik.sch.id"
                                class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500"
                            />
                        </label>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                            >Kota / Wilayah (Opsional)
                            <input
                                v-model="form.alamat"
                                maxlength="255"
                                placeholder="Contoh: Tasikmalaya"
                                class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500"
                            />
                        </label>
                    </div>
                    <label
                        class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300 cursor-pointer"
                    >
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 accent-blue-700 dark:accent-blue-500 cursor-pointer"
                        />
                        Status Instansi Aktif
                    </label>

                    <!-- Akun super admin awal: hanya saat tambah sekolah baru -->
                    <div
                        v-if="!editing"
                        class="rounded-xl border border-blue-100 bg-blue-50/50 p-3 dark:border-blue-900/40 dark:bg-blue-950/20"
                    >
                        <label
                            class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200 cursor-pointer"
                        >
                            <input
                                v-model="buatAkun"
                                type="checkbox"
                                class="h-4 w-4 accent-blue-700 dark:accent-blue-500 cursor-pointer"
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
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500"
                                />
                            </label>
                            <div class="grid gap-2.5 sm:grid-cols-2">
                                <label class="text-xs font-medium text-slate-600 dark:text-slate-300"
                                    >Username Super Admin*
                                    <input
                                        v-model="akun.username"
                                        :required="buatAkun"
                                        placeholder="Contoh: admin_smkn1"
                                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500"
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
                                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500"
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
                        :disabled="saving"
                        class="w-full cursor-pointer rounded-xl bg-blue-700 py-2.5 text-sm font-bold text-white hover:bg-blue-800 disabled:opacity-60 dark:bg-blue-600 dark:hover:bg-blue-500 transition shadow-xs"
                    >
                        {{ saving ? 'Menyimpan…' : editing ? 'Simpan Perubahan' : 'Simpan Data Sekolah' }}
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
