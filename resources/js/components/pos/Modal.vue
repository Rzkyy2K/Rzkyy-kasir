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
        <div
            class="fixed inset-0 z-50 flex justify-center pointer-events-none"
            :class="[
                sheetOnMobile
                    ? 'items-end sm:items-center sm:p-4'
                    : 'items-center p-3 sm:p-4',
                { 'pointer-events-auto': open },
            ]"
        >
            <!-- Backdrop with smooth blur and fade (stays blurred until closed) -->
            <Transition
                enter-active-class="backdrop-enter-active"
                enter-from-class="backdrop-enter-from"
                enter-to-class="backdrop-enter-to"
                leave-active-class="backdrop-leave-active"
                leave-from-class="backdrop-leave-from"
                leave-to-class="backdrop-leave-to"
            >
                <div
                    v-if="open"
                    class="absolute inset-0 backdrop-active cursor-pointer pointer-events-auto"
                    @click="$emit('close')"
                />
            </Transition>

            <!-- Modal Content with smooth enter and exit -->
            <Transition
                :enter-active-class="sheetOnMobile ? 'sheet-slide-enter sm:modal-scale-enter' : 'modal-scale-enter'"
                :enter-from-class="sheetOnMobile ? 'translate-y-full sm:opacity-0 sm:scale-95' : 'opacity-0 scale-95'"
                :enter-to-class="sheetOnMobile ? 'translate-y-0 sm:opacity-100 sm:scale-100' : 'opacity-100 scale-100'"
                :leave-active-class="sheetOnMobile ? 'sheet-slide-leave sm:modal-scale-leave' : 'modal-scale-leave'"
                :leave-from-class="sheetOnMobile ? 'translate-y-0 sm:opacity-100 sm:scale-100' : 'opacity-100 scale-100'"
                :leave-to-class="sheetOnMobile ? 'translate-y-full sm:opacity-0 sm:scale-95' : 'opacity-0 scale-95'"
            >
                <div
                    v-if="open"
                    :class="[
                        'relative flex flex-col max-h-[88vh] w-full overflow-hidden bg-white shadow-2xl dark:bg-slate-900 dark:border dark:border-slate-800 dark:text-slate-100 pointer-events-auto',
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
                            class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 active-press dark:hover:bg-slate-800 dark:hover:text-slate-200 transition cursor-pointer"
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
            </Transition>
        </div>
    </Teleport>
</template>
