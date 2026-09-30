<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Lock, LogOut, Settings, X } from '@lucide/vue';
import { ref } from 'vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

defineProps<Props>();

const showConfirmModal = ref(false);
const isLoggingOut = ref(false);

const handleLogoutClick = (e: Event) => {
    e.preventDefault();
    e.stopPropagation();
    isLoggingOut.value = false;
    showConfirmModal.value = true;
};

const cancelLogout = () => {
    if (isLoggingOut.value) return;
    showConfirmModal.value = false;
};

const confirmLogout = () => {
    if (isLoggingOut.value) return;
    isLoggingOut.value = true;

    setTimeout(() => {
        router.flushAll();
        router.post('/logout', {}, {
            onFinish: () => {
                isLoggingOut.value = false;
                showConfirmModal.value = false;
            },
        });
    }, 850);
};
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                Pengaturan
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <button
            type="button"
            class="flex w-full items-center px-2 py-1.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 rounded-sm cursor-pointer text-left"
            @click="handleLogoutClick"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Keluar
        </button>
    </DropdownMenuItem>

    <!-- Modal Konfirmasi Logout -->
    <Teleport to="body">
        <div
            v-if="showConfirmModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
            role="dialog"
            aria-modal="true"
        >
            <Transition
                appear
                enter-active-class="backdrop-enter-active"
                enter-from-class="backdrop-enter-from"
                enter-to-class="backdrop-enter-to"
                leave-active-class="backdrop-leave-active"
                leave-from-class="backdrop-leave-from"
                leave-to-class="backdrop-leave-to"
            >
                <div
                    class="fixed inset-0 backdrop-active cursor-pointer"
                    @click="cancelLogout"
                />
            </Transition>

            <Transition
                appear
                enter-active-class="modal-scale-enter"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="modal-scale-leave"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <!-- Modal Dialog with Animated Moving Glowing Red Border Beam -->
                <div
                    class="relative w-full max-w-sm sm:max-w-md overflow-hidden rounded-[26px] p-[2.5px] shadow-2xl animate-in transition-all duration-500"
                    :class="[
                        isLoggingOut
                            ? 'bg-emerald-100/70 dark:bg-emerald-950/40 shadow-emerald-500/20'
                            : 'bg-rose-100/80 dark:bg-rose-950/40 shadow-rose-500/15'
                    ]"
                >
                    <!-- Animated moving glowing outline beam -->
                    <div
                        class="absolute -inset-[150%] animate-[spin_3.5s_linear_infinite] pointer-events-none transition-all duration-500"
                        :class="[
                            isLoggingOut
                                ? 'bg-[conic-gradient(from_0deg,transparent_0_240deg,#34d399_280deg,#10b981_320deg,#059669_360deg)]'
                                : 'bg-[conic-gradient(from_0deg,transparent_0_220deg,#fecdd3_260deg,#f43f5e_300deg,#e11d48_340deg,#be123c_360deg)]'
                        ]"
                    />

                    <!-- Soft ambient glow around the moving border -->
                    <div
                        class="absolute -inset-[100%] blur-md opacity-60 animate-[spin_3.5s_linear_infinite] pointer-events-none transition-all duration-500"
                        :class="[
                            isLoggingOut
                                ? 'bg-[conic-gradient(from_0deg,transparent_0_240deg,#34d399_280deg,#10b981_320deg,#059669_360deg)]'
                                : 'bg-[conic-gradient(from_0deg,transparent_0_220deg,#fecdd3_260deg,#f43f5e_300deg,#e11d48_340deg,#be123c_360deg)]'
                        ]"
                    />

                    <!-- Inner Card -->
                    <div
                        class="relative w-full h-full rounded-[23px] bg-white p-6 dark:bg-slate-900 dark:border dark:border-slate-800/80 dark:text-slate-100"
                    >
                        <button
                            v-if="!isLoggingOut"
                            type="button"
                            class="absolute top-4 right-4 rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200 transition cursor-pointer"
                            @click="cancelLogout"
                        >
                            <X class="h-4 w-4 sm:h-5 sm:w-5" />
                        </button>

                        <div class="flex flex-col items-center text-center">
                            <div
                                class="flex h-16 w-16 items-center justify-center rounded-2xl mb-4 transition-all duration-300 shadow-sm"
                                :class="[
                                    isLoggingOut
                                        ? 'bg-emerald-50 border border-emerald-200 text-emerald-600 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-400 scale-105'
                                        : 'bg-rose-50 border border-rose-100 text-rose-600 dark:bg-rose-950/40 dark:border-rose-900/50 dark:text-rose-400'
                                ]"
                            >
                                <Lock v-if="isLoggingOut" class="h-8 w-8 animate-in zoom-in-75 duration-300" />
                                <LogOut v-else class="h-8 w-8 transition-transform duration-200" />
                            </div>

                            <h3
                                class="text-lg sm:text-xl font-bold transition-colors duration-200"
                                :class="isLoggingOut ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-900 dark:text-white'"
                            >
                                {{ isLoggingOut ? 'Sesi Berhasil Diamankan' : 'Yakin Ingin Keluar?' }}
                            </h3>

                            <p class="mt-2 text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed transition-all duration-200">
                                {{
                                    isLoggingOut
                                        ? 'Menutup sesi aktif kasir dan mengamankan data transaksi...'
                                        : 'Apakah Anda yakin ingin keluar dari sistem? Sesi akun Anda saat ini akan diakhiri.'
                                }}
                            </p>

                            <div v-if="!isLoggingOut" class="mt-6 flex w-full flex-col-reverse sm:flex-row items-center gap-2.5">
                                <button
                                    type="button"
                                    class="w-full sm:flex-1 rounded-xl border border-slate-200 bg-white py-2.5 px-4 text-sm font-semibold text-slate-700 shadow-xs hover:bg-slate-50 active:scale-98 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 transition cursor-pointer"
                                    @click="cancelLogout"
                                >
                                    Batal
                                </button>
                                <button
                                    type="button"
                                    class="w-full sm:flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-rose-600 py-2.5 px-4 text-sm font-semibold text-white shadow-md shadow-rose-600/20 hover:bg-rose-700 active:scale-98 transition cursor-pointer"
                                    @click="confirmLogout"
                                >
                                    <LogOut class="h-4 w-4" />
                                    <span>Ya, Keluar</span>
                                </button>
                            </div>

                            <!-- Shimmer Progress Bar -->
                            <div v-else class="mt-6 w-full space-y-2 animate-in fade-in duration-300">
                                <div class="h-2 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden relative">
                                    <div class="h-full bg-gradient-to-r from-emerald-500 via-teal-400 to-emerald-500 w-full animate-pulse"></div>
                                </div>
                                <p class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400">
                                    Mengalihkan ke halaman login...
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>
    </Teleport>
</template>
