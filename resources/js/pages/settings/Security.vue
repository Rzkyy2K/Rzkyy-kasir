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
    <Head title="Ubah Kata Sandi" />

    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-gradient-to-br from-[#0f2a5c] via-[#1a3f7d] to-[#0b1f45] p-6"
    >
        <div class="w-full max-w-sm">
            <div class="overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900 dark:border dark:border-slate-800">
                <div
                    class="flex flex-col items-center gap-2 bg-gradient-to-b from-blue-50 to-white px-6 pt-6 pb-2 dark:from-slate-800/80 dark:to-slate-900"
                >
                    <Link
                        href="/pengaturan"
                        class="flex flex-col items-center gap-2"
                    >
                        <img
                            src="/logoScholify.png"
                            alt="Logo Scholify"
                            class="h-16 w-16 rounded-2xl border border-slate-200 bg-white object-contain p-1.5 shadow-sm dark:border-slate-700 dark:bg-slate-800"
                        />
                        <span
                            class="text-lg font-black tracking-tight text-[#0f2a5c] dark:text-blue-400"
                        >
                            Scholify
                        </span>
                        <span
                            class="-mt-2 text-[11px] font-medium text-slate-400 dark:text-slate-400"
                        >
                            Smart School POS
                        </span>
                    </Link>
                    <div class="space-y-1 pt-2 text-center">
                        <h1 class="text-lg font-bold text-slate-900 dark:text-white">
                            Ubah Kata Sandi
                        </h1>
                        <p class="text-center text-sm text-slate-500 dark:text-slate-400">
                            Perbarui kata sandi akun kasir Anda
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
                                    class="text-slate-700 dark:text-slate-300"
                                    >Kata Sandi Saat Ini</Label
                                >
                                <PasswordInput
                                    id="current_password"
                                    name="current_password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="Masukkan kata sandi saat ini"
                                    class="bg-white dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100 dark:placeholder:text-slate-500"
                                />
                                <InputError
                                    :message="errors.current_password"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="password" class="text-slate-700 dark:text-slate-300"
                                    >Kata Sandi Baru</Label
                                >
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autocomplete="new-password"
                                    placeholder="Masukkan kata sandi baru"
                                    class="bg-white dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100 dark:placeholder:text-slate-500"
                                />
                                <InputError :message="errors.password" />
                            </div>

                            <div class="grid gap-2">
                                <Label
                                    for="password_confirmation"
                                    class="text-slate-700 dark:text-slate-300"
                                    >Konfirmasi Kata Sandi Baru</Label
                                >
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    required
                                    autocomplete="new-password"
                                    placeholder="Ulangi kata sandi baru"
                                    class="bg-white dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100 dark:placeholder:text-slate-500"
                                />
                                <InputError
                                    :message="errors.password_confirmation"
                                />
                            </div>

                            <Button
                                type="submit"
                                class="mt-2 w-full rounded-xl bg-blue-700 py-2.5 hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500 font-bold"
                                :disabled="processing"
                                data-test="update-password-button"
                            >
                                Simpan Kata Sandi Baru
                            </Button>
                        </div>

                        <div class="text-center">
                            <Link
                                href="/pengaturan"
                                class="text-sm font-semibold text-slate-500 hover:text-blue-700 dark:text-slate-400 dark:hover:text-blue-400"
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
