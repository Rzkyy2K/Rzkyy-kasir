<script setup lang="ts">
import { X } from '@lucide/vue';

withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        wide?: boolean;
        sheetOnMobile?: boolean;
    }>(),
    {
        wide: false,
        sheetOnMobile: false,
    },
);

defineEmits<{ (e: 'close'): void }>();
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex justify-center"
                :class="[
                    sheetOnMobile
                        ? 'items-end sm:items-center sm:p-4'
                        : 'items-center p-3 sm:p-4',
                ]"
            >
                <!-- Backdrop -->
                <div
                    class="absolute inset-0 bg-slate-900/60 backdrop-blur-xs"
                    @click="$emit('close')"
                />

                <!-- Modal Content -->
                <div
                    :class="[
                        'relative flex flex-col max-h-[88vh] w-full overflow-hidden bg-white shadow-2xl dark:bg-slate-900 dark:border dark:border-slate-800 dark:text-slate-100 animate-scale-in',
                        sheetOnMobile
                            ? 'rounded-t-2xl sm:rounded-2xl'
                            : 'rounded-2xl',
                        wide ? 'max-w-lg sm:max-w-2xl lg:max-w-3xl' : 'max-w-md sm:max-w-lg',
                    ]"
                >
                    <!-- Drag handle bar for mobile bottom sheet -->
                    <div
                        v-if="sheetOnMobile"
                        class="mx-auto mt-2.5 -mb-1 h-1.5 w-12 shrink-0 rounded-full bg-slate-300 dark:bg-slate-700 sm:hidden"
                    />

                    <!-- Modal Header (Sticky) -->
                    <div
                        class="flex shrink-0 items-center justify-between border-b border-slate-100 px-4 py-3 sm:px-6 sm:py-3.5 dark:border-slate-800"
                    >
                        <h3 class="text-sm font-bold text-slate-900 sm:text-base truncate dark:text-white">
                            {{ title }}
                        </h3>
                        <button
                            type="button"
                            class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 active-press dark:hover:bg-slate-800 dark:hover:text-slate-200 transition"
                            @click="$emit('close')"
                        >
                            <X class="h-4 w-4 sm:h-5 sm:w-5" />
                        </button>
                    </div>

                    <!-- Modal Body (Scrollable) -->
                    <div class="flex-1 overflow-y-auto p-3.5 sm:p-6 text-slate-700 dark:text-slate-200">
                        <slot />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
