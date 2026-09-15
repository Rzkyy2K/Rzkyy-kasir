<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{ title: string; subtitle?: string; icon?: Component }>();
</script>

<template>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div
                v-if="icon"
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[#0f2a5c] text-white shadow"
            >
                <component :is="icon" class="h-5 w-5" />
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900">
                    {{ title }}
                </h1>
                <p v-if="subtitle" class="mt-1 text-sm text-slate-500">
                    {{ subtitle }}
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
