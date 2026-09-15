<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

const { title = '', description = '', showNav = undefined } = defineProps<{
    title?: string;
    description?: string;
    showNav?: boolean;
}>();
</script>

<template>
    <AuthLayout :title="title" :description="description" :show-nav="showNav">
        <slot />
    </AuthLayout>
</template>
