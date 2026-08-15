<script setup lang="ts">
import { computed } from 'vue';

interface DonutSegment {
    label: string;
    value: number;
    color: string;
}

const props = defineProps<{
    segments: DonutSegment[];
    centerTitle?: string;
    centerValue?: string;
}>();

const radius = 42;
const strokeWidth = 16;
const circle = 2 * Math.PI * radius;

const total = computed(() => props.segments.reduce((sum, segment) => sum + segment.value, 0));

const arcs = computed(() => {
    let offset = 0;

    return props.segments.map((segment) => {
        const fraction = total.value > 0 ? segment.value / total.value : 0;
        const dash = fraction * circle;

        const arc = {
            color: segment.color,
            dasharray: `${dash} ${circle - dash}`,
            dashoffset: -offset,
        };

        offset += dash;

        return arc;
    });
});
</script>

<template>
    <div class="flex shrink-0 items-center justify-center">
        <div class="relative" style="width: 140px; height: 140px">
            <svg viewBox="0 0 120 120" width="100%" height="100%">
                <circle cx="60" cy="60" :r="radius" fill="none" stroke="hsl(var(--muted-foreground) / 0.15)" :stroke-width="strokeWidth" />
                <g transform="rotate(-90 60 60)">
                    <circle
                        v-for="(arc, index) in arcs"
                        :key="index"
                        cx="60"
                        cy="60"
                        :r="radius"
                        fill="none"
                        :stroke="arc.color"
                        :stroke-width="strokeWidth"
                        :stroke-dasharray="arc.dasharray"
                        :stroke-dashoffset="arc.dashoffset"
                    />
                </g>
            </svg>

            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                <p v-if="centerTitle" class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                    {{ centerTitle }}
                </p>
                <p class="text-base font-bold">{{ centerValue }}</p>
            </div>
        </div>
    </div>
</template>
