<script setup lang="ts">
defineProps<{ rows?: number }>();
</script>

<template>
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
        <div
            v-for="i in rows ?? 8"
            :key="i"
            class="animate-pulse rounded-2xl border border-slate-200 bg-white p-4"
        >
            <div class="h-20 rounded-xl bg-slate-100" />
            <div class="mt-2 h-3 w-3/4 rounded bg-slate-100" />
            <div class="mt-2 h-3 w-1/2 rounded bg-slate-100" />
        </div>
    </div>
</template>
