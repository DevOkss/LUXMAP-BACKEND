<script setup lang="ts">
import { computed } from 'vue'

interface Props {
    label: string
    value: string | number
    icon: object
    tone?: 'primary' | 'success' | 'info' | 'warning' | 'danger'
    trend?: string | null
}

const props = withDefaults(defineProps<Props>(), {
    tone: 'primary',
    trend: null,
})

const tones = {
    primary: {
        card: 'border-primary/25 bg-primary/10',
        icon: 'bg-primary text-primary-foreground',
        trend: 'text-primary',
    },
    success: {
        card: 'border-emerald-500/25 bg-emerald-500/10',
        icon: 'bg-emerald-500 text-white',
        trend: 'text-emerald-600 dark:text-emerald-400',
    },
    info: {
        card: 'border-sky-500/25 bg-sky-500/10',
        icon: 'bg-sky-500 text-white',
        trend: 'text-sky-600 dark:text-sky-400',
    },
    warning: {
        card: 'border-amber-500/25 bg-amber-500/10',
        icon: 'bg-amber-500 text-white',
        trend: 'text-amber-600 dark:text-amber-400',
    },
    danger: {
        card: 'border-destructive/25 bg-destructive/10',
        icon: 'bg-destructive text-destructive-foreground',
        trend: 'text-destructive',
    },
}

const toneClasses = computed(() => tones[props.tone])
</script>

<template>
    <div class="rounded-xl border p-5 transition-shadow hover:shadow-sm" :class="toneClasses.card">
        <div class="flex items-start gap-3">
            <div class="flex size-11 shrink-0 items-center justify-center rounded-lg" :class="toneClasses.icon">
                <component :is="icon" class="size-5" />
            </div>
            <div class="min-w-0">
                <p class="text-sm font-medium text-muted-foreground">{{ label }}</p>
                <p class="mt-1 text-2xl font-bold">{{ value }}</p>
                <p v-if="trend" class="mt-1 text-xs font-medium" :class="toneClasses.trend">{{ trend }}</p>
            </div>
        </div>
    </div>
</template>
