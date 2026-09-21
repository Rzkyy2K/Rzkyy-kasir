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
        </template>
    </PosLayout>
</template>
