<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{ title: string; subtitle?: string; icon?: Component }>();
</script>

<template>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-4 animate-fade-in">
        <div class="flex items-center gap-3">
            <div
                v-if="icon"
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[#0f2a5c] text-white shadow-sm transition-transform duration-200 hover:scale-105"
            >
                <component :is="icon" class="h-5 w-5" />
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">
                    {{ title }}
                </h1>
                <p v-if="subtitle" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ subtitle }}
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
