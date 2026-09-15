<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Pencil, Plus, Power, RotateCcw, School } from '@lucide/vue';
import { onMounted, ref } from 'vue';
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
    fetchRoles,
    fetchSekolah,
    updateSekolah,
} from '@/services/masterService';
import { usePosStore } from '@/stores/pos';
import type { Role, Sekolah } from '@/types/pos';

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

async function load() {
    loading.value = true;
    try {
        rows.value = await fetchSekolah(true);
    } catch (e) {
        toast.error(friendlyError(e, 'Daftar sekolah gagal dimuat.'));
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
            // Opsional: langsung buatkan akun super admin untuk sekolah baru
            // agar sekolah tersebut bisa login & memakai kasir.
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
        showForm.value = false;
        await load();
        await pos.refreshSekolah();
    } catch (e) {
        toast.error(
            friendlyError(e, 'Sekolah gagal disimpan. Silakan coba lagi.'),
        );
    } finally {
        saving.value = false;
    }
}

async function nonaktifkan(s: Sekolah) {
    if (!confirm(`Hapus permanen "${s.nama_sekolah}" beserta kodenya (${s.kode_sekolah})? Tindakan ini tidak bisa dibatalkan.`)) return;
    try {
        const res = await deleteSekolah(s.id_sekolah);
        toast.success(res.message);
        await load();
        await pos.refreshSekolah();
    } catch (e) {
        toast.error(friendlyError(e, 'Sekolah gagal dihapus.'));
    }
}

async function aktifkan(s: Sekolah) {
    try {
        const res = await updateSekolah(s.id_sekolah, { is_active: true });
        toast.success(res.message);
        await load();
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
    })();
});
</script>

<template>
    <Head title="Kelola Sekolah" />
    <PosLayout>
        <PageHeader
            title="Kelola Sekolah"
            :icon="School"
            subtitle="Tambah sekolah baru beserta akun super adminnya (khusus developer)"
        >
            <template #actions>
                <button
                    v-if="pos.can('sekolah')"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-800"
                    @click="bukaTambah"
                >
                    <Plus class="h-4 w-4" /> Tambah Sekolah
                </button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="!pos.loading && !pos.can('sekolah')"
            title="Akses ditolak"
            message="Halaman ini khusus peran Developer."
        />
        <template v-else>
            <div v-if="loading" class="space-y-2">
                <div
                    v-for="i in 4"
                    :key="i"
                    class="h-16 animate-pulse rounded-xl bg-white"
                />
            </div>
            <EmptyState v-else-if="rows.length === 0" title="Belum ada sekolah" />
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
                            v-for="s in rows"
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
                                        @click="bukaEdit(s)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="s.is_active"
                                        type="button"
                                        title="Hapus permanen"
                                        class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                        @click="nonaktifkan(s)"
                                    >
                                        <Power class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        title="Aktifkan kembali"
                                        class="rounded-lg p-2 text-slate-400 hover:bg-emerald-50 hover:text-emerald-600"
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
                :title="editing ? 'Edit Sekolah' : 'Tambah Sekolah'"
                @close="showForm = false"
            >
                <form class="space-y-2.5" @submit.prevent="simpan">
                    <div class="grid gap-2.5 sm:grid-cols-2">
                        <label class="text-xs font-medium text-slate-600"
                            >Kode sekolah*
                            <input
                                v-model="form.kode_sekolah"
                                required
                                maxlength="20"
                                placeholder="cth: SMKN005"
                                class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-3 font-mono text-sm uppercase"
                            />
                        </label>
                        <label class="text-xs font-medium text-slate-600"
                            >Nama sekolah*
                            <input
                                v-model="form.nama_sekolah"
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
                            v-model="form.alamat_sekolah"
                            placeholder="Jl. …"
                            class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm"
                        />
                    </label>
                    <div class="grid gap-2.5 sm:grid-cols-2">
                        <label class="text-xs font-medium text-slate-600"
                            >Website
                            <input
                                v-model="form.website"
                                maxlength="200"
                                placeholder="https://…"
                                class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm"
                            />
                        </label>
                        <label class="text-xs font-medium text-slate-600"
                            >Alamat singkat (opsional)
                            <input
                                v-model="form.alamat"
                                maxlength="255"
                                class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm"
                            />
                        </label>
                    </div>
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

                    <!-- Akun super admin awal: hanya saat tambah sekolah baru -->
                    <div
                        v-if="!editing"
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
                                    placeholder="cth: SuperAdmin-smkn2"
                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm"
                                />
                            </label>
                            <div class="grid gap-2.5 sm:grid-cols-2">
                                <label class="text-xs font-medium text-slate-600"
                                    >Username*
                                    <input
                                        v-model="akun.username"
                                        :required="buatAkun"
                                        placeholder="cth: super admin SMKN 2 TASIKMALAYA"
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
                                memakai aplikasi kasir untuk sekolah baru
                                tersebut.
                            </p>
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="saving"
                        class="w-full rounded-xl bg-blue-700 py-2.5 text-sm font-bold text-white disabled:opacity-60"
                    >
                        Simpan
                    </button>
                </form>
            </Modal>
        </template>
    </PosLayout>
</template>
