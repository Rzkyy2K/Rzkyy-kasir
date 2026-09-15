<script setup lang="ts">
import type { Component } from 'vue';

type Warna = 'blue' | 'emerald' | 'amber' | 'violet' | 'slate';

withDefaults(
    defineProps<{
        title: string;
        value: string;
        hint?: string;
        color?: Warna;
        icon?: Component | null;
    }>(),
    { color: 'slate', icon: null },
);

const gaya: Record<
    Warna,
    { kartu: string; label: string; nilai: string; ikon: string }
> = {
    blue: {
        kartu: 'border-slate-200 border-t-4 border-t-blue-600 bg-white',
        label: 'text-slate-500',
        nilai: 'text-slate-900',
        ikon: 'bg-blue-50 text-blue-700',
    },
    emerald: {
        kartu: 'border-slate-200 border-t-4 border-t-emerald-600 bg-white',
        label: 'text-slate-500',
        nilai: 'text-slate-900',
        ikon: 'bg-emerald-50 text-emerald-700',
    },
    amber: {
        kartu: 'border-slate-200 border-t-4 border-t-amber-500 bg-white',
        label: 'text-slate-500',
        nilai: 'text-slate-900',
        ikon: 'bg-amber-50 text-amber-700',
    },
    violet: {
        kartu: 'border-slate-200 border-t-4 border-t-violet-600 bg-white',
        label: 'text-slate-500',
        nilai: 'text-slate-900',
        ikon: 'bg-violet-50 text-violet-700',
    },
    slate: {
        kartu: 'border-slate-200 bg-white',
        label: 'text-slate-500',
        nilai: 'text-slate-900',
        ikon: 'bg-slate-100 text-slate-600',
    },
};
</script>

<template>
    <div
        :class="[
            'flex items-center gap-3 rounded-2xl border p-4 shadow-sm',
            gaya[color].kartu,
        ]"
    >
        <div
            v-if="icon"
            :class="[
                'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl shadow-sm',
                gaya[color].ikon,
            ]"
        >
            <component :is="icon" class="h-5 w-5" />
        </div>
        <div class="min-w-0">
            <p
                :class="[
                    'text-xs font-medium tracking-wide uppercase',
                    gaya[color].label,
                ]"
            >
                {{ title }}
            </p>
            <p
                :class="[
                    'mt-1 truncate text-xl font-bold',
                    gaya[color].nilai,
                ]"
            >
                {{ value }}
            </p>
            <p v-if="hint" class="mt-0.5 text-xs text-slate-400">{{ hint }}</p>
        </div>
    </div>
</template>
