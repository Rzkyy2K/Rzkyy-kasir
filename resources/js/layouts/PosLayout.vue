<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BarChart3,
    Boxes,
    ChevronsUpDown,
    KeyRound,
    LayoutDashboard,
    LogOut,
    Menu,
    ReceiptText,
    Settings,
    ShoppingBag,
    ShoppingCart,
    Store,
    Tags,
    Truck,
    Users,
    X,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { Toaster } from '@/components/ui/sonner';
import BottomNav from '@/components/pos/BottomNav.vue';
import { getInitials } from '@/composables/useInitials';
import { usePosStore } from '@/stores/pos';

const pos = usePosStore();
const sidebarOpen = ref(false);
const showProfile = ref(false);
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

onMounted(() => {
    void pos.init();
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
