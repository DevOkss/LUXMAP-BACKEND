<script setup lang="ts">
interface Props {
    title: string
    subtitle?: string
}

withDefaults(defineProps<Props>(), {
    subtitle: undefined,
})
</script>

<template>
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold tracking-tight">{{ title }}</h1>
            <p v-if="subtitle" class="mt-1 text-sm text-muted-foreground">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
