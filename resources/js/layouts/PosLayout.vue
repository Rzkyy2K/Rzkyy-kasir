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
    KeyRound,
    LayoutDashboard,
    LogOut,
    Menu,
    ReceiptText,
    RefreshCw,
    Settings,
    ShoppingBag,
    ShoppingCart,
    Store,
    Tags,
    Truck,
    Users,
    X,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Toaster } from '@/components/ui/sonner';
import BottomNav from '@/components/pos/BottomNav.vue';
import { getInitials } from '@/composables/useInitials';
import { fetchBarang } from '@/services/barangService';
import { usePosStore } from '@/stores/pos';
import type { Barang } from '@/types/pos';

const pos = usePosStore();
const sidebarOpen = ref(false);
const showProfile = ref(false);
const showNotification = ref(false);
const notifDropdownRef = ref<HTMLElement | null>(null);
const lowStockItems = ref<Barang[]>([]);
const lowStockLoading = ref(false);

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
    { key: 'users', label: 'Manajemen User', href: '/users', icon: Users },
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
        const res = await fetchBarang({
            id_sekolah: pos.idSekolah,
            stok_rendah: 10,
            is_active: true,
            per_page: 50,
        });
        lowStockItems.value = res.data ?? [];
    } catch {
        // silent fail in global layout
    } finally {
        lowStockLoading.value = false;
    }
}

function handleStockChanged() {
    void loadLowStockAlert();
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

watch(
    () => pos.idSekolah,
    (val) => {
        if (val) void loadLowStockAlert();
    },
);

onMounted(() => {
    void pos.init().then(() => {
        void loadLowStockAlert();
    });
    window.addEventListener('click', handleClickOutside);
    window.addEventListener('pos:stock-changed', handleStockChanged);
});

onUnmounted(() => {
    window.removeEventListener('click', handleClickOutside);
    window.removeEventListener('pos:stock-changed', handleStockChanged);
});
</script>

<template>
    <div class="min-h-screen bg-slate-100 text-slate-900">
        <!-- Sidebar desktop -->
        <aside
            class="fixed inset-y-0 left-0 z-30 hidden w-60 flex-col bg-[#0f2a5c] text-white lg:flex"
        >
            <div class="flex items-center gap-2 px-5 pt-6 pb-4">
                <img
                    src="/logoEduMart.jpeg"
                    alt="Logo EduMart"
                    class="h-10 w-10 rounded-xl bg-white object-contain p-1"
                />
                <div>
                    <p class="text-base font-bold">EduMart</p>
                    <p class="text-[11px] text-blue-200">Kasir Alat Sekolah</p>
                </div>
            </div>
            <nav class="flex-1 space-y-1 overflow-y-auto px-3 pb-4">
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
                            'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                            currentUrl.startsWith(m.href)
                                ? 'bg-white text-[#0f2a5c] shadow'
                                : 'text-blue-100 hover:bg-white/10',
                        ]"
                    >
                        <component :is="m.icon" class="h-4.5 w-4.5 shrink-0" />
                        {{ m.label }}
                    </Link>
                </template>
            </nav>
            <div class="border-t border-white/10 p-3">
                <div class="relative">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-xl px-2 py-2 text-left hover:bg-white/10"
                        @click="showProfile = !showProfile"
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/20 text-sm font-bold text-white"
                        >
                            {{ profileInitials || 'U' }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-semibold text-white"
                            >
                                {{ pos.me?.nama_lengkap ?? 'Memuat…' }}
                            </p>
                            <p class="truncate text-[11px] text-blue-200">
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
                    <div
                        v-if="showProfile"
                        class="absolute right-0 bottom-full left-0 mb-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm text-red-600 hover:bg-red-50"
                            @click="
                                showProfile = false;
                                logout();
                            "
                        >
                            <LogOut class="h-4 w-4" /> Logout
                        </button>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Drawer mobile -->
        <Teleport to="body">
            <div v-if="sidebarOpen" class="fixed inset-0 z-50 lg:hidden">
                <div
                    class="absolute inset-0 bg-slate-900/50"
                    @click="sidebarOpen = false"
                />
                <aside
                    class="absolute inset-y-0 left-0 flex w-72 flex-col bg-[#0f2a5c] text-white shadow-xl"
                >
                    <div
                        class="flex items-center justify-between px-5 pt-6 pb-4"
                    >
                        <div class="flex items-center gap-2">
                            <img
                                src="/logoEduMart.jpeg"
                                alt="Logo EduMart"
                                class="h-10 w-10 rounded-xl bg-white object-contain p-1"
                            />
                            <div>
                                <p class="text-base font-bold">EduMart</p>
                                <p class="text-[11px] text-blue-200">
                                    Kasir Alat Sekolah
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg p-1.5 hover:bg-white/10"
                            @click="sidebarOpen = false"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    <nav class="flex-1 space-y-1 overflow-y-auto px-3 pb-4">
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
                                    'flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium',
                                    currentUrl.startsWith(m.href)
                                        ? 'bg-white text-[#0f2a5c]'
                                        : 'text-blue-100 hover:bg-white/10',
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
                    <div class="border-t border-white/10 p-3">
                        <div class="relative">
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-xl px-2 py-2 text-left hover:bg-white/10"
                                @click="showProfile = !showProfile"
                            >
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/20 text-sm font-bold text-white"
                                >
                                    {{ profileInitials || 'U' }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="truncate text-sm font-semibold text-white"
                                    >
                                        {{ pos.me?.nama_lengkap ?? 'Memuat…' }}
                                    </p>
                                    <p
                                        class="truncate text-[11px] text-blue-200"
                                    >
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
                            <div
                                v-if="showProfile"
                                class="absolute right-0 bottom-full left-0 mb-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm text-red-600 hover:bg-red-50"
                                    @click="
                                        showProfile = false;
                                        logout();
                                    "
                                >
                                    <LogOut class="h-4 w-4" /> Logout
                                </button>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </Teleport>

        <div class="lg:pl-60">
            <!-- Header -->
            <header
                class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur"
            >
                <div class="flex items-center gap-2 px-3 py-2.5 sm:px-6">
                    <button
                        v-if="showBack"
                        type="button"
                        title="Kembali"
                        class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden"
                        @click="goBack"
                    >
                        <ArrowLeft class="h-5 w-5" />
                    </button>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden"
                        @click="sidebarOpen = true"
                    >
                        <Menu class="h-5 w-5" />
                    </button>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-bold text-slate-900">
                            EduMart
                        </p>
                        <p class="truncate text-[11px] text-slate-500">
                            {{ pos.sekolahAktif?.nama_sekolah ?? '…' }} ·
                            {{ pos.me?.nama_lengkap ?? '…' }} ({{
                                pos.ownRole
                            }})
                        </p>
                    </div>
                    <!-- Developer: pindah sekolah + pindah tampilan peran -->
                    <template v-if="pos.isDev">
                        <select
                            :value="pos.idSekolah"
                            title="Pindah sekolah"
                            class="max-w-36 truncate rounded-lg border border-slate-200 bg-white px-2 py-2 text-xs sm:max-w-52 sm:text-sm"
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
                            >
                                {{ s.nama_sekolah }}
                            </option>
                        </select>
                        <select
                            :value="pos.effectiveRole"
                            title="Tampilan peran"
                            class="max-w-32 truncate rounded-lg border border-slate-200 bg-white px-2 py-2 text-xs sm:max-w-44 sm:text-sm"
                            @change="
                                pos.setCtxRole(
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option value="super admin">Super Admin</option>
                            <option value="admin">Admin</option>
                            <option value="kasir">Kasir</option>
                        </select>
                    </template>
                    <!-- Super admin: pilih tampilan peran (sekolah terkunci) -->
                    <template v-else-if="pos.isSuper">
                        <select
                            :value="pos.effectiveRole"
                            title="Tampilan peran"
                            class="max-w-32 truncate rounded-lg border border-slate-200 bg-white px-2 py-2 text-xs sm:max-w-44 sm:text-sm"
                            @change="
                                pos.setCtxRole(
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option value="super admin">Super Admin</option>
                            <option value="admin">Admin</option>
                            <option value="kasir">Kasir</option>
                        </select>
                    </template>

                    <!-- Notifikasi Stok Menipis Dropdown Popover -->
                    <div ref="notifDropdownRef" class="relative">
                        <button
                            type="button"
                            title="Peringatan Stok Menipis"
                            class="relative rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                            :class="{ 'bg-amber-50 text-amber-700': showNotification }"
                            @click="showNotification = !showNotification"
                        >
                            <Bell class="h-5 w-5" />
                            <span
                                v-if="lowStockItems.length > 0"
                                class="absolute -top-0.5 -right-0.5 flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white shadow-sm ring-2 ring-white animate-pulse"
                            >
                                {{ lowStockItems.length > 99 ? '99+' : lowStockItems.length }}
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
                                class="absolute right-0 top-full mt-2 w-80 sm:w-96 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl z-50"
                            >
                                <!-- Header Popover -->
                                <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/90 px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                                            <AlertTriangle class="h-4 w-4" />
                                        </span>
                                        <div>
                                            <h4 class="text-xs font-bold text-slate-900">
                                                Peringatan Stok Menipis
                                            </h4>
                                            <p class="text-[10px] text-slate-500">
                                                {{ lowStockItems.length }} produk memerlukan restock
                                            </p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        title="Muat ulang stok"
                                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200/70 hover:text-slate-700 transition"
                                        :disabled="lowStockLoading"
                                        @click="loadLowStockAlert"
                                    >
                                        <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': lowStockLoading }" />
                                    </button>
                                </div>

                                <!-- List Items -->
                                <div class="max-h-72 overflow-y-auto divide-y divide-slate-100">
                                    <div v-if="lowStockLoading && lowStockItems.length === 0" class="p-6 text-center">
                                        <RefreshCw class="h-6 w-6 animate-spin text-slate-400 mx-auto mb-2" />
                                        <p class="text-xs text-slate-500">Memeriksa stok produk…</p>
                                    </div>

                                    <div v-else-if="lowStockItems.length === 0" class="p-6 text-center">
                                        <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                                            <CheckCircle class="h-5 w-5" />
                                        </div>
                                        <p class="text-xs font-bold text-slate-800">Semua Stok Aman</p>
                                        <p class="text-[11px] text-slate-500 mt-0.5">
                                            Tidak ada produk di bawah batas minimum (10 pcs).
                                        </p>
                                    </div>

                                    <div
                                        v-for="item in lowStockItems"
                                        :key="item.id_barang"
                                        class="flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-slate-50/80 transition"
                                    >
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-xs font-semibold text-slate-900">
                                                {{ item.nama }}
                                            </p>
                                            <p class="text-[10px] text-slate-500 truncate">
                                                {{ item.kategori?.nama ?? 'Umum' }}
                                                <span v-if="item.barcode"> · {{ item.barcode }}</span>
                                            </p>
                                        </div>
                                        <div class="shrink-0 text-right">
                                            <span
                                                :class="[
                                                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold',
                                                    Number(item.stok) <= 0
                                                        ? 'bg-red-100 text-red-700'
                                                        : 'bg-amber-100 text-amber-700',
                                                ]"
                                            >
                                                {{ Number(item.stok) <= 0 ? 'Habis (0)' : `Sisa ${item.stok} ${item.satuan}` }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer Actions -->
                                <div class="border-t border-slate-100 bg-slate-50 px-3 py-2.5 flex items-center justify-between gap-2">
                                    <Link
                                        v-if="pos.can('stok')"
                                        href="/stok"
                                        class="flex-1 rounded-xl border border-slate-200 bg-white py-1.5 text-center text-xs font-medium text-slate-700 hover:bg-slate-100 transition"
                                        @click="showNotification = false"
                                    >
                                        Kelola Stok
                                    </Link>
                                    <Link
                                        v-if="pos.can('pembelian')"
                                        href="/pembelian"
                                        class="flex-1 rounded-xl bg-[#0f2a5c] py-1.5 text-center text-xs font-medium text-white hover:bg-[#0c2149] transition shadow-sm"
                                        @click="showNotification = false"
                                    >
                                        + Beli Stok
                                    </Link>
                                </div>
                            </div>
                        </Transition>
                    </div>

                    <Link
                        v-if="pos.can('pengaturan')"
                        href="/settings/security"
                        title="Ganti password"
                        class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                    >
                        <KeyRound class="h-5 w-5" />
                    </Link>
                    <button
                        type="button"
                        title="Keluar"
                        class="rounded-lg p-2 text-slate-500 hover:bg-red-50 hover:text-red-600"
                        @click="logout"
                    >
                        <LogOut class="h-5 w-5" />
                    </button>
                </div>
            </header>

            <main class="mx-auto w-full max-w-7xl px-4 pt-4 pb-24 lg:pb-4">
                <slot />
            </main>
        </div>
        <BottomNav />
        <Toaster />
    </div>
</template>
