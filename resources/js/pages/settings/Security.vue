<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

defineProps<{
    passwordRules: string;
}>();
</script>

<template>
    <Head title="Ganti Password" />

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
                            Ganti Password
                        </h1>
                        <p class="text-center text-sm text-slate-500">
                            Perbarui password akun Anda
                        </p>
                    </div>
                </div>
                <div class="px-6 py-6">
                    <Form
                        v-bind="SecurityController.update.form()"
                        :options="{ preserveScroll: true }"
                        reset-on-success
                        :reset-on-error="[
                            'password',
                            'password_confirmation',
                            'current_password',
                        ]"
                        class="flex flex-col gap-6"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-6">
                            <div class="grid gap-2">
                                <Label
                                    for="current_password"
                                    class="text-slate-700"
                                    >Password saat ini</Label
                                >
                                <PasswordInput
                                    id="current_password"
                                    name="current_password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="Password saat ini"
                                    class="bg-white"
                                />
                                <InputError
                                    :message="errors.current_password"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="password" class="text-slate-700"
                                    >Password baru</Label
                                >
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autocomplete="new-password"
                                    placeholder="Password baru"
                                    class="bg-white"
                                />
                                <InputError :message="errors.password" />
                            </div>

                            <div class="grid gap-2">
                                <Label
                                    for="password_confirmation"
                                    class="text-slate-700"
                                    >Konfirmasi password baru</Label
                                >
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    required
                                    autocomplete="new-password"
                                    placeholder="Konfirmasi password"
                                    class="bg-white"
                                />
                                <InputError
                                    :message="errors.password_confirmation"
                                />
                            </div>

                            <Button
                                type="submit"
                                class="mt-2 w-full rounded-xl bg-blue-700 py-2.5 hover:bg-blue-800"
                                :disabled="processing"
                                data-test="update-password-button"
                            >
                                Simpan Password Baru
                            </Button>
                        </div>

                        <div class="text-center">
                            <Link
                                href="/pengaturan"
                                class="text-sm font-semibold text-slate-500 hover:text-blue-700"
                            >
                                ← Kembali ke Pengaturan
                            </Link>
                        </div>
                    </Form>
                </div>
            </div>
            <p class="mt-4 text-center text-[11px] text-blue-200/70">
                SMKN 1 · SMKN 2 · SMKN 3 · SMKN 4 Tasikmalaya
            </p>
        </div>
    </div>
</template>
