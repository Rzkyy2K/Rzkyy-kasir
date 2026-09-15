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
        toast.error(friendlyError(e, 'Daftar user gagal dimuat.'));
    } finally {
        loading.value = false;
    }
}

async function loadSekolah() {
    loadingSekolah.value = true;
    try {
        rowsSekolah.value = await fetchSekolah(true);
    } catch (e) {
        toast.error(friendlyError(e, 'Daftar sekolah gagal dimuat.'));
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
            friendlyError(e, 'User gagal disimpan. Silakan coba lagi.'),
        );
    } finally {
        saving.value = false;
    }
}

async function nonaktifkan(u: PosUser) {
    if (!confirm(`Hapus permanen user "${u.nama_lengkap}" (${u.username})? Username bisa dipakai lagi.`)) return;
    try {
        const res = await deletePosUser(u.id_user);
        toast.success(res.message ?? 'User berhasil dihapus.');
        await load();
    } catch (e) {
        toast.error(friendlyError(e, 'User gagal dihapus.'));
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
                    toast.error('Role super admin tidak ditemukan, akun super admin batal dibuat.');
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
            friendlyError(e, 'Sekolah gagal disimpan. Silakan coba lagi.'),
        );
    } finally {
        savingSekolah.value = false;
    }
}

async function hapusSekolah(s: Sekolah) {
    if (!confirm(`Hapus permanen "${s.nama_sekolah}" beserta kodenya (${s.kode_sekolah})? Tindakan ini tidak bisa dibatalkan.`)) return;
    try {
        const res = await deleteSekolah(s.id_sekolah);
        toast.success(res.message);
        await loadSekolah();
        await pos.refreshSekolah();
    } catch (e) {
        toast.error(friendlyError(e, 'Sekolah gagal dihapus.'));
    }
}

async function aktifkanSekolah(s: Sekolah) {
    try {
        const res = await updateSekolah(s.id_sekolah, { is_active: true });
        toast.success(res.message);
        await loadSekolah();
        await pos.refreshSekolah();
    } catch (e) {
        toast.error(friendlyError(e, 'Sekolah gagal diaktifkan.'));
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
    <Head title="Manajemen User" />
    <PosLayout>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[#0f2a5c] text-white shadow"
                >
                    <Users class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-xl font-bold text-slate-900">Manajemen User</h1>
                    <p class="text-sm text-slate-500">
                        Kelola akun tb_user, peran, dan sekolah (khusus developer)
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-if="tab === 'user'"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-800"
                    @click="bukaTambah"
                >
                    <Plus class="h-4 w-4" /> Tambah User
                </button>
                <button
                    v-else-if="pos.can('sekolah')"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-800"
                    @click="bukaTambahSekolah"
                >
                    <Plus class="h-4 w-4" /> Tambah Sekolah
                </button>
            </div>
        </div>

        <EmptyState
            v-if="!pos.loading && !pos.can('users')"
            title="Akses ditolak"
            message="Halaman ini khusus peran Developer. Login sebagai developer untuk membukanya."
        />
        <template v-else>
            <!-- Tabs: User | Sekolah (sekolah hanya untuk developer) -->
            <div
                v-if="pos.can('sekolah')"
                class="mb-4 flex w-fit rounded-xl bg-slate-100 p-1"
            >
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold transition"
                    :class="tab === 'user' ? 'bg-white shadow text-[#0f2a5c]' : 'text-slate-500 hover:text-slate-700'"
                    @click="tab = 'user'"
                >
                    <Users class="h-4 w-4" /> User
                </button>
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold transition"
                    :class="tab === 'sekolah' ? 'bg-white shadow text-[#0f2a5c]' : 'text-slate-500 hover:text-slate-700'"
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
                        class="h-16 animate-pulse rounded-xl bg-white"
                    />
                </div>
                <EmptyState v-else-if="rows.length === 0" title="Belum ada user" />
                <div
                    v-else
                    class="overflow-x-auto rounded-2xl border border-slate-200 bg-white"
                >
                    <table class="w-full min-w-170 text-left text-sm">
                        <thead>
                            <tr
                                class="border-b border-slate-100 bg-slate-50 text-xs text-slate-500 uppercase"
                            >
                                <th class="px-4 py-3">Nama</th>
                                <th class="px-4 py-3">Username</th>
                                <th class="px-4 py-3">Role</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="u in rows"
                                :key="u.id_user"
                                class="border-b border-slate-50"
                            >
                                <td class="px-4 py-3 font-semibold">
                                    {{ u.nama_lengkap }}
                                </td>
                                <td class="px-4 py-3 text-slate-500">
                                    {{ u.username }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700"
                                        >{{ u.role?.nama_role }}</span
                                    >
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="
                                            u.is_active
                                                ? 'bg-emerald-50 text-emerald-600'
                                                : 'bg-slate-100 text-slate-500'
                                        "
                                        class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                    >
                                        {{ u.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <button
                                            type="button"
                                            class="rounded-lg p-2 text-slate-400 hover:bg-blue-50 hover:text-blue-700"
                                            @click="bukaEdit(u)"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                            @click="nonaktifkan(u)"
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
            </template>

            <!-- Tab Sekolah -->
            <template v-else>
                <div v-if="loadingSekolah" class="space-y-2">
                    <div
                        v-for="i in 4"
                        :key="i"
                        class="h-16 animate-pulse rounded-xl bg-white"
                    />
                </div>
                <EmptyState v-else-if="rowsSekolah.length === 0" title="Belum ada sekolah" />
                <div
                    v-else
                    class="overflow-x-auto rounded-2xl border border-slate-200 bg-white"
                >
                    <table class="w-full min-w-170 text-left text-sm">
                        <thead>
                            <tr
                                class="border-b border-slate-100 bg-slate-50 text-xs text-slate-500 uppercase"
                            >
                                <th class="px-4 py-3">Kode</th>
                                <th class="px-4 py-3">Nama Sekolah</th>
                                <th class="px-4 py-3">Alamat / Website</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="s in rowsSekolah"
                                :key="s.id_sekolah"
                                class="border-b border-slate-50"
                            >
                                <td class="px-4 py-3 font-mono text-xs font-bold text-slate-600">
                                    {{ s.kode_sekolah }}
                                </td>
                                <td class="px-4 py-3 font-semibold">
                                    {{ s.nama_sekolah }}
                                </td>
                                <td class="max-w-60 px-4 py-3 text-slate-500">
                                    <p class="truncate text-xs">
                                        {{ s.alamat_sekolah ?? s.alamat ?? '—' }}
                                    </p>
                                    <p class="truncate text-xs text-blue-600">
                                        {{ s.website ?? '' }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="
                                            s.is_active
                                                ? 'bg-emerald-50 text-emerald-600'
                                                : 'bg-slate-100 text-slate-500'
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
                                            title="Edit"
                                            class="rounded-lg p-2 text-slate-400 hover:bg-blue-50 hover:text-blue-700"
                                            @click="bukaEditSekolah(s)"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-if="s.is_active"
                                            type="button"
                                            title="Hapus permanen"
                                            class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                            @click="hapusSekolah(s)"
                                        >
                                            <Power class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-else
                                            type="button"
                                            title="Aktifkan kembali"
                                            class="rounded-lg p-2 text-slate-400 hover:bg-emerald-50 hover:text-emerald-600"
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
            </template>

            <!-- Modal User -->
            <Modal
                :open="showForm"
                :title="editing ? 'Edit User' : 'Tambah User'"
                @close="showForm = false"
            >
                <form class="space-y-2.5" @submit.prevent="simpan">
                    <label class="text-xs font-medium text-slate-600"
                        >Nama lengkap*
                        <input
                            v-model="form.nama_lengkap"
                            required
                            class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm"
                        />
                    </label>
                    <label class="text-xs font-medium text-slate-600"
                        >Username*{{ editing ? ' (tidak dapat diubah)' : '' }}
                        <input
                            v-model="form.username"
                            required
                            :disabled="!!editing"
                            class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm disabled:bg-slate-50"
                        />
                    </label>
                    <label class="text-xs font-medium text-slate-600"
                        >Password{{
                            editing ? ' (kosongkan jika tidak diubah)' : '*'
                        }}
                        <input
                            v-model="form.password"
                            type="password"
                            :required="!editing"
                            minlength="6"
                            class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm"
                        />
                    </label>
                    <label class="text-xs font-medium text-slate-600"
                        >Role*
                        <select
                            v-model="form.id_role"
                            class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm"
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
                        class="flex items-center gap-2 text-sm text-slate-600"
                    >
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 accent-blue-700"
                        />
                        Aktif
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

            <!-- Modal Sekolah -->
            <Modal
                :open="showFormSekolah"
                :title="editingSekolah ? 'Edit Sekolah' : 'Tambah Sekolah'"
                @close="showFormSekolah = false"
            >
                <form class="space-y-2.5" @submit.prevent="simpanSekolah">
                    <div class="grid gap-2.5 sm:grid-cols-2">
                        <label class="text-xs font-medium text-slate-600"
                            >Kode sekolah*
                            <input
                                v-model="formSekolah.kode_sekolah"
                                required
                                maxlength="20"
                                placeholder="cth: SMKN005"
                                class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-3 font-mono text-sm uppercase"
                            />
                        </label>
                        <label class="text-xs font-medium text-slate-600"
                            >Nama sekolah*
                            <input
                                v-model="formSekolah.nama_sekolah"
                                required
                                maxlength="150"
                                placeholder="cth: SMKN 5 Tasikmalaya"
                                class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm"
                            />
                        </label>
                    </div>
                    <label class="text-xs font-medium text-slate-600"
                        >Alamat sekolah
                        <input
                            v-model="formSekolah.alamat_sekolah"
                            placeholder="Jl. …"
                            class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm"
                        />
                    </label>
                    <div class="grid gap-2.5 sm:grid-cols-2">
                        <label class="text-xs font-medium text-slate-600"
                            >Website
                            <input
                                v-model="formSekolah.website"
                                maxlength="200"
                                placeholder="https://…"
                                class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm"
                            />
                        </label>
                        <label class="text-xs font-medium text-slate-600"
                            >Alamat singkat (opsional)
                            <input
                                v-model="formSekolah.alamat"
                                maxlength="255"
                                class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm"
                            />
                        </label>
                    </div>
                    <label
                        class="flex items-center gap-2 text-sm text-slate-600"
                    >
                        <input
                            v-model="formSekolah.is_active"
                            type="checkbox"
                            class="h-4 w-4 accent-blue-700"
                        />
                        Aktif
                    </label>

                    <div
                        v-if="!editingSekolah"
                        class="rounded-xl border border-blue-100 bg-blue-50/50 p-3"
                    >
                        <label
                            class="flex items-center gap-2 text-sm font-semibold text-slate-700"
                        >
                            <input
                                v-model="buatAkun"
                                type="checkbox"
                                class="h-4 w-4 accent-blue-700"
                            />
                            Buatkan akun super admin untuk sekolah ini
                        </label>
                        <div v-if="buatAkun" class="mt-2.5 space-y-2.5">
                            <label class="text-xs font-medium text-slate-600"
                                >Nama super admin*
                                <input
                                    v-model="akun.nama_lengkap"
                                    :required="buatAkun"
                                    placeholder="cth: SuperAdmin SMKN 5"
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm"
                                />
                            </label>
                            <div class="grid gap-2.5 sm:grid-cols-2">
                                <label class="text-xs font-medium text-slate-600"
                                    >Username*
                                    <input
                                        v-model="akun.username"
                                        :required="buatAkun"
                                        placeholder="cth: admin-smkn5"
                                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm"
                                    />
                                </label>
                                <label class="text-xs font-medium text-slate-600"
                                    >Password* (min 6)
                                    <input
                                        v-model="akun.password"
                                        type="password"
                                        :required="buatAkun"
                                        minlength="6"
                                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm"
                                    />
                                </label>
                            </div>
                            <p class="text-[11px] text-slate-500">
                                Akun ini berperan super admin dan langsung bisa login
                                memakai aplikasi kasir untuk sekolah baru tersebut.
                            </p>
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="savingSekolah"
                        class="w-full rounded-xl bg-blue-700 py-2.5 text-sm font-bold text-white disabled:opacity-60"
                    >
                        Simpan
                    </button>
                </form>
            </Modal>
        </template>
    </PosLayout>
</template>
