<script setup lang="ts">
import { Plus } from '@lucide/vue';
import { rupiah } from '@/lib/format';
import type { Barang } from '@/types/pos';

defineProps<{ item: Barang }>();
defineEmits<{ (e: 'add', item: Barang): void }>();

function inisial(nama: string) {
    return nama
        .split(' ')
        .map((w) => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}
</script>

<template>
    <div
        class="flex flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-md"
    >
        <div
            class="flex h-20 items-center justify-center rounded-xl bg-gradient-to-br from-blue-50 to-slate-100 text-lg font-bold text-blue-800"
        >
            {{ inisial(item.nama) }}
        </div>
        <p
            class="mt-2 truncate text-sm font-semibold text-slate-800"
            :title="item.nama"
        >
            {{ item.nama }}
        </p>
        <p class="text-xs text-slate-400">
            {{ item.kategori?.nama ?? '—' }} · {{ item.satuan }} · Stok
            {{ item.stok }}
        </p>
        <div class="mt-1 flex items-center justify-between gap-2">
            <p class="text-sm font-bold text-blue-800">
                {{ rupiah(item.harga_jual) }}
            </p>
            <button
                type="button"
                :disabled="item.stok <= 0"
                class="inline-flex items-center gap-1 rounded-lg bg-blue-700 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-slate-300"
                @click="$emit('add', item)"
            >
                <Plus class="h-3.5 w-3.5" /> Tambah
            </button>
        </div>
        <p
            v-if="item.stok <= 0"
            class="mt-1 text-[11px] font-medium text-red-500"
        >
            Stok habis
        </p>
    </div>
</template>
