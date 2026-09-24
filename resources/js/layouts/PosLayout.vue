<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    BarChart3,
    Bell,
    Boxes,
    CheckCircle,
    ChevronsUpDown,
    LayoutDashboard,
    LogOut,
    Menu,
    Moon,
    ReceiptText,
    RefreshCw,
    Settings,
    ShoppingBag,
    ShoppingCart,
    Store,
    Sun,
    Tags,
    Truck,
    Users,
    X,
    MessageCircle,
    ShieldAlert,
    ExternalLink,
    Sparkles,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Toaster } from '@/components/ui/sonner';
import BottomNav from '@/components/pos/BottomNav.vue';
import { useAppearance } from '@/composables/useAppearance';
import { getInitials } from '@/composables/useInitials';
import { rupiah } from '@/lib/format';
import { fetchBarang, fetchPrediksiStok } from '@/services/barangService';
import { fetchPendingVoidRequests } from '@/services/penjualanService';
import { usePosStore } from '@/stores/pos';
import type { Barang, Penjualan, PrediksiStokItem } from '@/types/pos';

const pos = usePosStore();
const { appearance, resolvedAppearance, updateAppearance } = useAppearance();
const isDark = computed(() => resolvedAppearance.value === 'dark');

function toggleDarkMode() {
    updateAppearance(isDark.value ? 'light' : 'dark');
}
const sidebarOpen = ref(false);
const showProfile = ref(false);
const showNotification = ref(false);
const notifDropdownRef = ref<HTMLElement | null>(null);
const lowStockItems = ref<any[]>([]);
const lowStockLoading = ref(false);
const pendingVoidItems = ref<Penjualan[]>([]);
const pendingVoidLoading = ref(false);

const isSuperAdmin = computed(
    () =>
        pos.isSuper ||
        pos.isDev ||
        ['super admin', 'developer'].includes(pos.effectiveRole.toLowerCase()),
);
const isAdminRole = computed(
    () => pos.effectiveRole.toLowerCase() === 'admin',
);
const hasVoidManagement = computed(
    () => isAdminRole.value || isSuperAdmin.value,
);

const pendingForAdmin = computed(() =>
    pendingVoidItems.value.filter((p) =>
        ['pending_admin', 'pending'].includes(p.status_void ?? ''),
    ),
);

const pendingForSuperAdmin = computed(() =>
    pendingVoidItems.value.filter(
        (p) => p.status_void === 'pending_super_admin',
    ),
);

const myPendingVoids = computed(() => {
    if (isAdminRole.value) return pendingForAdmin.value;
    if (isSuperAdmin.value) return pendingForSuperAdmin.value;
    return [];
});

const totalNotifCount = computed(() => {
    return lowStockItems.value.length + myPendingVoids.value.length;
});

const profileInitials = computed(() =>
    getInitials(pos.me?.nama_lengkap ?? 'U')
        .slice(0, 2)
        .toUpperCase(),
);

function logout() {
    router.post('/logout');
}

const showBack = computed(() => usePage().url !== '/dashboard');

function goBack() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit('/dashboard');
    }
}

const menus = [
    {
        key: 'dashboard',
        label: 'Dashboard',
        href: '/dashboard',
        icon: LayoutDashboard,
    },
    { key: 'kasir', label: 'Kasir', href: '/kasir', icon: ShoppingCart },
    { key: 'produk', label: 'Produk', href: '/produk', icon: ShoppingBag },
    { key: 'stok', label: 'Stok', href: '/stok', icon: Boxes },
    { key: 'kategori', label: 'Kategori', href: '/kategori', icon: Tags },
    { key: 'pembelian', label: 'Pembelian', href: '/pembelian', icon: Truck },
    {
        key: 'penjualan',
        label: 'Penjualan',
        href: '/penjualan',
        icon: ReceiptText,
    },
    { key: 'supplier', label: 'Supplier', href: '/supplier', icon: Store },
    { key: 'pelanggan', label: 'Pelanggan', href: '/pelanggan', icon: Users },
    { key: 'laporan', label: 'Laporan', href: '/laporan', icon: BarChart3 },
    { key: 'users', label: 'Manajemen Pengguna', href: '/users', icon: Users },
    {
        key: 'pengaturan',
        label: 'Pengaturan',
        href: '/pengaturan',
        icon: Settings,
    },
];

const visibleMenus = computed(() => menus.filter((m) => pos.can(m.key)));
const currentUrl = computed(() => usePage().url);

async function loadLowStockAlert() {
    if (!pos.idSekolah) return;
    try {
        lowStockLoading.value = true;
        if (isAdminRole.value || isSuperAdmin.value) {
            const predRes = await fetchPrediksiStok({
                id_sekolah: pos.idSekolah,
                days: 7,
            });
            lowStockItems.value = (predRes.items ?? []).filter(
                (item) =>
                    item.status === 'kritis' ||
                    item.status === 'waspada' ||
                    Number(item.stok) <= 10,
            );
        } else {
            const res = await fetchBarang({
                id_sekolah: pos.idSekolah,
                stok_rendah: 10,
                is_active: true,
                per_page: 50,
            });
            lowStockItems.value = res.data ?? [];
        }
    } catch {
        // silent fail in global layout
    } finally {
        lowStockLoading.value = false;
    }
}

async function loadPendingVoidsAlert() {
    if (!pos.idSekolah || !hasVoidManagement.value) {
        pendingVoidItems.value = [];
        return;
    }
    try {
        pendingVoidLoading.value = true;
        const res = await fetchPendingVoidRequests(pos.idSekolah);
        pendingVoidItems.value = res ?? [];
    } catch {
        // silent fail in global layout
    } finally {
        pendingVoidLoading.value = false;
    }
}

async function refreshAllAlerts() {
    await Promise.allSettled([loadLowStockAlert(), loadPendingVoidsAlert()]);
}

function handleStockChanged() {
    void loadLowStockAlert();
}

function handleVoidChanged() {
    void loadPendingVoidsAlert();
}

function getWaUrl(phone: string | null | undefined, t: Penjualan): string {
    if (!phone) return '#';
    let clean = phone.replace(/[^0-9]/g, '');
    if (clean.startsWith('0')) {
        clean = '62' + clean.slice(1);
    } else if (!clean.startsWith('62')) {
        clean = '62' + clean;
    }
    let namaKasir =
        t.void_requester?.nama_lengkap ||
        t.kasir?.nama_lengkap ||
        'Kasir';
    if (
        namaKasir.toLowerCase().includes('superadmin') ||
        namaKasir.toLowerCase().includes('super admin')
    ) {
        namaKasir = `kasir_smkn${t.id_sekolah ?? '2'}`;
    }
    const namaSekolah =
        t.sekolah?.nama_sekolah ||
        pos.sekolahAktif?.nama_sekolah ||
        'SMKN 2 Tasikmalaya';
    const text = `Halo ${namaKasir}, saya dari Admin ${namaSekolah}. Terkait pengajuan pembatalan (void) Transaksi #${t.id_penjualan} senilai ${rupiah(t.total_faktur)} dengan alasan "${t.alasan_void ?? ''}", saya ingin cross-check apakah benar ada kesalahan input?`;
    return `https://wa.me/${clean}?text=${encodeURIComponent(text)}`;
}

function handleClickOutside(e: MouseEvent) {
    if (
        showNotification.value &&
        notifDropdownRef.value &&
        !notifDropdownRef.value.contains(e.target as Node)
    ) {
        showNotification.value = false;
    }
}

let pollInterval: ReturnType<typeof setInterval> | null = null;

watch(
    () => pos.idSekolah,
    (val) => {
        if (val) {
            void loadLowStockAlert();
            void loadPendingVoidsAlert();
        }
    },
);

watch(
    () => pos.effectiveRole,
    () => {
        if (hasVoidManagement.value) {
            void loadPendingVoidsAlert();
        } else {
            pendingVoidItems.value = [];
        }
    },
);

onMounted(() => {
    void pos.init().then(() => {
        void loadLowStockAlert();
        void loadPendingVoidsAlert();
    });
    window.addEventListener('click', handleClickOutside);
    window.addEventListener('pos:stock-changed', handleStockChanged);
    window.addEventListener('pos:void-changed', handleVoidChanged);
    window.addEventListener('focus', () => {
        if (hasVoidManagement.value) void loadPendingVoidsAlert();
    });

    pollInterval = setInterval(() => {
        if (
            hasVoidManagement.value &&
            typeof document !== 'undefined' &&
            document.visibilityState === 'visible'
        ) {
            void loadPendingVoidsAlert();
        }
    }, 15000);
});

onUnmounted(() => {
    window.removeEventListener('click', handleClickOutside);
    window.removeEventListener('pos:stock-changed', handleStockChanged);
    window.removeEventListener('pos:void-changed', handleVoidChanged);
    if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
    <div class="relative min-h-screen w-full overflow-x-hidden bg-gradient-to-br from-[#0c2356] via-[#143f91] to-[#1e58c8] text-slate-900 transition-colors duration-200 dark:from-[#061226] dark:via-[#091c3d] dark:to-[#0d2757] dark:text-slate-100">
        <!-- Background Geometric Curves & Ambient Glows for Content Area (Smooth, Elegan, Renggang & Tidak Terlalu Ramai) -->
        <div class="pointer-events-none fixed inset-0 overflow-hidden z-0">
            <!-- Subtle SVG Curves -->
            <div class="absolute inset-0 opacity-[0.08] dark:opacity-[0.05]">
                <svg class="h-full w-full" viewBox="0 0 1440 900" fill="none">
                    <!-- Busur anggun kanan atas (tidak ramai, terpisah dengan renggang & lembut) -->
                    <circle cx="1250" cy="180" r="340" stroke="white" stroke-width="1.2" stroke-dasharray="6 8" />
                    <circle cx="1250" cy="180" r="580" stroke="white" stroke-width="1.5" />
                    <circle cx="1250" cy="180" r="880" stroke="white" stroke-width="1" stroke-dasharray="10 10" />

                    <!-- Busur lembut sudut kiri bawah (sangat halus) -->
                    <circle cx="280" cy="800" r="420" stroke="white" stroke-width="1" stroke-dasharray="8 8" />
                    <circle cx="280" cy="800" r="680" stroke="white" stroke-width="1.2" />
                </svg>
            </div>

            <!-- Ambient Glow Orbs yang menyatu dengan Sidebar dan Header -->
            <div class="absolute top-1/4 right-1/6 h-96 w-96 rounded-full bg-cyan-400/10 blur-3xl" />
            <div class="absolute bottom-1/4 left-1/3 h-80 w-80 rounded-full bg-blue-400/10 blur-3xl" />
        </div>

        <!-- Sidebar desktop -->
        <aside
            class="fixed inset-y-0 left-0 z-30 hidden w-60 flex-col overflow-hidden bg-gradient-to-b from-[#0c2356] via-[#143f91] to-[#1e58c8] text-white shadow-2xl border-r border-white/10 transition-colors duration-200 md:flex dark:border-r dark:border-white/10 dark:from-[#061226] dark:via-[#091c3d] dark:to-[#0d2757]"
        >
            <!-- Background Decorative Radial Curves & Geometric Accents (Identik dengan Login) -->
            <div class="pointer-events-none absolute inset-0 opacity-15 overflow-hidden">
                <svg class="h-full w-full" viewBox="0 0 300 800" fill="none" preserveAspectRatio="none">
                    <circle cx="50" cy="120" r="140" stroke="white" stroke-width="1.5" stroke-dasharray="4 6" />
                    <circle cx="50" cy="120" r="220" stroke="white" stroke-width="1.5" />
                    <circle cx="50" cy="120" r="300" stroke="white" stroke-width="1" stroke-dasharray="8 8" />
                    <circle cx="50" cy="120" r="380" stroke="white" stroke-width="1" />
                </svg>
            </div>

            <!-- Ambient Glow Orbs -->
            <div class="pointer-events-none absolute -top-16 -left-16 h-48 w-48 rounded-full bg-cyan-400/20 blur-3xl" />
            <div class="pointer-events-none absolute bottom-12 -right-12 h-44 w-44 rounded-full bg-blue-400/20 blur-3xl" />

            <!-- Brand Header -->
            <div class="relative z-10 flex items-center gap-3 px-5 pt-6 pb-4 border-b border-white/10">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white p-1 shadow-lg shadow-black/20 border border-white/25 transition-transform hover:scale-105">
                    <img
                        src="/logoScholify.png"
                        alt="Logo Scholify"
                        class="h-full w-full object-contain"
                    />
                </div>
                <div>
                    <p class="text-base font-black tracking-tight text-white leading-none">Scholify</p>
                    <p class="text-[9px] font-bold tracking-widest text-blue-200 uppercase mt-1">Smart School POS</p>
                </div>
            </div>

            <!-- Navigasi Menu -->
            <nav class="relative z-10 flex-1 space-y-1 overflow-y-auto px-3 py-3">
                <template v-if="!pos.me">
                    <div
                        v-for="i in 6"
                        :key="i"
                        class="h-10 animate-pulse rounded-xl bg-white/10"
                    />
                </template>
                <template v-else>
                    <Link
                        v-for="m in visibleMenus"
                        :key="m.key"
                        :href="m.href"
                        :class="[
                            'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition active-press',
                            currentUrl.startsWith(m.href)
                                ? 'bg-white text-[#0c2356] font-bold shadow-lg shadow-black/15'
                                : 'text-blue-100 hover:bg-white/15 hover:text-white',
                        ]"
                    >
                        <component :is="m.icon" class="h-4.5 w-4.5 shrink-0" />
                        {{ m.label }}
                    </Link>
                </template>
            </nav>

            <!-- User Profile Bottom Bar -->
            <div class="relative z-10 border-t border-white/15 bg-black/15 p-3 backdrop-blur-md">
                <div class="relative">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-xl px-2 py-2 text-left hover:bg-white/10 transition cursor-pointer"
                        @click="showProfile = !showProfile"
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-white/25 bg-gradient-to-br from-cyan-400/30 to-blue-500/30 text-sm font-bold text-white shadow-md backdrop-blur-md"
                        >
                            {{ profileInitials || 'U' }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-bold text-white"
                            >
                                {{ pos.me?.nama_lengkap ?? 'Memuat…' }}
                            </p>
                            <p class="truncate text-[10px] font-medium text-blue-200">
                                {{
                                    pos.me?.sekolah?.nama_sekolah ??
                                    pos.sekolahAktif?.nama_sekolah ??
                                    pos.ownRole
                                }}
                            </p>
                        </div>
                        <ChevronsUpDown
                            class="h-4 w-4 shrink-0 text-blue-200"
                        />
                    </button>
                    <Transition
                        enter-active-class="transition duration-150 ease-out"
                        enter-from-class="opacity-0 scale-95 translate-y-1"
                        enter-to-class="opacity-100 scale-100 translate-y-0"
                        leave-active-class="transition duration-100 ease-in"
                        leave-from-class="opacity-100 scale-100 translate-y-0"
                        leave-to-class="opacity-0 scale-95 translate-y-1"
                    >
                        <div
                            v-if="showProfile"
                            class="absolute right-0 bottom-full left-0 mb-2 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900"
                        >
                            <button
                                type="button"
                                class="flex w-full items-center justify-between border-b border-slate-100 px-3 py-2.5 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-800"
                                @click="toggleDarkMode"
                            >
                                <span class="flex items-center gap-2">
                                    <Sun v-if="isDark" class="h-4 w-4 text-amber-400" />
                                    <Moon v-else class="h-4 w-4 text-slate-500" />
                                    Mode Tampilan
                                </span>
                                <span
                                    class="rounded px-1.5 py-0.5 text-[10px] font-bold"
                                    :class="isDark ? 'bg-slate-800 text-amber-300' : 'bg-slate-100 text-slate-600'"
                                >
                                    {{ isDark ? 'Gelap' : 'Terang' }}
                                </span>
                            </button>
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
                                @click="
                                    showProfile = false;
                                    logout();
                                "
                            >
                                <LogOut class="h-4 w-4" /> Keluar
                            </button>
                        </div>
                    </Transition>
                </div>
            </div>
        </aside>

        <!-- Drawer mobile -->
        <Teleport to="body">
            <div
                class="fixed inset-0 z-50 lg:hidden pointer-events-none"
                :class="{ 'pointer-events-auto': sidebarOpen }"
            >
                <!-- Backdrop with smooth blur and fade (stays blurred until closed) -->
                <Transition
                    enter-active-class="backdrop-enter-active"
                    enter-from-class="backdrop-enter-from"
                    enter-to-class="backdrop-enter-to"
                    leave-active-class="backdrop-leave-active"
                    leave-from-class="backdrop-leave-from"
                    leave-to-class="backdrop-leave-to"
                >
                    <div
                        v-if="sidebarOpen"
                        class="absolute inset-0 backdrop-active cursor-pointer pointer-events-auto"
                        @click="sidebarOpen = false"
                    />
                </Transition>

                <!-- Sidebar sliding in and out smoothly -->
                <Transition
                    enter-active-class="sidebar-slide-enter"
                    enter-from-class="-translate-x-full"
                    enter-to-class="translate-x-0"
                    leave-active-class="sidebar-slide-leave"
                    leave-from-class="translate-x-0"
                    leave-to-class="-translate-x-full"
                >
                    <aside
                        v-if="sidebarOpen"
                        class="absolute inset-y-0 left-0 flex w-72 flex-col overflow-hidden bg-gradient-to-b from-[#0c2356] via-[#143f91] to-[#1e58c8] text-white shadow-2xl border-r border-white/10 transition-colors duration-200 dark:border-r dark:border-white/10 dark:from-[#061226] dark:via-[#091c3d] dark:to-[#0d2757] pointer-events-auto"
                    >
                        <!-- Background Decorative Radial Curves & Geometric Accents (Identik dengan Login) -->
                        <div class="pointer-events-none absolute inset-0 opacity-15 overflow-hidden">
                            <svg class="h-full w-full" viewBox="0 0 350 800" fill="none" preserveAspectRatio="none">
                                <circle cx="50" cy="120" r="140" stroke="white" stroke-width="1.5" stroke-dasharray="4 6" />
                                <circle cx="50" cy="120" r="220" stroke="white" stroke-width="1.5" />
                                <circle cx="50" cy="120" r="300" stroke="white" stroke-width="1" stroke-dasharray="8 8" />
                                <circle cx="50" cy="120" r="380" stroke="white" stroke-width="1" />
                            </svg>
                        </div>

                        <!-- Ambient Glow Orbs -->
                        <div class="pointer-events-none absolute -top-16 -left-16 h-48 w-48 rounded-full bg-cyan-400/20 blur-3xl" />
                        <div class="pointer-events-none absolute bottom-12 -right-12 h-44 w-44 rounded-full bg-blue-400/20 blur-3xl" />

                        <!-- Brand Header -->
                        <div
                            class="relative z-10 flex items-center justify-between px-5 pt-6 pb-4 border-b border-white/10"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white p-1 shadow-lg shadow-black/20 border border-white/25">
                                    <img
                                        src="/logoScholify.png"
                                        alt="Logo Scholify"
                                        class="h-full w-full object-contain"
                                    />
                                </div>
                                <div>
                                    <p class="text-base font-black tracking-tight text-white leading-none">Scholify</p>
                                    <p class="text-[9px] font-bold tracking-widest text-blue-200 uppercase mt-1">
                                        Smart School POS
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="rounded-xl p-1.5 hover:bg-white/10 active-press transition text-blue-200 hover:text-white cursor-pointer"
                                @click="sidebarOpen = false"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <!-- Navigasi Menu Mobile -->
                        <nav class="relative z-10 flex-1 space-y-1 overflow-y-auto px-3 py-3">
                            <template v-if="!pos.me">
                                <div
                                    v-for="i in 6"
                                    :key="i"
                                    class="h-11 animate-pulse rounded-xl bg-white/10"
                                />
                            </template>
                            <template v-else>
                                <Link
                                    v-for="m in visibleMenus"
                                    :key="m.key"
                                    :href="m.href"
                                    :class="[
                                        'flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold transition active-press',
                                        currentUrl.startsWith(m.href)
                                            ? 'bg-white text-[#0c2356] font-bold shadow-lg shadow-black/15'
                                            : 'text-blue-100 hover:bg-white/15 hover:text-white',
                                    ]"
                                    @click="sidebarOpen = false"
                                >
                                    <component
                                        :is="m.icon"
                                        class="h-5 w-5 shrink-0"
                                    />
                                    {{ m.label }}
                                </Link>
                            </template>
                        </nav>

                        <!-- User Profile Bottom Bar Mobile -->
                        <div class="relative z-10 border-t border-white/15 bg-black/15 p-3 backdrop-blur-md">
                            <div class="relative">
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-3 rounded-xl px-2 py-2 text-left hover:bg-white/10 transition cursor-pointer active-press"
                                    @click="showProfile = !showProfile"
                                >
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-white/25 bg-gradient-to-br from-cyan-400/30 to-blue-500/30 text-sm font-bold text-white shadow-md backdrop-blur-md"
                                    >
                                        {{ profileInitials || 'U' }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="truncate text-sm font-bold text-white"
                                        >
                                            {{ pos.me?.nama_lengkap ?? 'Memuat…' }}
                                        </p>
                                        <p class="truncate text-[10px] font-medium text-blue-200">
                                            {{
                                                pos.me?.sekolah?.nama_sekolah ??
                                                pos.sekolahAktif?.nama_sekolah ??
                                                pos.ownRole
                                            }}
                                        </p>
                                    </div>
                                    <ChevronsUpDown
                                        class="h-4 w-4 shrink-0 text-blue-200"
                                    />
                                </button>
                                <Transition
                                    enter-active-class="transition duration-150 ease-out"
                                    enter-from-class="opacity-0 scale-95 translate-y-1"
                                    enter-to-class="opacity-100 scale-100 translate-y-0"
                                    leave-active-class="transition duration-100 ease-in"
                                    leave-from-class="opacity-100 scale-100 translate-y-0"
                                    leave-to-class="opacity-0 scale-95 translate-y-1"
                                >
                                    <div
                                        v-if="showProfile"
                                        class="absolute right-0 bottom-full left-0 mb-2 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900"
                                    >
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between border-b border-slate-100 px-3 py-2.5 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-800"
                                            @click="toggleDarkMode"
                                        >
                                            <span class="flex items-center gap-2">
                                                <Sun v-if="isDark" class="h-4 w-4 text-amber-400" />
                                                <Moon v-else class="h-4 w-4 text-slate-500" />
                                                Mode Tampilan
                                            </span>
                                            <span
                                                class="rounded px-1.5 py-0.5 text-[10px] font-bold"
                                                :class="isDark ? 'bg-slate-800 text-amber-300' : 'bg-slate-100 text-slate-600'"
                                            >
                                                {{ isDark ? 'Gelap' : 'Terang' }}
                                            </span>
                                        </button>
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
                                            @click="
                                                showProfile = false;
                                                logout();
                                            "
                                        >
                                            <LogOut class="h-4 w-4" /> Keluar
                                        </button>
                                    </div>
                                </Transition>
                            </div>
                        </div>
                    </aside>
                </Transition>
            </div>
        </Teleport>

        <div class="min-w-0 w-full md:pl-60">
            <!-- Header (Menyatu Alami dengan Layout Latar Konten) -->
            <header
                class="sticky top-0 z-30 border-b border-white/10 bg-[#0c2356]/40 backdrop-blur-md text-white transition-all duration-300 dark:border-white/10 dark:bg-[#061226]/50 will-change-[backdrop-filter]"
            >

                <div class="relative z-10 flex items-center gap-1.5 sm:gap-2.5 px-3 sm:px-6 py-2.5 min-w-0">
                    <button
                        v-if="showBack"
                        type="button"
                        title="Kembali"
                        class="rounded-xl p-2 text-blue-100 hover:bg-white/15 hover:text-white transition active-press md:hidden cursor-pointer"
                        @click="goBack"
                    >
                        <ArrowLeft class="h-5 w-5" />
                    </button>
                    <button
                        type="button"
                        title="Buka menu"
                        class="rounded-xl p-2 text-blue-100 hover:bg-white/15 hover:text-white transition active-press md:hidden cursor-pointer"
                        @click="sidebarOpen = true"
                    >
                        <Menu class="h-5 w-5" />
                    </button>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-black tracking-tight text-white leading-tight">
                            Scholify
                        </p>
                        <p class="truncate text-[11px] font-medium text-blue-200/90 mt-0.5">
                            {{ pos.sekolahAktif?.nama_sekolah ?? '…' }} ·
                            {{ pos.me?.nama_lengkap ?? '…' }} ({{
                                pos.ownRole
                            }})
                        </p>
                    </div>
                    <!-- Developer: pindah sekolah (tanpa ganti peran, privasi multi-sekolah) -->
                    <template v-if="pos.isDev">
                        <select
                            :value="pos.idSekolah"
                            title="Pindah sekolah"
                            class="max-w-28 sm:max-w-56 truncate rounded-xl border border-white/20 bg-white/10 px-2.5 py-1.5 text-xs font-medium text-white backdrop-blur-md transition hover:bg-white/20 focus:ring-2 focus:ring-cyan-300 focus:outline-none dark:border-white/15 dark:bg-black/30 dark:text-slate-100 cursor-pointer"
                            @change="
                                pos.setCtxSekolah(
                                    Number(
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    ),
                                )
                            "
                        >
                            <option
                                v-for="s in pos.sekolahList"
                                :key="s.id_sekolah"
                                :value="s.id_sekolah"
                                class="bg-[#0c2356] text-white dark:bg-slate-900"
                            >
                                {{ s.nama_sekolah }}
                            </option>
                        </select>
                        <span
                            class="hidden sm:inline-flex items-center rounded-xl bg-cyan-400/20 border border-cyan-300/30 px-2.5 py-1 text-[11px] font-bold tracking-wide text-cyan-200"
                        >
                            Developer
                        </span>
                    </template>
                    <!-- Super Admin: badge peran paten (tanpa ganti peran) -->
                    <template v-else-if="pos.isSuper">
                        <span
                            class="hidden sm:inline-flex items-center rounded-xl bg-amber-400/20 border border-amber-300/30 px-2.5 py-1 text-[11px] font-bold tracking-wide text-amber-200"
                        >
                            Super Admin
                        </span>
                    </template>
                    <!-- Admin: badge peran paten -->
                    <template v-else-if="pos.ownRole.toLowerCase() === 'admin'">
                        <span
                            class="hidden sm:inline-flex items-center rounded-xl bg-blue-400/20 border border-blue-300/30 px-2.5 py-1 text-[11px] font-bold tracking-wide text-blue-200"
                        >
                            Admin
                        </span>
                    </template>
                    <!-- Kasir: badge peran paten -->
                    <template v-else-if="pos.ownRole.toLowerCase() === 'kasir'">
                        <span
                            class="hidden sm:inline-flex items-center rounded-xl bg-emerald-400/20 border border-emerald-300/30 px-2.5 py-1 text-[11px] font-bold tracking-wide text-emerald-200"
                        >
                            Kasir
                        </span>
                    </template>

                    <!-- Tombol Cepat Toggle Mode Gelap / Terang -->
                    <button
                        type="button"
                        :title="isDark ? 'Beralih ke Mode Terang (Light Mode)' : 'Beralih ke Mode Gelap (Dark Mode)'"
                        class="rounded-xl p-2 text-blue-100 hover:bg-white/15 hover:text-white transition cursor-pointer backdrop-blur-md active-press"
                        @click="toggleDarkMode"
                    >
                        <Sun v-if="isDark" class="h-5 w-5 text-amber-300 transition transform rotate-0 hover:rotate-45" />
                        <Moon v-else class="h-5 w-5 text-cyan-200 transition transform hover:-rotate-12" />
                    </button>

                    <!-- Notifikasi Dropdown Popover (Void & Stok) -->
                    <div ref="notifDropdownRef" class="relative">
                        <button
                            type="button"
                            title="Pemberitahuan Sistem"
                            class="relative cursor-pointer rounded-xl p-2 text-blue-100 transition hover:bg-white/15 hover:text-white active-press"
                            :class="{
                                'bg-white/20 text-white ring-1 ring-white/30':
                                    showNotification,
                            }"
                            @click.stop="showNotification = !showNotification"
                        >
                            <Bell class="h-5 w-5" />
                            <span
                                v-if="totalNotifCount > 0"
                                :class="[
                                    'absolute -top-0.5 -right-0.5 flex h-4.5 min-w-4.5 items-center justify-center rounded-full px-1 text-[10px] font-bold text-white shadow-sm ring-2 ring-[#0c2356]',
                                    myPendingVoids.length > 0
                                        ? 'bg-rose-600 animate-bounce'
                                        : 'bg-red-600 animate-pulse',
                                ]"
                            >
                                {{
                                    totalNotifCount > 99
                                        ? '99+'
                                        : totalNotifCount
                                }}
                            </span>
                        </button>

                        <Transition
                            enter-active-class="transition duration-150 ease-out"
                            enter-from-class="opacity-0 scale-95 translate-y-1"
                            enter-to-class="opacity-100 scale-100 translate-y-0"
                            leave-active-class="transition duration-100 ease-in"
                            leave-from-class="opacity-100 scale-100 translate-y-0"
                            leave-to-class="opacity-0 scale-95 translate-y-1"
                        >
                            <div
                                v-if="showNotification"
                                class="absolute right-0 top-full mt-2 w-80 sm:w-96 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl z-50 text-slate-900 dark:border-slate-800 dark:bg-slate-900 dark:text-white"
                            >
                                <!-- Header Popover -->
                                <div
                                    class="flex items-center justify-between border-b border-slate-100 bg-slate-50/90 px-4 py-3 dark:border-slate-800 dark:bg-slate-800/90"
                                >
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="flex h-7 w-7 items-center justify-center rounded-lg shadow-xs"
                                            :class="
                                                myPendingVoids.length > 0
                                                    ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300'
                                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300'
                                            "
                                        >
                                            <component
                                                :is="
                                                    myPendingVoids.length > 0
                                                        ? (isAdminRole ? MessageCircle : ShieldAlert)
                                                        : AlertTriangle
                                                "
                                                class="h-4 w-4"
                                            />
                                        </span>
                                        <div>
                                            <h4
                                                class="text-xs font-bold text-slate-900 dark:text-white"
                                            >
                                                Pusat Pemberitahuan
                                            </h4>
                                            <p
                                                class="text-[10px] text-slate-500 dark:text-slate-400"
                                            >
                                                <template v-if="myPendingVoids.length > 0">
                                                    {{ myPendingVoids.length }} void butuh tindakan · {{ lowStockItems.length }} stok menipis
                                                </template>
                                                <template v-else>
                                                    {{ lowStockItems.length }} produk memerlukan restock
                                                </template>
                                            </p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        title="Muat ulang semua notifikasi"
                                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200/70 hover:text-slate-700 dark:hover:bg-slate-700 dark:hover:text-slate-200 transition cursor-pointer"
                                        :disabled="lowStockLoading || pendingVoidLoading"
                                        @click="refreshAllAlerts"
                                    >
                                        <RefreshCw
                                            class="h-3.5 w-3.5"
                                            :class="{
                                                'animate-spin':
                                                    lowStockLoading ||
                                                    pendingVoidLoading,
                                            }"
                                        />
                                    </button>
                                </div>

                                <!-- Konten Scrollable -->
                                <div class="max-h-80 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800">
                                    <!-- ================= SEKSI 1: PENGAJUAN VOID BUTUH TINDAKAN ================= -->
                                    <div
                                        v-if="hasVoidManagement && myPendingVoids.length > 0"
                                        class="bg-amber-50/20 dark:bg-amber-950/10"
                                    >
                                        <!-- Header Sub Seksi Void -->
                                        <div
                                            class="flex items-center justify-between px-4 py-2 text-xs font-bold"
                                            :class="
                                                isAdminRole
                                                    ? 'bg-amber-100/70 text-amber-900 dark:bg-amber-950/60 dark:text-amber-300'
                                                    : 'bg-blue-100/70 text-blue-900 dark:bg-blue-950/60 dark:text-blue-300'
                                            "
                                        >
                                            <div class="flex items-center gap-1.5">
                                                <component
                                                    :is="isAdminRole ? MessageCircle : ShieldAlert"
                                                    class="h-4 w-4 shrink-0"
                                                />
                                                <span>
                                                    {{
                                                        isAdminRole
                                                            ? `Verifikasi Kasir (${pendingForAdmin.length})`
                                                            : `Persetujuan Final Super Admin (${pendingForSuperAdmin.length})`
                                                    }}
                                                </span>
                                            </div>
                                            <Link
                                                href="/penjualan"
                                                class="text-[10px] font-semibold underline hover:opacity-80 transition"
                                                @click="showNotification = false"
                                            >
                                                Buka Riwayat
                                            </Link>
                                        </div>

                                        <!-- List Card Void -->
                                        <div class="divide-y divide-slate-100 dark:divide-slate-800/80">
                                            <div
                                                v-for="v in myPendingVoids"
                                                :key="v.id_penjualan"
                                                class="p-3.5 transition hover:bg-slate-50/90 dark:hover:bg-slate-800/50"
                                            >
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="font-bold text-xs text-slate-800 dark:text-slate-100">
                                                        Transaksi #{{ v.id_penjualan }}
                                                    </span>
                                                    <span class="text-xs font-extrabold text-emerald-600 dark:text-emerald-400">
                                                        {{ rupiah(v.total_faktur) }}
                                                    </span>
                                                </div>

                                                <!-- Admin View -->
                                                <template v-if="isAdminRole">
                                                    <div class="mt-1 flex items-center justify-between text-[11px] text-slate-600 dark:text-slate-300">
                                                        <span>Kasir: <strong class="font-semibold">{{ v.kasir?.nama_lengkap ?? 'Kasir' }}</strong></span>
                                                        <span v-if="v.void_telepon_kasir" class="font-mono text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold">
                                                            WA: {{ v.void_telepon_kasir }}
                                                        </span>
                                                    </div>
                                                    <p class="mt-1 text-[11px] italic text-slate-500 dark:text-slate-400 line-clamp-2 bg-amber-50 dark:bg-amber-950/30 p-1.5 rounded border border-amber-200/50 dark:border-amber-900/40">
                                                        "{{ v.alasan_void }}"
                                                    </p>
                                                </template>

                                                <!-- Super Admin View -->
                                                <template v-else-if="isSuperAdmin">
                                                    <div class="mt-1 text-[11px] text-slate-600 dark:text-slate-300">
                                                        Diverifikasi oleh: <strong class="font-semibold">{{ v.admin_verifier?.nama_lengkap ?? 'Admin' }}</strong>
                                                    </div>
                                                    <p class="mt-1 text-[11px] italic text-slate-500 dark:text-slate-400 line-clamp-2 bg-blue-50 dark:bg-blue-950/30 p-1.5 rounded border border-blue-200/50 dark:border-blue-900/40">
                                                        "{{ v.void_admin_notes || v.alasan_void }}"
                                                    </p>
                                                </template>

                                                <div class="mt-2 flex items-center justify-end gap-1.5">
                                                    <a
                                                        v-if="isAdminRole && v.void_telepon_kasir"
                                                        :href="getWaUrl(v.void_telepon_kasir, v)"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-2.5 py-1 text-[11px] font-bold text-white shadow-xs transition hover:bg-emerald-700"
                                                    >
                                                        <MessageCircle class="h-3 w-3" />
                                                        <span>Chat WA Kasir</span>
                                                    </a>
                                                    <Link
                                                        href="/penjualan"
                                                        class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-[11px] font-bold text-white shadow-xs transition"
                                                        :class="
                                                            isAdminRole
                                                                ? 'bg-amber-600 hover:bg-amber-700'
                                                                : 'bg-blue-600 hover:bg-blue-700'
                                                        "
                                                        @click="showNotification = false"
                                                    >
                                                        <span>{{ isAdminRole ? 'Buka & Proses' : 'Tinjau & Putuskan' }}</span>
                                                        <ExternalLink class="h-3 w-3" />
                                                    </Link>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ================= SEKSI 2: PERINGATAN STOK MENIPIS ================= -->
                                    <div>
                                        <div
                                            v-if="hasVoidManagement && myPendingVoids.length > 0"
                                            class="flex items-center justify-between border-t border-slate-100 bg-slate-100/70 px-4 py-1.5 text-[11px] font-bold text-slate-700 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-300"
                                        >
                                            <div class="flex items-center gap-1">
                                                <AlertTriangle class="h-3.5 w-3.5 text-amber-500" />
                                                <span>Peringatan Stok ({{ lowStockItems.length }})</span>
                                            </div>
                                            <Link
                                                v-if="pos.can('stok')"
                                                href="/stok"
                                                class="text-[10px] font-semibold underline hover:opacity-80 transition"
                                                @click="showNotification = false"
                                            >
                                                Lihat Stok
                                            </Link>
                                        </div>

                                        <div
                                            v-if="lowStockLoading && lowStockItems.length === 0"
                                            class="p-6 text-center"
                                        >
                                            <RefreshCw class="mx-auto mb-2 h-6 w-6 animate-spin text-slate-400" />
                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                Memeriksa stok produk…
                                            </p>
                                        </div>

                                        <div
                                            v-else-if="lowStockItems.length === 0 && myPendingVoids.length === 0"
                                            class="p-6 text-center"
                                        >
                                            <div
                                                class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400"
                                            >
                                                <CheckCircle class="h-5 w-5" />
                                            </div>
                                            <p class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                                Semua Berjalan Normal
                                            </p>
                                            <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                                Tidak ada antrean pembatalan transaksi maupun peringatan stok produk.
                                            </p>
                                        </div>

                                        <div
                                            v-for="item in lowStockItems"
                                            :key="item.id_barang"
                                            class="flex items-center justify-between gap-3 px-4 py-2.5 transition hover:bg-slate-50/80 dark:hover:bg-slate-800/60"
                                        >
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-1.5">
                                                    <p class="truncate text-xs font-semibold text-slate-900 dark:text-slate-100">
                                                        {{ item.nama }}
                                                    </p>
                                                    <span
                                                        v-if="item.status === 'kritis'"
                                                        class="shrink-0 rounded-full bg-red-100 px-1.5 py-0.2 text-[9px] font-bold text-red-700 dark:bg-red-950/60 dark:text-red-300"
                                                    >
                                                        Kritis
                                                    </span>
                                                    <span
                                                        v-else-if="item.status === 'waspada'"
                                                        class="shrink-0 rounded-full bg-amber-100 px-1.5 py-0.2 text-[9px] font-bold text-amber-700 dark:bg-amber-950/60 dark:text-amber-300"
                                                    >
                                                        Waspada
                                                    </span>
                                                </div>
                                                <p class="truncate text-[10px] text-slate-500 dark:text-slate-400">
                                                    {{ item.kategori?.nama ?? 'Umum' }}
                                                    <span v-if="item.estimasi_hari_habis !== null && item.estimasi_hari_habis <= 5">
                                                        · Habis ~{{ item.estimasi_hari_habis }} hr lagi
                                                    </span>
                                                    <span v-else-if="Number(item.stok) <= 0">
                                                        · Habis sekarang!
                                                    </span>
                                                </p>
                                            </div>
                                            <div class="shrink-0 text-right flex items-center gap-2">
                                                <span
                                                    :class="[
                                                        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold',
                                                        Number(item.stok) <= 0
                                                            ? 'bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-300'
                                                            : 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
                                                    ]"
                                                >
                                                    {{
                                                        Number(item.stok) <= 0
                                                            ? 'Habis (0)'
                                                            : `Sisa ${item.stok} ${item.satuan}`
                                                    }}
                                                </span>
                                                <Link
                                                    v-if="pos.can('pembelian')"
                                                    :href="`/pembelian?tambah=1&id_barang=${item.id_barang}&jumlah=${item.rekomendasi_restock || 10}&id_supplier=${item.supplier?.id_supplier || ''}`"
                                                    class="text-[10px] font-bold text-orange-600 hover:text-orange-700 dark:text-orange-400 hover:underline"
                                                    title="Beli stok ke supplier"
                                                    @click="showNotification = false"
                                                >
                                                    + Restock
                                                </Link>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer Actions -->
                                <div
                                    class="flex items-center justify-between gap-1.5 border-t border-slate-100 bg-slate-50 px-3 py-2.5 dark:border-slate-800 dark:bg-slate-800/60"
                                >
                                    <Link
                                        v-if="pos.can('penjualan')"
                                        href="/penjualan"
                                        class="flex-1 rounded-xl border border-slate-200 bg-white py-1.5 text-center text-xs font-medium text-slate-700 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                        @click="showNotification = false"
                                    >
                                        Riwayat
                                    </Link>
                                    <Link
                                        v-if="pos.can('stok')"
                                        href="/stok?tab=prediksi"
                                        class="flex-1 rounded-xl bg-gradient-to-r from-amber-600 to-orange-600 py-1.5 text-center text-xs font-bold text-white shadow-xs transition hover:opacity-95"
                                        @click="showNotification = false"
                                    >
                                        ⚡ Smart Restock
                                    </Link>
                                    <Link
                                        v-if="pos.can('stok')"
                                        href="/stok"
                                        class="flex-1 rounded-xl bg-[#0f2a5c] py-1.5 text-center text-xs font-medium text-white shadow-xs transition hover:bg-[#0c2149] dark:bg-blue-600 dark:hover:bg-blue-700"
                                        @click="showNotification = false"
                                    >
                                        Kelola Stok
                                    </Link>
                                </div>
                            </div>
                        </Transition>
                    </div>

                    <button
                        type="button"
                        title="Keluar"
                        class="rounded-xl p-2 text-rose-200 hover:bg-rose-500/20 hover:text-white transition cursor-pointer active-press"
                        @click="logout"
                    >
                        <LogOut class="h-5 w-5" />
                    </button>
                </div>
            </header>

            <main class="relative z-10 w-full min-w-0 px-3 sm:px-6 pt-4 pb-24 md:pb-6 animate-fade-in">
                <slot />
            </main>
        </div>
        <BottomNav />
        <Toaster />
    </div>
</template>
