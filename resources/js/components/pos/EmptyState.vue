<script setup lang="ts">
import { PackageSearch } from '@lucide/vue';

defineProps<{ title?: string; message?: string }>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-6 text-center transition-colors dark:border-slate-800 dark:bg-slate-900/60 animate-fade-in"
    >
        <PackageSearch class="h-8 w-8 text-slate-300 dark:text-slate-600" />
        <p class="font-semibold text-slate-700 dark:text-slate-200">
            {{ title ?? 'Belum ada data' }}
        </p>
        <p class="max-w-sm text-sm text-slate-400 dark:text-slate-500">
            {{ message ?? 'Data akan muncul di sini setelah tersedia.' }}
        </p>
        <slot />
    </div>
</template>
