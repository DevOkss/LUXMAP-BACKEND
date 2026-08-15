<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from 'vue-sonner';
import { useFlashToast } from '@/composables/useFlashToast';
import type { BreadcrumbItemType } from '@/types';
import { computed, onMounted, ref } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

useFlashToast();

const isDark = ref(document.documentElement.classList.contains('dark'));
const toastTheme = computed(() => (isDark.value ? 'dark' : 'light'));

onMounted(() => {
    const observer = new MutationObserver(() => {
        isDark.value = document.documentElement.classList.contains('dark');
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div class="flex flex-1 flex-col p-4 md:p-6">
                <slot />
            </div>
        </AppContent>
        <Toaster richColors position="top-right" :theme="toastTheme" />
    </AppShell>
</template>
