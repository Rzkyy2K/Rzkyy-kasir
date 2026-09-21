<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Terang' },
    { value: 'dark', Icon: Moon, label: 'Gelap' },
    { value: 'system', Icon: Monitor, label: 'Sistem' },
] as const;
</script>

<template>
    <div
        class="inline-flex gap-1 rounded-xl bg-slate-100 p-1.5 border border-slate-200/80 dark:bg-slate-800/90 dark:border-slate-700/80"
    >
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            @click="updateAppearance(value)"
            type="button"
            :class="[
                'flex items-center gap-2 rounded-lg px-3.5 py-1.5 text-sm font-semibold transition-all cursor-pointer',
                appearance === value
                    ? 'bg-white text-blue-700 shadow-sm dark:bg-slate-900 dark:text-blue-400 dark:shadow-slate-950/40'
                    : 'text-slate-600 hover:text-slate-900 hover:bg-white/60 dark:text-slate-400 dark:hover:text-slate-100 dark:hover:bg-slate-700/50',
            ]"
        >
            <component :is="Icon" class="h-4 w-4" />
            <span>{{ label }}</span>
        </button>
    </div>
</template>
