<script setup lang="ts">
import { Search } from '@lucide/vue';

defineProps<{ modelValue: string; placeholder?: string }>();
defineEmits<{ (e: 'update:modelValue', v: string): void }>();
</script>

<template>
    <div class="relative">
        <Search
            class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
        />
        <input
            :value="modelValue"
            type="search"
            :placeholder="placeholder ?? 'Cari barang, barcode…'"
            class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pr-3 pl-9 text-sm text-slate-800 placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:ring-blue-900/40 transition-colors"
            @input="
                $emit(
                    'update:modelValue',
                    ($event.target as HTMLInputElement).value,
                )
            "
        />
    </div>
</template>
