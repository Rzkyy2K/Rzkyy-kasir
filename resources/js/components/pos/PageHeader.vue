<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{ title: string; subtitle?: string; icon?: Component }>();
</script>

<template>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-4 animate-fade-in">
        <div class="flex items-center gap-3">
            <div
                v-if="icon"
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-white/20 bg-white/10 text-white shadow-lg backdrop-blur-md transition-transform duration-200 hover:scale-105"
            >
                <component :is="icon" class="h-5 w-5 text-cyan-200" />
            </div>
            <div>
                <h1 class="text-xl font-black tracking-tight text-white">
                    {{ title }}
                </h1>
                <p v-if="subtitle" class="mt-0.5 text-xs sm:text-sm font-medium text-blue-100/90">
                    {{ subtitle }}
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
