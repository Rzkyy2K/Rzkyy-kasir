<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { friendlyError } from '@/services/api';
import { fetchRoles, updatePosUser } from '@/services/masterService';
import { usePosStore } from '@/stores/pos';
import type { Role } from '@/types/pos';

const pos = usePosStore();
const page = usePage();
const authUser = computed(
    () => page.props.auth.user as unknown as Record<string, unknown>,
);

const roles = ref<Role[]>([]);
const formNama = ref('');
const formUsername = ref('');
const formRoleId = ref<number>(0);
const saving = ref(false);

function syncForm() {
    formNama.value = String(
        authUser.value.nama_lengkap ?? pos.me?.nama_lengkap ?? '',
    );
    formUsername.value = String(
        authUser.value.username ?? pos.me?.username ?? '',
    );
    formRoleId.value = Number(authUser.value.id_role ?? pos.me?.id_role ?? 0);
}

async function simpanAkun() {
    if (!pos.me) return;
    if (!formNama.value.trim() || !formUsername.value.trim()) {
        toast.error('Nama dan username wajib diisi.');
        return;
    }
    saving.value = true;
    try {
        const payload: Record<string, unknown> = {
            nama_lengkap: formNama.value.trim(),
            username: formUsername.value.trim(),
        };
        if (pos.isSuper || pos.isDev) {
            payload.id_role = formRoleId.value;
        }
        const res = await updatePosUser(pos.me.id_user, payload);
        toast.success(res.message || 'Akun berhasil diperbarui.');
        await pos.init();
        syncForm();
    } catch (e) {
        toast.error(friendlyError(e, 'Gagal memperbarui akun.'));
    } finally {
        saving.value = false;
    }
}

onMounted(() => {
    void (async () => {
        await pos.init();
        syncForm();
        try {
            roles.value = await fetchRoles();
        } catch {
            /* roles opsional */
        }
    })();
});

watch(() => pos.me?.id_user, syncForm);
</script>

<template>
    <Head title="Ubah Username" />

    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-gradient-to-br from-[#0f2a5c] via-[#1a3f7d] to-[#0b1f45] p-6"
    >
        <div class="w-full max-w-sm">
            <div class="overflow-hidden rounded-3xl bg-white shadow-2xl">
                <div
                    class="flex flex-col items-center gap-2 bg-gradient-to-b from-blue-50 to-white px-6 pt-6 pb-2"
                >
                    <Link
                        href="/pengaturan"
                        class="flex flex-col items-center gap-2"
                    >
                        <img
                            src="/logoEduMart.jpeg"
                            alt="Logo EduMart"
                            class="h-16 w-16 rounded-2xl border border-slate-200 bg-white object-contain p-1.5 shadow-sm"
                        />
                        <span
                            class="text-lg font-black tracking-tight text-[#0f2a5c]"
                        >
                            EduMart
                        </span>
                        <span
                            class="-mt-2 text-[11px] font-medium text-slate-400"
                        >
                            Kasir Alat-Alat Sekolah
                        </span>
                    </Link>
                    <div class="space-y-1 pt-2 text-center">
                        <h1 class="text-lg font-bold text-slate-900">
                            Ubah Username & Role
                        </h1>
                        <p class="text-center text-sm text-slate-500">
                            Perbarui nama, username, dan peran akun Anda
                        </p>
                    </div>
                </div>
                <div class="px-6 py-6">
                    <form
                        class="flex flex-col gap-6"
                        @submit.prevent="simpanAkun"
                    >
                        <div class="grid gap-6">
                            <div class="grid gap-2">
                                <Label for="nama_lengkap" class="text-slate-700"
                                    >Nama lengkap</Label
                                >
                                <Input
                                    id="nama_lengkap"
                                    v-model="formNama"
                                    required
                                    autocomplete="name"
                                    placeholder="Nama lengkap"
                                    class="bg-white"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="username" class="text-slate-700"
                                    >Username</Label
                                >
                                <Input
                                    id="username"
                                    v-model="formUsername"
                                    required
                                    autocomplete="username"
                                    placeholder="Username"
                                    class="bg-white"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="role" class="text-slate-700"
                                    >Peran</Label
                                >
                                <select
                                    v-if="pos.isSuper || pos.isDev"
                                    id="role"
                                    v-model="formRoleId"
                                    class="border-input flex h-9 w-full rounded-md border bg-white px-3 py-1 text-sm shadow-xs"
                                >
                                    <option
                                        v-for="r in roles"
                                        :key="r.id_role"
                                        :value="r.id_role"
                                    >
                                        {{ r.nama_role }}
                                    </option>
                                </select>
                                <Input
                                    v-else
                                    :model-value="String(pos.ownRole ?? '')"
                                    disabled
                                    class="bg-slate-50"
                                />
                                <p
                                    v-if="!(pos.isSuper || pos.isDev)"
                                    class="text-xs text-slate-400"
                                >
                                    Peran hanya dapat diubah oleh Super Admin /
                                    Developer.
                                </p>
                            </div>

                            <button
                                type="submit"
                                class="mt-2 w-full rounded-xl bg-blue-700 py-2.5 text-sm font-bold text-white hover:bg-blue-800 disabled:opacity-60"
                                :disabled="saving"
                            >
                                {{ saving ? 'Menyimpan…' : 'Simpan Perubahan' }}
                            </button>
                        </div>

                        <div class="text-center">
                            <Link
                                href="/pengaturan"
                                class="text-sm font-semibold text-slate-500 hover:text-blue-700"
                            >
                                ← Kembali ke Pengaturan
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
            <p class="mt-4 text-center text-[11px] text-blue-200/70">
                SMKN 1 · SMKN 2 · SMKN 3 · SMKN 4 Tasikmalaya
            </p>
        </div>
    </div>
</template>
