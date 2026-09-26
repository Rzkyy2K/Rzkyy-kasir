<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    Boxes,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    Clock,
    Moon,
    School,
    ShieldCheck,
    Sparkles,
    Sun,
} from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useAppearance } from '@/composables/useAppearance';
import { store } from '@/routes/login';

const props = defineProps<{
    status?: string;
    canResetPassword?: boolean;
}>();

// ============================================================================
// Fitur Ubah Tema (Warna Tampilan: Terang & Gelap)
// ============================================================================
const { resolvedAppearance, updateAppearance } = useAppearance();

function toggleTheme() {
    if (resolvedAppearance.value === 'dark') {
        updateAppearance('light');
    } else {
        updateAppearance('dark');
    }
}

// ============================================================================
// State & Handler Interaksi Mobile Slide-Up Login Sheet (Fade In / Fade Out)
// ============================================================================
const isMobileLoginOpen = ref(false);

function openMobileLogin() {
    isMobileLoginOpen.value = true;
    const welcomeEl = document.querySelector('.welcome-scroll-container');
    if (welcomeEl) {
        welcomeEl.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function closeMobileLogin() {
    isMobileLoginOpen.value = false;
}

function toggleMobileLogin() {
    if (isMobileLoginOpen.value) {
        closeMobileLogin();
    } else {
        openMobileLogin();
    }
}

let touchStartY = 0;
function onWaveTouchStart(e: TouchEvent) {
    touchStartY = e.touches[0].clientY;
}
function onWaveTouchEnd(e: TouchEvent) {
    const deltaY = e.changedTouches[0].clientY - touchStartY;
    if (!isMobileLoginOpen.value && deltaY < -25) {
        openMobileLogin();
    } else if (isMobileLoginOpen.value && deltaY > 35) {
        closeMobileLogin();
    }
}

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape' && isMobileLoginOpen.value) {
        closeMobileLogin();
    }
}

// ============================================================================
// Animasi Ketik Manual Super Smooth (Natural Typing Dynamics)
// ============================================================================
const phrases = [
    'Selamat Datang di Scholify',
    'Portal Kasir Koperasi Sekolah',
    'Solusi Transaksi Cepat & Akurat',
];

const displayedText = ref('');
const currentPhraseIndex = ref(0);
const isDeleting = ref(false);
let typingTimer: ReturnType<typeof setTimeout> | null = null;

function typeEffect() {
    const currentFullText = phrases[currentPhraseIndex.value];

    if (!isDeleting.value) {
        // Mode mengetik karakter berikutnya
        const nextCharCount = displayedText.value.length + 1;
        displayedText.value = currentFullText.substring(0, nextCharCount);

        if (displayedText.value === currentFullText) {
            // Selesai satu kalimat, jeda nyaman untuk membaca
            isDeleting.value = true;
            typingTimer = setTimeout(typeEffect, 2500);
            return;
        }

        // Variasi pengetikan alami manusia (smooth & rhythmic):
        const lastChar = currentFullText[nextCharCount - 1];
        let delay = Math.floor(Math.random() * 20) + 48; // Kecepatan dasar 48ms - 68ms (cepat & lincah)

        if (lastChar === ' ') {
            delay += 40; // Jeda antar kata
        } else if (lastChar === '!' || lastChar === '.' || lastChar === ',') {
            delay += 160; // Jeda setelah tanda baca
        }

        typingTimer = setTimeout(typeEffect, delay);
    } else {
        // Mode menghapus karakter
        displayedText.value = currentFullText.substring(
            0,
            displayedText.value.length - 1,
        );

        if (displayedText.value === '') {
            // Selesai menghapus, ganti ke kalimat berikutnya
            isDeleting.value = false;
            currentPhraseIndex.value =
                (currentPhraseIndex.value + 1) % phrases.length;
            // Jeda halus sejenak sebelum mulai mengetik kalimat baru
            typingTimer = setTimeout(typeEffect, 450);
            return;
        }

        // Kecepatan menghapus yang konsisten dan mulus (~22ms - 26ms)
        const deleteSpeed = Math.floor(Math.random() * 5) + 22;
        typingTimer = setTimeout(typeEffect, deleteSpeed);
    }
}

onMounted(() => {
    typingTimer = setTimeout(typeEffect, 500);
    window.addEventListener('keydown', handleKeydown);
    if (props.status) {
        isMobileLoginOpen.value = true;
    }
});

onUnmounted(() => {
    if (typingTimer) {
        clearTimeout(typingTimer);
    }
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <Head title="Masuk ke Akun — Scholify" />

    <div class="relative flex min-h-svh w-full flex-col lg:flex-row overflow-x-hidden bg-white dark:bg-slate-950 font-sans">
        <!-- ================================================================= -->
        <!-- NAVIGASI HOME — ABOUT & FITUR UBAH WARNA (TEMA TAMPILAN)          -->
        <!-- ================================================================= -->
        <nav
            class="pointer-events-auto absolute z-50 flex items-center gap-2 sm:gap-3 rounded-full border border-white/30 bg-[#0c2356]/90 px-3.5 py-1.5 sm:px-5 sm:py-2 text-xs sm:text-sm font-bold tracking-wide text-white shadow-xl shadow-black/30 backdrop-blur-xl transition hover:bg-[#0c2356] dark:bg-[#061226]/90 dark:border-white/20 dark:hover:bg-[#061226] top-4 right-4 sm:top-6 sm:right-8 lg:right-auto lg:left-1/2 lg:-translate-x-1/2 lg:top-6"
            aria-label="Navigasi dan Tema"
        >
            <Link href="/login" class="font-bold text-white transition hover:text-cyan-300">
                Home
            </Link>
            <span class="text-cyan-400/50">—</span>
            <Link href="/about" class="text-blue-100 transition hover:text-white">
                About
            </Link>
            <span class="text-white/20">|</span>

            <!-- Tombol Pengubah Warna Tampilan (Terang / Gelap) -->
            <button
                type="button"
                @click="toggleTheme"
                class="inline-flex items-center gap-1.5 rounded-full bg-white/15 hover:bg-white/25 px-2.5 py-1 text-xs font-semibold text-white transition-all cursor-pointer active:scale-95 shadow-sm"
                :title="`Ubah Tema (Saat ini: ${resolvedAppearance === 'dark' ? 'Gelap' : 'Terang'})`"
            >
                <Moon v-if="resolvedAppearance === 'dark'" class="h-3.5 w-3.5 text-cyan-200" />
                <Sun v-else class="h-3.5 w-3.5 text-amber-300" />
                <span class="text-[11px] font-medium hidden sm:inline capitalize">
                    {{ resolvedAppearance === 'dark' ? 'Gelap' : 'Terang' }}
                </span>
            </button>
        </nav>

        <!-- ================================================================= -->
        <!-- 1. SISI KIRI: BRANDING, UCAPAN TYPEWRITER, SLOGAN & TUJUAN        -->
        <!-- Full Screen di Mobile, menyisakan header saat form login naik     -->
        <!-- ================================================================= -->
        <div
            class="welcome-scroll-container relative flex w-full flex-col justify-between overflow-y-auto lg:overflow-hidden bg-gradient-to-br from-[#0c2356] via-[#143f91] to-[#1e58c8] dark:from-[#061226] dark:via-[#091c3d] dark:to-[#0d2757] transition-colors duration-300 px-6 py-4 sm:px-10 md:px-14 lg:w-[52%] xl:w-[54%] 2xl:w-[56%] h-svh lg:min-h-svh lg:py-12 pb-24 lg:pb-12 text-white"
        >
            <!-- Background Decorative Radial Curves & Geometric Accents (Mirip Referensi) -->
            <div class="pointer-events-none absolute inset-0 opacity-15">
                <svg class="h-full w-full" viewBox="0 0 800 800" fill="none" preserveAspectRatio="none">
                    <circle cx="200" cy="200" r="280" stroke="white" stroke-width="1.5" stroke-dasharray="4 6" />
                    <circle cx="200" cy="200" r="420" stroke="white" stroke-width="1.5" />
                    <circle cx="200" cy="200" r="560" stroke="white" stroke-width="1" stroke-dasharray="8 8" />
                    <circle cx="200" cy="200" r="700" stroke="white" stroke-width="1" />
                </svg>
            </div>

            <!-- Ambient Glow Orbs -->
            <div class="pointer-events-none absolute -top-32 -left-32 h-96 w-96 rounded-full bg-cyan-400/20 blur-3xl" />
            <div class="pointer-events-none absolute bottom-0 right-0 h-96 w-96 rounded-full bg-blue-400/20 blur-3xl" />

            <!-- Header Sisi Kiri: Logo Scholify (Tetap terlihat di mobile saat form login naik) -->
            <div class="relative z-30 flex items-center justify-between h-[68px] sm:h-[72px] shrink-0 pr-36 sm:pr-40 lg:pr-0">
                <Link href="/login" class="flex items-center gap-2.5 sm:gap-3 transition hover:opacity-90">
                    <div class="flex h-11 w-11 sm:h-12 sm:w-12 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white p-1 shadow-lg shadow-black/20 border border-white/25 transition-transform hover:scale-105">
                        <img
                            src="/logoScholify.png"
                            alt="Logo Scholify"
                            class="h-full w-full object-contain"
                        />
                    </div>
                    <div>
                        <span class="text-lg font-black tracking-tight text-white sm:text-xl">Scholify</span>
                        <span class="block text-[10px] font-bold tracking-widest text-blue-200 uppercase">
                            Smart School POS
                        </span>
                    </div>
                </Link>
            </div>

            <!-- Kontainer Konten Selamat Datang (Animasi Fade In & Fade Out di Mobile) -->
            <div
                :class="[
                    'relative z-10 flex flex-1 flex-col justify-between transition-all duration-500 ease-in-out',
                    isMobileLoginOpen
                        ? 'opacity-0 -translate-y-2 pointer-events-none'
                        : 'opacity-100 translate-y-0 pointer-events-auto',
                    'lg:opacity-100 lg:translate-y-0 lg:pointer-events-auto'
                ]"
            >
                <!-- Bagian Tengah: Teks Ucapan dengan Animasi Ketik Manual + Slogan + Tujuan -->
                <div class="my-auto py-8 sm:py-10 lg:max-w-xl">
                    <!-- Sparkle Badge Mewah -->
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-cyan-300/30 bg-gradient-to-r from-cyan-500/20 via-blue-500/20 to-indigo-500/20 px-4 py-1.5 shadow-lg shadow-cyan-500/10 backdrop-blur-md"
                    >
                        <div class="flex h-5 w-5 items-center justify-center rounded-full bg-cyan-400/30 text-cyan-200">
                            <Sparkles class="h-3.5 w-3.5 animate-pulse" />
                        </div>
                        <span class="text-xs font-bold tracking-wide text-cyan-100">
                            Smart Point of Sales Sekolah
                        </span>
                    </div>

                    <!-- Headline Ucapan Selamat Datang dengan Animasi Ketik Manual (Sejajar Sempurna & Inline) -->
                    <h1
                        class="mt-4 min-h-[96px] sm:min-h-[110px] md:min-h-[125px] lg:min-h-[135px] text-3xl font-black tracking-tight text-white sm:text-4xl md:text-5xl lg:text-[46px] xl:text-[50px] leading-[1.18]"
                    >
                        <span class="inline">{{ displayedText }}</span><span
                            class="inline-block w-[3px] sm:w-1 h-[0.82em] bg-cyan-300 rounded-full ml-1.5 shadow-[0_0_10px_#38bdf8] typewriter-cursor align-[-0.06em]"
                            aria-hidden="true"
                        />
                    </h1>

                    <!-- Slogan & Tujuan Website -->
                    <div class="mt-6 space-y-3.5 text-blue-100/90">
                        <p class="text-base sm:text-lg font-bold text-white leading-snug">
                            Sederhanakan Transaksi, Majukan Koperasi Sekolah.
                        </p>
                        <p class="text-xs sm:text-sm leading-relaxed text-blue-100/80">
                            Platform kasir modern yang dirancang khusus untuk mempermudah operasional koperasi sekolah (alat tulis, perlengkapan belajar, seragam, hingga makanan & minuman). Mempercepat pelayanan saat jam istirahat yang padat, mencatat stok secara otomatis, dan menjaga transparansi pembukuan keuangan sekolah tanpa selisih kas.
                        </p>
                    </div>

                    <!-- 3 Pilar Fitur Unggulan (Ikon Modern & Elegan dengan Gradient Squircle) -->
                    <div class="mt-8 grid grid-cols-3 gap-3 sm:gap-4 pt-2">
                        <!-- Fitur 1: Kasir Cepat -->
                        <div
                            class="group relative overflow-hidden rounded-2xl border border-white/15 bg-white/10 p-3.5 sm:p-4 shadow-lg backdrop-blur-md transition-all duration-300 hover:-translate-y-1 hover:border-white/30 hover:bg-white/15 hover:shadow-xl"
                        >
                            <div class="flex h-10 w-10 sm:h-11 sm:w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-600 text-white shadow-md shadow-emerald-500/30 border border-emerald-300/40 transition-transform duration-300 group-hover:scale-110">
                                <Clock class="h-5 w-5" />
                            </div>
                            <p class="mt-3 text-xs sm:text-sm font-black text-white tracking-tight">Kasir Cepat</p>
                            <p class="mt-1 text-[10px] sm:text-[11px] leading-snug text-blue-100/80">Pindai barcode kilat & kembalian otomatis</p>
                        </div>

                        <!-- Fitur 2: Stok Real-time -->
                        <div
                            class="group relative overflow-hidden rounded-2xl border border-white/15 bg-white/10 p-3.5 sm:p-4 shadow-lg backdrop-blur-md transition-all duration-300 hover:-translate-y-1 hover:border-white/30 hover:bg-white/15 hover:shadow-xl"
                        >
                            <div class="flex h-10 w-10 sm:h-11 sm:w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-400 to-blue-600 text-white shadow-md shadow-cyan-500/30 border border-cyan-300/40 transition-transform duration-300 group-hover:scale-110">
                                <Boxes class="h-5 w-5" />
                            </div>
                            <p class="mt-3 text-xs sm:text-sm font-black text-white tracking-tight">Stok Real-time</p>
                            <p class="mt-1 text-[10px] sm:text-[11px] leading-snug text-blue-100/80">Sinkronisasi stok otomatis tanpa selisih</p>
                        </div>

                        <!-- Fitur 3: Multi-Sekolah -->
                        <div
                            class="group relative overflow-hidden rounded-2xl border border-white/15 bg-white/10 p-3.5 sm:p-4 shadow-lg backdrop-blur-md transition-all duration-300 hover:-translate-y-1 hover:border-white/30 hover:bg-white/15 hover:shadow-xl"
                        >
                            <div class="flex h-10 w-10 sm:h-11 sm:w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-400 to-orange-600 text-white shadow-md shadow-amber-500/30 border border-amber-300/40 transition-transform duration-300 group-hover:scale-110">
                                <ShieldCheck class="h-5 w-5" />
                            </div>
                            <p class="mt-3 text-xs sm:text-sm font-black text-white tracking-tight">Multi-Sekolah</p>
                            <p class="mt-1 text-[10px] sm:text-[11px] leading-snug text-blue-100/80">Isolasi data aman SMKN 1–4 Tasikmalaya</p>
                        </div>
                    </div>
                </div>

                <!-- Footer Sisi Kiri: Hak Cipta & Mitra (z-30 dan safe right padding) -->
                <div
                    class="relative z-10 flex flex-wrap items-center justify-between gap-2 border-t border-white/15 pt-5 pr-1 lg:pr-24 xl:pr-28 text-xs text-blue-200/80 shrink-0"
                >
                    <p class="font-medium">
                        © {{ new Date().getFullYear() }} Scholify. All rights reserved.
                    </p>
                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-white">
                        <School class="h-3.5 w-3.5 text-cyan-300" />
                        <span>SMKN 1 · SMKN 2 · SMKN 3 · SMKN 4 Tasikmalaya</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Backdrop halus di mobile saat form login terbuka penuh -->
        <div
            v-if="isMobileLoginOpen"
            class="fixed inset-x-0 top-[68px] sm:top-[72px] bottom-0 z-35 bg-black/20 backdrop-blur-[2px] transition-opacity duration-300 block lg:hidden"
            @click="closeMobileLogin"
        />

        <!-- ================================================================= -->
        <!-- 2. SISI KANAN: FORM LOGIN DENGAN GELOMBANG NAIK-TURUN DI MOBILE   -->
        <!-- ================================================================= -->
        <div
            :class="[
                'fixed inset-x-0 bottom-0 z-40 flex flex-col justify-start bg-transparent transition-transform duration-500 ease-[cubic-bezier(0.16,1,0.3,1)]',
                'h-[calc(100svh-68px)] sm:h-[calc(100svh-72px)]',
                isMobileLoginOpen ? 'translate-y-0' : 'translate-y-[calc(100%-3.75rem)]',
                'lg:static lg:h-auto lg:min-h-svh lg:w-[48%] xl:w-[46%] 2xl:w-[44%] lg:flex-1 lg:translate-y-0 lg:justify-center lg:bg-white lg:dark:bg-slate-950 px-0 lg:px-16 xl:px-20 py-0 lg:py-12'
            ]"
        >
            <!-- ------------------------------------------------------------- -->
            <!-- GELOMBANG PEMISAH VERTIKAL (DESKTOP)                          -->
            <!-- Terletak di ujung kiri form login, memotong warna biru        -->
            <!-- ------------------------------------------------------------- -->
            <div
                class="pointer-events-none absolute top-0 bottom-0 -left-12 lg:-left-20 xl:-left-24 hidden h-full w-16 lg:w-24 xl:w-28 overflow-hidden lg:block z-20"
            >
                <!-- Layer Gelombang 1 (Aksen Transparan Cyan - Mengalir Naik) -->
                <svg
                    class="absolute inset-x-0 top-0 h-[200%] w-full vert-wave-layer-1 opacity-40"
                    viewBox="0 0 100 1200"
                    preserveAspectRatio="none"
                >
                    <defs>
                        <linearGradient id="waveCyanGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.8" />
                            <stop offset="100%" stop-color="#38bdf8" stop-opacity="0.1" />
                        </linearGradient>
                    </defs>
                    <path
                        d="M 100,0 
                           L 30,0 
                           C 65,75 65,225 30,300 
                           C -5,375 -5,525 30,600 
                           C 65,675 65,825 30,900 
                           C -5,975 -5,1125 30,1200 
                           L 100,1200 
                           Z"
                        fill="url(#waveCyanGrad)"
                    />
                </svg>

                <!-- Layer Gelombang 2 (Aksen Transparan Indigo - Mengalir Berlawanan) -->
                <svg
                    class="absolute inset-x-0 top-0 h-[200%] w-full vert-wave-layer-2 opacity-35"
                    viewBox="0 0 100 1200"
                    preserveAspectRatio="none"
                >
                    <defs>
                        <linearGradient id="waveIndigoGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" stop-color="#818cf8" stop-opacity="0.7" />
                            <stop offset="100%" stop-color="#818cf8" stop-opacity="0.1" />
                        </linearGradient>
                    </defs>
                    <path
                        d="M 100,0 
                           L 45,0 
                           C 10,75 10,225 45,300 
                           C 80,375 80,525 45,600 
                           C 10,675 10,825 45,900 
                           C 80,975 80,1125 45,1200 
                           L 100,1200 
                           Z"
                        fill="url(#waveIndigoGrad)"
                    />
                </svg>

                <!-- Layer Gelombang Utama (Menyatu Sempurna dengan Warna Form Login) -->
                <svg
                    class="absolute inset-x-0 top-0 h-[200%] w-full vert-wave-layer-main"
                    viewBox="0 0 100 1200"
                    preserveAspectRatio="none"
                >
                    <path
                        d="M 100,0 
                           L 40,0 
                           C 75,75 75,225 40,300 
                           C 5,375 5,525 40,600 
                           C 75,675 75,825 40,900 
                           C 5,975 5,1125 40,1200 
                           L 100,1200 
                           Z"
                        class="fill-white dark:fill-slate-950"
                    />
                    <!-- Garis Batas Gelombang Berpendar Halus -->
                    <path
                        d="M 40,0 
                           C 75,75 75,225 40,300 
                           C 5,375 5,525 40,600 
                           C 75,675 75,825 40,900 
                           C 5,975 5,1125 40,1200"
                        stroke="#38bdf8"
                        stroke-width="2"
                        fill="none"
                        class="opacity-60"
                    />
                </svg>
            </div>

            <!-- ------------------------------------------------------------- -->
            <!-- GELOMBANG PEMISAH HORIZONTAL INTERAKTIF (MOBILE)              -->
            <!-- Di mobile: Menghubungkan selamat datang di atas & form login  -->
            <!-- ------------------------------------------------------------- -->
            <div
                class="relative h-14 sm:h-16 w-full shrink-0 overflow-hidden block lg:hidden z-20 cursor-pointer select-none"
                @click="toggleMobileLogin"
                @touchstart="onWaveTouchStart"
                @touchend="onWaveTouchEnd"
            >
                <!-- Layer Gelombang Aksen Cyan (Horizontal Mengalir Halus) -->
                <svg
                    class="absolute inset-0 h-full w-[200%] mobile-wave-accent opacity-35 pointer-events-none"
                    viewBox="0 0 1200 80"
                    preserveAspectRatio="none"
                >
                    <path
                        d="M 0,80 
                           L 0,25 
                           C 75,5 225,5 300,25 
                           C 375,50 525,50 600,25 
                           C 675,5 825,5 900,25 
                           C 975,50 1125,50 1200,25 
                           L 1200,80 
                           Z"
                        fill="#38bdf8"
                    />
                </svg>

                <!-- Layer Gelombang Utama (Menyatu Sempurna dengan Warna Form Login) -->
                <svg
                    class="h-full w-[200%] mobile-wave-track pointer-events-none"
                    viewBox="0 0 1200 80"
                    preserveAspectRatio="none"
                >
                    <path
                        d="M 0,80 
                           L 0,35 
                           C 75,65 225,65 300,35 
                           C 375,5 525,5 600,35 
                           C 675,65 825,65 900,35 
                           C 975,5 1125,5 1200,35 
                           L 1200,80 
                           Z"
                        class="fill-white dark:fill-slate-950"
                    />
                    <path
                        d="M 0,35 
                           C 75,65 225,65 300,35 
                           C 375,5 525,5 600,35 
                           C 675,65 825,65 900,35 
                           C 975,5 1125,5 1200,35"
                        stroke="#38bdf8"
                        stroke-width="2.5"
                        fill="none"
                        class="opacity-60"
                    />
                </svg>

                <!-- Tombol Indikator Ketuk / Buka / Tutup di Tengah Gelombang -->
                <div class="pointer-events-none absolute inset-x-0 bottom-1 flex justify-center z-30">
                    <div
                        v-if="!isMobileLoginOpen"
                        class="flex items-center gap-1.5 rounded-full border border-cyan-400/40 bg-white/95 px-3.5 py-1 text-xs font-black text-slate-800 shadow-md backdrop-blur-md dark:border-cyan-500/40 dark:bg-slate-900/95 dark:text-cyan-300"
                    >
                        <ChevronUp class="h-3.5 w-3.5 animate-bounce text-blue-600 dark:text-cyan-400" />
                        <span>Ketuk untuk Masuk ke Akun</span>
                    </div>
                    <div
                        v-else
                        class="flex items-center gap-1.5 rounded-full border border-slate-200/90 bg-white/95 px-3 py-0.5 text-[11px] font-bold text-slate-600 shadow-xs backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/95 dark:text-slate-300"
                    >
                        <ChevronDown class="h-3 w-3 text-slate-500 dark:text-slate-400" />
                        <span>Tutup & Lihat Beranda</span>
                    </div>
                </div>
            </div>

            <!-- Kontainer Form Login (Background Putih / Slate-950) -->
            <div
                class="flex-1 bg-white dark:bg-slate-950 overflow-y-auto px-6 py-6 pb-12 sm:px-10 md:px-14 lg:px-0 lg:py-0 lg:overflow-visible flex flex-col justify-center"
            >
                <div class="mx-auto w-full max-w-md">
                    <!-- Logo & Brand di Bagian Kanan -->
                    <div class="mb-6 sm:mb-8">
                        <div class="flex items-center gap-2.5">
                            <img
                                src="/logoScholify.png"
                                alt="Logo Scholify"
                                class="h-9 w-9 rounded-xl border border-slate-200 bg-white object-contain p-1 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                            />
                            <span class="text-xl font-black tracking-tight text-slate-900 dark:text-white">
                                Scholify
                            </span>
                        </div>

                        <h2 class="mt-4 sm:mt-6 text-2xl sm:text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                            Welcome Back!
                        </h2>
                        <p class="mt-1.5 text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                            Masukkan nama pengguna dan kata sandi akun kasir Anda untuk melanjutkan.
                        </p>
                    </div>

                    <!-- Alert Status Session -->
                    <div
                        v-if="status"
                        class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-3.5 text-xs font-semibold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300"
                    >
                        {{ status }}
                    </div>

                    <!-- Form Login Interaktif -->
                    <Form
                        v-bind="store.form()"
                        :reset-on-success="['password']"
                        v-slot="{ errors, processing }"
                        class="flex flex-col gap-5"
                    >
                        <div class="space-y-4">
                            <!-- Input Username -->
                            <div class="space-y-1.5">
                                <Label
                                    for="username"
                                    class="text-xs font-bold text-slate-700 dark:text-slate-300"
                                >
                                    Username
                                </Label>
                                <Input
                                    id="username"
                                    type="text"
                                    name="username"
                                    required
                                    autofocus
                                    :tabindex="1"
                                    autocomplete="username"
                                    placeholder="Masukkan username Anda"
                                    class="h-11 rounded-xl border-slate-200 bg-slate-50/50 px-3.5 text-sm text-slate-900 placeholder:text-slate-400 focus-visible:border-blue-600 focus-visible:ring-blue-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500"
                                />
                                <InputError :message="errors.username" />
                            </div>

                            <!-- Input Password -->
                            <div class="space-y-1.5">
                                <Label
                                    for="password"
                                    class="text-xs font-bold text-slate-700 dark:text-slate-300"
                                >
                                    Password
                                </Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    :tabindex="2"
                                    autocomplete="current-password"
                                    placeholder="Masukkan kata sandi Anda"
                                    class="h-11 rounded-xl border-slate-200 bg-slate-50/50 px-3.5 text-sm text-slate-900 placeholder:text-slate-400 focus-visible:border-blue-600 focus-visible:ring-blue-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500"
                                />
                                <InputError :message="errors.password" />
                            </div>

                            <!-- Tombol Masuk -->
                            <Button
                                type="submit"
                                class="mt-2 h-11 w-full cursor-pointer rounded-xl bg-slate-950 py-3 text-sm font-bold text-white shadow-lg transition-all hover:bg-slate-800 active:scale-98 dark:bg-blue-600 dark:hover:bg-blue-500"
                                :tabindex="3"
                                :disabled="processing"
                                data-test="login-button"
                            >
                                <Spinner v-if="processing" class="mr-2" />
                                <span>Login Now</span>
                            </Button>
                        </div>

                        <!-- Keterangan Hubungi Admin Sekolah -->
                        <div class="mt-4 border-t border-slate-100 pt-4 text-center dark:border-slate-800">
                            <p class="text-[11px] leading-relaxed text-slate-400 dark:text-slate-500">
                                Akun dikelola oleh administrator sekolah. Hubungi pihak sekolah jika membutuhkan bantuan akses atau reset kata sandi.
                            </p>
                        </div>
                    </Form>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ==========================================================================
   Animasi Gelombang Pemisah (Vertical Wave Divider)
   ========================================================================== */

/* Gelombang Utama (Warna Form Login): Mengalir Naik */
.vert-wave-layer-main {
    animation: waveFlowUp 9s linear infinite;
    will-change: transform;
}

/* Gelombang Aksen 1: Cyan Mengalir Naik Cepat */
.vert-wave-layer-1 {
    animation: waveFlowUp 6.5s linear infinite;
    will-change: transform;
}

/* Gelombang Aksen 2: Indigo Mengalir Berlawanan */
.vert-wave-layer-2 {
    animation: waveFlowDown 11s linear infinite;
    will-change: transform;
}

@keyframes waveFlowUp {
    0% {
        transform: translate3d(0, 0, 0);
    }
    100% {
        transform: translate3d(0, -50%, 0);
    }
}

@keyframes waveFlowDown {
    0% {
        transform: translate3d(0, -50%, 0);
    }
    100% {
        transform: translate3d(0, 0, 0);
    }
}

/* Mobile Horizontal Wave Track (Main) */
.mobile-wave-track {
    animation: waveFlowHorizontal 8s linear infinite;
    will-change: transform;
}

/* Mobile Wave Accent Layer (Cyan): Mengalir Berlawanan Arah */
.mobile-wave-accent {
    animation: waveFlowHorizontalRev 11s linear infinite;
    will-change: transform;
}

@keyframes waveFlowHorizontal {
    0% {
        transform: translate3d(0, 0, 0);
    }
    100% {
        transform: translate3d(-50%, 0, 0);
    }
}

@keyframes waveFlowHorizontalRev {
    0% {
        transform: translate3d(-50%, 0, 0);
    }
    100% {
        transform: translate3d(0, 0, 0);
    }
}

/* Kursor Ketik Halus & Berpendar (Smooth Blink) */
.typewriter-cursor {
    animation: cursorSmoothBlink 0.95s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes cursorSmoothBlink {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.1;
    }
}

@media (prefers-reduced-motion: reduce) {
    .vert-wave-layer-main,
    .vert-wave-layer-1,
    .vert-wave-layer-2,
    .mobile-wave-track,
    .mobile-wave-accent,
    .typewriter-cursor {
        animation: none !important;
    }
}
</style>
