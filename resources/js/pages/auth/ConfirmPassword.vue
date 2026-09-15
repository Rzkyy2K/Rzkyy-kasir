<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

defineOptions({
    layout: {
        title: 'Konfirmasi Password',
        description:
            'Area aman aplikasi. Silakan konfirmasi password Anda sebelum melanjutkan.',
    },
});
</script>

<template>
    <Head title="Konfirmasi Password" />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <div class="space-y-6">
            <div class="grid gap-2">
                <Label for="password" class="text-slate-700">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full bg-white"
                    required
                    autocomplete="current-password"
                    autofocus
                    placeholder="Masukkan password Anda"
                />

                <InputError :message="errors.password" />
            </div>

            <div class="space-y-2">
                <Button
                    class="w-full rounded-xl bg-blue-700 py-2.5 hover:bg-blue-800"
                    :disabled="processing"
                    data-test="confirm-password-button"
                >
                    <Spinner v-if="processing" />
                    Konfirmasi Password
                </Button>
                <Link
                    href="/pengaturan"
                    class="block w-full py-1 text-center text-xs font-semibold text-slate-500 transition hover:text-slate-800"
                >
                    Batal & Kembali ke Pengaturan
                </Link>
            </div>
        </div>
    </Form>
</template>
