<script setup lang="ts">
import { PackageSearch } from '@lucide/vue';

defineProps<{ title?: string; message?: string }>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-6 text-center"
    >
        <PackageSearch class="h-8 w-8 text-slate-300" />
        <p class="font-semibold text-slate-700">
            {{ title ?? 'Belum ada data' }}
        </p>
        <p class="max-w-sm text-sm text-slate-400">
            {{ message ?? 'Data akan muncul di sini setelah tersedia.' }}
        </p>
        <slot />
    </div>
</template>
