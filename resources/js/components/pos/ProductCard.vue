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

const cardGradient = computed(() => {
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
        class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border bg-white p-3.5 shadow-sm transition-all duration-200 select-none dark:bg-slate-900"
        :class="[
            isOutOfStock
                ? 'border-dashed border-rose-200 bg-slate-50/70 opacity-65 cursor-not-allowed dark:border-rose-900/50 dark:bg-slate-800/40'
                : isLowStock
                  ? 'border-amber-300 hover:border-amber-400 hover:shadow-md cursor-pointer active-press dark:border-amber-600/60'
                  : 'border-slate-200 hover:border-blue-400 hover:shadow-md cursor-pointer active-press dark:border-slate-800 dark:hover:border-blue-500',
        ]"
        @click="handleAdd"
    >
        <!-- Bagian Atas: Gambar/Inisial Visual & Badge -->
        <div class="relative">
            <div
                class="flex h-24 w-full items-center justify-center rounded-xl bg-gradient-to-br shadow-inner transition-transform duration-200 group-hover:scale-[1.02]"
                :class="cardGradient"
            >
                <span class="text-2xl font-black tracking-wider drop-shadow-sm">
                    {{ inisial(item.nama) }}
                </span>
            </div>

            <!-- In-Cart Badge (Kiri Atas) -->
            <div
                v-if="cartQty > 0"
                :key="cartQty"
                class="absolute -top-1.5 -left-1.5 flex items-center gap-1 rounded-full bg-blue-600 px-2.5 py-0.5 text-[11px] font-bold text-white shadow-md ring-2 ring-white dark:ring-slate-900 animate-pop-badge"
            >
                <Check class="h-3 w-3 stroke-[3]" />
                <span>{{ cartQty }}</span>
            </div>

            <!-- Stock Status Badge (Kanan Atas) -->
            <div class="absolute -top-1.5 -right-1.5">
                <!-- Stok Habis -->
                <span
                    v-if="isOutOfStock"
                    class="inline-flex items-center gap-1 rounded-full bg-rose-600 px-2.5 py-0.5 text-[10px] font-extrabold tracking-wide text-white uppercase shadow-md ring-2 ring-white dark:ring-slate-900"
                >
                    <PackageX class="h-3 w-3" />
                    Habis
                </span>

                <!-- Stok Menipis (< 5) -->
                <span
                    v-else-if="isLowStock"
                    class="inline-flex items-center gap-1 rounded-full bg-amber-500 px-2.5 py-0.5 text-[11px] font-bold text-white shadow-md ring-2 ring-white dark:ring-slate-900 animate-pulse"
                >
                    <AlertTriangle class="h-3 w-3" />
                    Sisa {{ item.stok }}
                </span>

                <!-- Stok Aman -->
                <span
                    v-else
                    class="inline-flex items-center rounded-full bg-emerald-600/90 px-2 py-0.5 text-[10px] font-medium text-white shadow-xs backdrop-blur-xs ring-1 ring-white/50 dark:ring-slate-800"
                >
                    Stok {{ item.stok }}
                </span>
            </div>
        </div>

        <!-- Bagian Tengah: Detail Produk -->
        <div class="mt-2.5 flex-1">
            <div class="flex items-center gap-1.5 text-[11px] text-slate-400 dark:text-slate-500">
                <span class="truncate">{{ item.kategori?.nama ?? 'Kantin / Umum' }}</span>
                <span>·</span>
                <span class="shrink-0 font-medium text-slate-500 dark:text-slate-400">{{ item.satuan }}</span>
            </div>
            <h4
                class="mt-1 line-clamp-2 text-sm font-semibold leading-snug text-slate-800 transition group-hover:text-blue-700 dark:text-slate-100 dark:group-hover:text-blue-400"
                :title="item.nama"
            >
                {{ item.nama }}
            </h4>
        </div>

        <!-- Bagian Bawah: Harga & Tombol Tambah -->
        <div class="mt-3 flex items-center justify-between gap-2 border-t border-slate-100 pt-2.5 dark:border-slate-800">
            <div class="min-w-0">
                <p class="text-xs text-slate-400 dark:text-slate-500">Harga</p>
                <p class="truncate text-sm font-bold tabular-nums text-blue-800 sm:text-base dark:text-blue-400">
                    {{ rupiah(item.harga_jual) }}
                </p>
            </div>

            <button
                type="button"
                :disabled="isOutOfStock || isMaxInCart"
                class="inline-flex shrink-0 items-center justify-center gap-1 rounded-xl px-3 py-1.5 text-xs font-bold transition shadow-xs"
                :class="[
                    isOutOfStock
                        ? 'cursor-not-allowed bg-slate-200 text-slate-400 shadow-none dark:bg-slate-800 dark:text-slate-600'
                        : isMaxInCart
                          ? 'cursor-not-allowed bg-amber-100 text-amber-800 shadow-none dark:bg-amber-950/50 dark:text-amber-300'
                          : 'cursor-pointer bg-blue-600 text-white hover:bg-blue-700 active:scale-95 shadow-blue-500/20 dark:bg-blue-600 dark:hover:bg-blue-500',
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
                    <span>Tambah</span>
                </template>
            </button>
        </div>
    </div>
</template>
