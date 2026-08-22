<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

interface Props {
    class?: string;
}

defineProps<Props>();

const page = usePage<{ name: string }>();
const appName = page.props.name || 'SOMS';
</script>

<template>
    <img src="/storage/logos/luxmap.png" alt="" class="h-9 w-9 rounded-lg object-cover" />
    <div class="ml-2 grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-semibold leading-none">{{ appName }}</span>
        <span class="mt-0.5 truncate text-[11px] text-muted-foreground">Student Org. Management</span>
    </div>
</template>
