<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Boxes,
    LayoutDashboard,
    LayoutGrid,
    ReceiptText,
    School,
    Settings,
    ShoppingBag,
    ShoppingCart,
    Store,
    Tags,
    Truck,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { usePosStore } from '@/stores/pos';

const pos = usePosStore();
const lainnyaOpen = ref(false);

const menus = [
    { key: 'dashboard', label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
    { key: 'kasir', label: 'Kasir', href: '/kasir', icon: ShoppingCart },
    { key: 'produk', label: 'Produk', href: '/produk', icon: ShoppingBag },
    { key: 'stok', label: 'Stok', href: '/stok', icon: Boxes },
    { key: 'kategori', label: 'Kategori', href: '/kategori', icon: Tags },
    { key: 'pembelian', label: 'Pembelian', href: '/pembelian', icon: Truck },
    { key: 'penjualan', label: 'Penjualan', href: '/penjualan', icon: ReceiptText },
    { key: 'supplier', label: 'Supplier', href: '/supplier', icon: Store },
    { key: 'pelanggan', label: 'Pelanggan', href: '/pelanggan', icon: Users },
    { key: 'laporan', label: 'Laporan', href: '/laporan', icon: BarChart3 },
    { key: 'users', label: 'Pengguna', fullLabel: 'Manajemen Pengguna', href: '/users', icon: Users },
    { key: 'pengaturan', label: 'Pengaturan', href: '/pengaturan', icon: Settings },
];

const computedMenus = computed(() =>
    menus.map((m) => {
        if (m.key === 'users' && pos.isDev) {
            return {
                ...m,
                label: 'Sekolah',
                fullLabel: 'Manajemen Sekolah',
                icon: School,
            };
        }
        return m;
    }),
);

const role = computed(() => (pos.effectiveRole || '').toLowerCase());

const bottomKeys = computed<string[]>(() => {
    if (pos.isDev) return ['dashboard', 'users', 'pengaturan'];
    if (role.value === 'kasir') return ['dashboard', 'kasir', 'penjualan'];
    if (role.value === 'admin') return ['dashboard', 'stok', 'pembelian', 'supplier'];
    return ['dashboard', 'users', 'pengaturan', 'laporan'];
});

const visibleMenus = computed(() => computedMenus.value.filter((m) => pos.can(m.key)));

const bottomItems = computed(() =>
    bottomKeys.value
        .map((k) => computedMenus.value.find((m) => m.key === k))
        .filter((m): m is (typeof menus)[number] => !!m && pos.can(m.key)),
);

const lainnyaItems = computed(() =>
    visibleMenus.value.filter((m) => !bottomKeys.value.includes(m.key)),
);

const hasLainnya = computed(() => lainnyaItems.value.length > 0);
const totalCols = computed(() => bottomItems.value.length + (hasLainnya.value ? 1 : 0));

const currentPath = computed(() => usePage().url.split('?')[0].split('#')[0]);

function isActive(href: string) {
    if (href === '/dashboard') return currentPath.value === '/dashboard' || currentPath.value.startsWith('/dashboard/');
    return currentPath.value === href || currentPath.value.startsWith(`${href}/`);
}

const lainnyaActive = computed(() => lainnyaItems.value.some((m) => isActive(m.href)));
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-gradient-to-r from-[#0c2356] via-[#143f91] to-[#1e58c8] text-white shadow-2xl backdrop-blur-xl transition-colors duration-200 md:hidden dark:from-[#061226] dark:via-[#091c3d] dark:to-[#0d2757] dark:border-white/10"
        style="padding-bottom: env(safe-area-inset-bottom)"
    >
        <div
            class="grid"
            :style="{ gridTemplateColumns: `repeat(${totalCols}, minmax(0, 1fr))` }"
        >
            <Link
                v-for="m in bottomItems"
                :key="m.key"
                :href="m.href"
                :title="m.fullLabel ?? m.label"
                class="flex flex-col items-center gap-1 px-1 pt-2 pb-2 active-press cursor-pointer"
            >
                <span
                    :class="[
                        'flex h-7 items-center justify-center rounded-full px-5 transition',
                        isActive(m.href)
                            ? 'bg-white text-[#0c2356] font-bold shadow-md'
                            : 'text-blue-200 hover:text-white',
                    ]"
                >
                    <component :is="m.icon" class="h-5 w-5 shrink-0" />
                </span>
                <span
                    :class="[
                        'max-w-full truncate text-[10px] leading-tight',
                        isActive(m.href)
                            ? 'font-bold text-white'
                            : 'font-medium text-blue-200/90',
                    ]"
                >
                    {{ m.label }}
                </span>
            </Link>
            <button
                v-if="hasLainnya"
                type="button"
                class="flex flex-col items-center gap-1 px-1 pt-2 pb-2 active-press cursor-pointer"
                @click="lainnyaOpen = true"
            >
                <span
                    :class="[
                        'flex h-7 items-center justify-center rounded-full px-5 transition',
                        lainnyaActive || lainnyaOpen
                            ? 'bg-white text-[#0c2356] font-bold shadow-md'
                            : 'text-blue-200 hover:text-white',
                    ]"
                >
                    <LayoutGrid class="h-5 w-5 shrink-0" />
                </span>
                <span
                    :class="[
                        'text-[10px] leading-tight',
                        lainnyaActive || lainnyaOpen
                            ? 'font-bold text-white'
                            : 'font-medium text-blue-200/90',
                    ]"
                >
                    Lainnya
                </span>
            </button>
        </div>
    </nav>

    <Teleport to="body">
        <div
            class="fixed inset-0 z-50 lg:hidden pointer-events-none"
            :class="{ 'pointer-events-auto': lainnyaOpen }"
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
                    v-if="lainnyaOpen"
                    class="absolute inset-0 backdrop-active cursor-pointer pointer-events-auto"
                    @click="lainnyaOpen = false"
                />
            </Transition>

            <!-- Bottom Sheet -->
            <Transition
                enter-active-class="sheet-slide-enter"
                enter-from-class="translate-y-full"
                enter-to-class="translate-y-0"
                leave-active-class="sheet-slide-leave"
                leave-from-class="translate-y-0"
                leave-to-class="translate-y-full"
            >
                <div
                    v-if="lainnyaOpen"
                    class="absolute inset-x-0 bottom-0 max-h-[80vh] overflow-y-auto rounded-t-3xl border-t border-white/15 bg-gradient-to-b from-[#0c2356] to-[#143f91] p-4 text-white shadow-2xl dark:border-white/10 dark:from-[#061226] dark:to-[#0d2757] pointer-events-auto"
                    style="padding-bottom: calc(1rem + env(safe-area-inset-bottom))"
                >
                    <!-- Drag handle bar -->
                    <div class="mx-auto mb-3 h-1.5 w-12 rounded-full bg-white/30" />

                    <div class="mb-3 flex items-center justify-between px-1">
                        <p class="text-sm font-bold text-white">Menu Lainnya</p>
                        <button
                            type="button"
                            class="rounded-lg p-1.5 text-blue-200 hover:bg-white/10 active-press hover:text-white cursor-pointer"
                            @click="lainnyaOpen = false"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    <div class="grid grid-cols-4 gap-2">
                        <Link
                            v-for="m in lainnyaItems"
                            :key="m.key"
                            :href="m.href"
                            :title="m.fullLabel ?? m.label"
                            :class="[
                                'flex flex-col items-center gap-1.5 rounded-2xl border p-3 text-center transition active-press',
                                isActive(m.href)
                                    ? 'border-white bg-white text-[#0c2356] font-bold shadow-md'
                                    : 'border-white/15 bg-white/5 text-blue-100 hover:bg-white/15 hover:text-white',
                            ]"
                            @click="lainnyaOpen = false"
                        >
                            <component :is="m.icon" class="h-5 w-5 shrink-0" />
                            <span class="w-full truncate text-[11px] font-semibold leading-tight">
                                {{ m.fullLabel ?? m.label }}
                            </span>
                        </Link>
                    </div>
                </div>
            </Transition>
        </div>
    </Teleport>
</template>
