<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { home } from '@/routes';

const props = withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        showNav?: boolean;
    }>(),
    {
        title: '',
        description: '',
        showNav: undefined,
    },
);

const page = usePage();
const isLogin = computed(
    () => page.url.startsWith('/login') || page.component === 'auth/Login',
);
const isAbout = computed(
    () => page.url.startsWith('/about') || page.component === 'About',
);
const isConfirmPassword = computed(
    () =>
        page.url.includes('confirm-password') ||
        page.component === 'auth/ConfirmPassword',
);

const displayNav = computed(() => {
    if (props.showNav !== undefined) {
        return props.showNav;
    }
    return !isConfirmPassword.value;
});
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-gradient-to-br from-[#0f2a5c] via-[#1a3f7d] to-[#0b1f45] p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <!-- Navigasi Home — About (di atas card, disembunyikan di halaman confirm password) -->
            <nav
                v-if="displayNav"
                class="mb-6 flex items-center justify-center gap-3 text-sm tracking-wide"
                aria-label="Navigasi"
            >
                <Link
                    href="/login"
                    class="transition hover:text-white"
                    :class="
                        isLogin
                            ? 'font-semibold text-white'
                            : 'font-medium text-blue-200 hover:text-white'
                    "
                >
                    Home
                </Link>
                <span class="text-blue-200/60">—</span>
                <Link
                    href="/about"
                    class="transition hover:text-white"
                    :class="
                        isAbout
                            ? 'font-semibold text-white'
                            : 'font-medium text-blue-200 hover:text-white'
                    "
                >
                    About
                </Link>
            </nav>

            <div class="overflow-hidden rounded-3xl bg-white shadow-2xl dark:bg-slate-900 dark:border dark:border-slate-800">
                <div
                    class="flex flex-col items-center gap-2 bg-gradient-to-b from-blue-50 to-white px-6 pt-8 pb-2 dark:from-slate-800/80 dark:to-slate-900"
                >
                    <Link
                        :href="home()"
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
                            {{
                                title ||
                                (isConfirmPassword
                                    ? 'Konfirmasi Password'
                                    : isLogin
                                      ? 'Masuk ke Scholify'
                                      : '')
                            }}
                        </h1>
                        <p class="text-center text-sm text-slate-500 dark:text-slate-400">
                            {{
                                description ||
                                (isConfirmPassword
                                    ? 'Silakan konfirmasi password Anda untuk melanjutkan.'
                                    : isLogin
                                      ? 'Gunakan username dan password akun kasir Anda'
                                      : '')
                            }}
                        </p>
                    </div>
                </div>
                <div class="px-6 py-6">
                    <slot />
                </div>
            </div>
            <p class="mt-4 text-center text-[11px] text-blue-200/70">
                SMKN 1 · SMKN 2 · SMKN 3 · SMKN 4 Tasikmalaya
            </p>
        </div>
    </div>
</template>
