<script setup lang="ts">
defineProps<{ page: number; lastPage: number; total: number }>();
defineEmits<{ (e: 'change', page: number): void }>();
</script>

<template>
    <div
        v-if="lastPage > 1"
        class="flex items-center justify-between gap-2 pt-4"
    >
        <p class="text-xs text-slate-500">Total {{ total }} data</p>
        <div class="flex items-center gap-1">
            <button
                type="button"
                :disabled="page <= 1"
                class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm disabled:opacity-40"
                @click="$emit('change', page - 1)"
            >
                ‹
            </button>
            <span class="px-2 text-sm text-slate-600"
                >{{ page }} / {{ lastPage }}</span
            >
            <button
                type="button"
                :disabled="page >= lastPage"
                class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm disabled:opacity-40"
                @click="$emit('change', page + 1)"
            >
                ›
            </button>
        </div>
    </div>
</template>
