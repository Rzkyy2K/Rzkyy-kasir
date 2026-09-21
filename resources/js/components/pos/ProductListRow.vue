<script setup lang="ts">
import {
    AlertTriangle,
    Check,
    PackageX,
    Plus,
} from '@lucide/vue';
import { computed } from 'vue';
import { rupiah } from '@/lib/format';
import type { Barang } from '@/types/pos';

const props = withDefaults(
    defineProps<{
        item: Barang;
        cartQty?: number;
    }>(),
    {
        cartQty: 0,
    },
);

const emit = defineEmits<{ (e: 'add', item: Barang): void }>();

const isOutOfStock = computed(() => Number(props.item.stok) <= 0);
const isLowStock = computed(
    () => Number(props.item.stok) > 0 && Number(props.item.stok) <= 5,
);
const isMaxInCart = computed(
    () => props.cartQty >= Number(props.item.stok) && !isOutOfStock.value,
);

const gradientPalettes = [
    'from-blue-500 to-indigo-600 text-white',
    'from-emerald-500 to-teal-600 text-white',
    'from-amber-500 to-orange-500 text-white',
    'from-purple-500 to-violet-600 text-white',
    'from-rose-500 to-pink-600 text-white',
    'from-cyan-500 to-sky-600 text-white',
];

const rowGradient = computed(() => {
    if (isOutOfStock.value) {
        return 'from-slate-200 to-slate-300 text-slate-500';
    }
    const idx = (props.item.id_barang ?? 0) % gradientPalettes.length;
    return gradientPalettes[idx];
});

function inisial(nama: string) {
    return nama
        .split(' ')
        .filter(Boolean)
        .map((w) => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function handleAdd() {
    if (isOutOfStock.value || isMaxInCart.value) return;
    emit('add', props.item);
}
</script>

<template>
    <div
        class="group flex items-center justify-between gap-3 rounded-xl border bg-white p-3 shadow-xs transition-all duration-150 select-none dark:bg-slate-900"
        :class="[
            isOutOfStock
                ? 'border-dashed border-rose-200 bg-slate-50/70 opacity-60 cursor-not-allowed dark:border-rose-900/50 dark:bg-slate-800/40'
                : isLowStock
                  ? 'border-amber-300 bg-amber-50/40 hover:border-amber-500 hover:bg-amber-100 hover:shadow-md cursor-pointer dark:border-amber-700/60 dark:bg-amber-950/20 dark:hover:border-amber-500 dark:hover:bg-amber-950/50'
                  : 'border-slate-200 hover:border-blue-500 hover:bg-blue-100 hover:shadow-md cursor-pointer dark:border-slate-800 dark:hover:border-blue-500 dark:hover:bg-blue-950/50',
        ]"
        @click="handleAdd"
    >
        <!-- Kiri: Mini Visual Avatar & Info Barang -->
        <div class="flex min-w-0 items-center gap-3">
            <div
                class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br shadow-inner transition-transform duration-200 group-hover:scale-105"
                :class="rowGradient"
            >
                <span class="text-sm font-bold">{{ inisial(item.nama) }}</span>
                <!-- In-Cart mini badge -->
                <span
                    v-if="cartQty > 0"
                    class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-blue-600 text-[10px] font-bold text-white shadow-xs ring-2 ring-white dark:ring-slate-900"
                >
                    {{ cartQty }}
                </span>
            </div>

            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <h4
                        class="truncate text-sm font-bold text-slate-800 transition-colors group-hover:text-blue-900 dark:text-slate-100 dark:group-hover:text-blue-400"
                        :title="item.nama"
                    >
                        {{ item.nama }}
                    </h4>
                </div>
                <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-slate-500 transition-colors group-hover:text-slate-700 dark:text-slate-400 dark:group-hover:text-slate-300">
                    <span class="truncate">{{ item.kategori?.nama ?? 'Kantin / Umum' }}</span>
                    <span>·</span>
                    <span class="font-medium">{{ item.satuan }}</span>
                    <template v-if="item.barcode">
                        <span>·</span>
                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-mono font-medium text-slate-700 transition-colors group-hover:bg-white group-hover:border group-hover:border-blue-200 group-hover:shadow-2xs dark:bg-slate-800 dark:text-slate-300 dark:group-hover:bg-slate-700">
                            {{ item.barcode }}
                        </span>
                    </template>
                </div>
            </div>
        </div>

        <!-- Kanan: Status Stok, Harga, & Tombol Tambah -->
        <div class="flex shrink-0 items-center gap-3 sm:gap-4">
            <!-- Badge Stok -->
            <div class="hidden text-right sm:block">
                <!-- Habis -->
                <span
                    v-if="isOutOfStock"
                    class="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-[11px] font-bold text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/60 dark:text-rose-300"
                >
                    <PackageX class="h-3 w-3" />
                    Habis
                </span>
                <!-- Menipis -->
                <span
                    v-else-if="isLowStock"
                    class="inline-flex items-center gap-1 rounded-full border border-amber-300 bg-amber-50 px-2.5 py-0.5 text-[11px] font-bold text-amber-700 dark:border-amber-700/60 dark:bg-amber-950/60 dark:text-amber-300 animate-pulse"
                >
                    <AlertTriangle class="h-3 w-3" />
                    Sisa {{ item.stok }}
                </span>
                <!-- Aman -->
                <span
                    v-else
                    class="inline-flex items-center rounded-full border border-emerald-300 bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-800 transition-colors group-hover:bg-emerald-100 group-hover:border-emerald-400 dark:border-emerald-800/60 dark:bg-emerald-950/60 dark:text-emerald-300"
                >
                    Stok {{ item.stok }}
                </span>
            </div>

            <!-- Harga Jual -->
            <div class="text-right">
                <p class="text-sm font-black text-blue-800 transition-colors group-hover:text-blue-950 sm:text-base dark:text-blue-400 dark:group-hover:text-blue-300">
                    {{ rupiah(item.harga_jual) }}
                </p>
                <!-- Mobile stock badge -->
                <p
                    v-if="isOutOfStock"
                    class="text-[10px] font-bold text-rose-600 sm:hidden dark:text-rose-400"
                >
                    Habis
                </p>
                <p
                    v-else-if="isLowStock"
                    class="text-[10px] font-bold text-amber-600 sm:hidden dark:text-amber-400"
                >
                    Sisa {{ item.stok }}
                </p>
            </div>

            <!-- Tombol Tambah -->
            <button
                type="button"
                :disabled="isOutOfStock || isMaxInCart"
                class="inline-flex items-center justify-center gap-1 rounded-xl px-3 py-1.5 text-xs font-bold transition shadow-xs"
                :class="[
                    isOutOfStock
                        ? 'cursor-not-allowed bg-slate-200 text-slate-400 dark:bg-slate-800 dark:text-slate-600'
                        : isMaxInCart
                          ? 'cursor-not-allowed bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300'
                          : 'cursor-pointer bg-blue-600 text-white hover:bg-blue-700 group-hover:bg-blue-700 group-hover:shadow-md active:scale-95 shadow-blue-500/20 dark:bg-blue-600 dark:hover:bg-blue-500',
                ]"
                @click.stop="handleAdd"
            >
                <template v-if="isOutOfStock">
                    Habis
                </template>
                <template v-else-if="isMaxInCart">
                    Maksimal
                </template>
                <template v-else>
                    <Plus class="h-3.5 w-3.5 stroke-[2.5]" />
                    <span class="hidden sm:inline">Tambah</span>
                </template>
            </button>
        </div>
    </div>
</template>
