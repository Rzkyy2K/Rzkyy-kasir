<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Boxes,
    LayoutDashboard,
    LayoutGrid,
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
import { computed, ref } from 'vue';
import { usePosStore } from '@/stores/pos';

const pos = usePosStore();
const lainnyaOpen = ref(false);

const menus = [
    { key: 'dashboard', label: 'Home', href: '/dashboard', icon: LayoutDashboard },
    { key: 'kasir', label: 'Kasir', href: '/kasir', icon: ShoppingCart },
    { key: 'produk', label: 'Produk', href: '/produk', icon: ShoppingBag },
    { key: 'stok', label: 'Stok', href: '/stok', icon: Boxes },
    { key: 'kategori', label: 'Kategori', href: '/kategori', icon: Tags },
    { key: 'pembelian', label: 'Pembelian', href: '/pembelian', icon: Truck },
    { key: 'penjualan', label: 'Penjualan', href: '/penjualan', icon: ReceiptText },
    { key: 'supplier', label: 'Supplier', href: '/supplier', icon: Store },
    { key: 'pelanggan', label: 'Pelanggan', href: '/pelanggan', icon: Users },
    { key: 'laporan', label: 'Laporan', href: '/laporan', icon: BarChart3 },
    { key: 'users', label: 'User', fullLabel: 'Manajemen User', href: '/users', icon: Users },
    { key: 'pengaturan', label: 'Pengaturan', href: '/pengaturan', icon: Settings },
];

const role = computed(() => (pos.effectiveRole || '').toLowerCase());

const bottomKeys = computed<string[]>(() => {
    if (role.value === 'kasir') return ['dashboard', 'kasir', 'penjualan'];
    if (role.value === 'admin') return ['dashboard', 'stok', 'pembelian', 'supplier'];
    return ['dashboard', 'users', 'pengaturan', 'laporan'];
});

const visibleMenus = computed(() => menus.filter((m) => pos.can(m.key)));

const bottomItems = computed(() =>
    bottomKeys.value
        .map((k) => menus.find((m) => m.key === k))
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
        class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 backdrop-blur lg:hidden"
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
                class="flex flex-col items-center gap-1 px-1 pt-2 pb-2"
            >
                <span
                    :class="[
                        'flex h-7 items-center justify-center rounded-full px-5 transition',
                        isActive(m.href)
                            ? 'bg-[#0f2a5c] text-white'
                            : 'text-slate-500',
                    ]"
                >
                    <component :is="m.icon" class="h-5 w-5 shrink-0" />
                </span>
                <span
                    :class="[
                        'max-w-full truncate text-[10px] leading-tight',
                        isActive(m.href)
                            ? 'font-bold text-[#0f2a5c]'
                            : 'font-medium text-slate-500',
                    ]"
                >
                    {{ m.label }}
                </span>
            </Link>
            <button
                v-if="hasLainnya"
                type="button"
                class="flex flex-col items-center gap-1 px-1 pt-2 pb-2"
                @click="lainnyaOpen = true"
            >
                <span
                    :class="[
                        'flex h-7 items-center justify-center rounded-full px-5 transition',
                        lainnyaActive || lainnyaOpen
                            ? 'bg-[#0f2a5c] text-white'
                            : 'text-slate-500',
                    ]"
                >
                    <LayoutGrid class="h-5 w-5 shrink-0" />
                </span>
                <span
                    :class="[
                        'text-[10px] leading-tight',
                        lainnyaActive || lainnyaOpen
                            ? 'font-bold text-[#0f2a5c]'
                            : 'font-medium text-slate-500',
                    ]"
                >
                    Lainnya
                </span>
            </button>
        </div>
    </nav>

    <Teleport to="body">
        <div v-if="lainnyaOpen" class="fixed inset-0 z-50 lg:hidden">
            <div class="absolute inset-0 bg-slate-900/50" @click="lainnyaOpen = false" />
            <div
                class="absolute inset-x-0 bottom-0 max-h-[80vh] overflow-y-auto rounded-t-3xl bg-white p-4"
                style="padding-bottom: calc(1rem + env(safe-area-inset-bottom))"
            >
                <div class="mb-3 flex items-center justify-between px-1">
                    <p class="text-sm font-bold text-slate-900">Menu Lainnya</p>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100"
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
                            'flex flex-col items-center gap-1.5 rounded-2xl border p-3 text-center transition',
                            isActive(m.href)
                                ? 'border-[#0f2a5c] bg-blue-50 text-[#0f2a5c]'
                                : 'border-slate-200 text-slate-600 hover:border-blue-300 hover:bg-slate-50',
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
        </div>
    </Teleport>
</template>
