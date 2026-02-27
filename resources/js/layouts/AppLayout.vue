<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppAdminLayout from '@/layouts/AppAdminLayout.vue';
import AppClientLayout from '@/layouts/AppClientLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const isAdmin = computed(() => (page.props.auth?.user as any)?.role === 'admin');
</script>

<template>
    <AppAdminLayout v-if="isAdmin">
        <slot />
    </AppAdminLayout>
    <AppClientLayout v-else>
        <slot />
    </AppClientLayout>
</template>
