<script setup lang="ts">
defineProps<{
    options: { value: number | null; label: string }[];
    modelValue: number | null;
}>();
defineEmits<{ (e: 'update:modelValue', v: number | null): void }>();
</script>

<template>
    <div class="flex gap-2 overflow-x-auto pb-1">
        <button
            v-for="o in options"
            :key="String(o.value)"
            type="button"
            :class="[
                'shrink-0 rounded-full border px-4 py-2 text-sm font-medium transition',
                modelValue === o.value
                    ? 'border-blue-700 bg-blue-700 text-white shadow-sm'
                    : 'border-slate-200 bg-white text-slate-600 hover:border-blue-300 hover:text-blue-700',
            ]"
            @click="$emit('update:modelValue', o.value)"
        >
            {{ o.label }}
        </button>
    </div>
</template>
