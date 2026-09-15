<script setup lang="ts">
import { X } from '@lucide/vue';

defineProps<{ open: boolean; title: string; wide?: boolean }>();
defineEmits<{ (e: 'close'): void }>();
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
        >
            <div
                class="absolute inset-0 bg-slate-900/50"
                @click="$emit('close')"
            />
            <div
                :class="[
                    'relative max-h-[92vh] w-full overflow-y-auto rounded-t-2xl bg-white p-4 shadow-xl sm:rounded-2xl sm:p-6',
                    wide ? 'sm:max-w-3xl' : 'sm:max-w-lg',
                ]"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-900">
                        {{ title }}
                    </h3>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                        @click="$emit('close')"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <slot />
            </div>
        </div>
    </Teleport>
</template>
