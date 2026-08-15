<script setup lang="ts">
import { computed } from 'vue';

interface IncomeBar {
    month: string;
    income: number;
    highlight?: boolean;
}

const props = withDefaults(
    defineProps<{
        data: IncomeBar[];
        formatValue?: (value: number) => string;
    }>(),
    {
        formatValue: (value: number) => String(value),
    },
);

const barWidth = 26;
const barGap = 18;
const padding = 20;
const chartHeight = 150;
const labelRow = 24;

const hasData = computed(() => props.data.some((d) => d.income > 0));
const maxIncome = computed(() => props.data.reduce((max, d) => Math.max(max, d.income), 0));
const viewWidth = computed(() => Math.max(props.data.length * (barWidth + barGap) + padding * 2, 200));
const viewHeight = computed(() => chartHeight + labelRow);

function barHeight(value: number): number {
    if (!hasData.value) {
        return 0;
    }

    return Math.max(2, (value / maxIncome.value) * chartHeight);
}

function xAt(index: number): number {
    return padding + index * (barWidth + barGap);
}

function labelValue(value: number): string {
    return props.formatValue(value);
}
</script>

<template>
    <div class="w-full overflow-x-auto">
        <svg
            :viewBox="`0 0 ${viewWidth} ${viewHeight}`"
            class="h-40 w-full min-w-full"
            role="img"
            aria-label="Income by month"
            aria-description="Monthly collected income for the selected term"
        >
            <template v-for="(datum, index) in data" :key="datum.month">
                <rect
                    class="transition-opacity hover:opacity-80"
                    :class="datum.highlight ? 'fill-amber-500' : hasData ? 'fill-primary' : 'fill-muted'"
                    :x="xAt(index)"
                    :y="chartHeight - barHeight(datum.income)"
                    :width="barWidth"
                    :height="barHeight(datum.income)"
                    rx="5"
                >
                    <title>{{ datum.month }} · {{ labelValue(datum.income) }}</title>
                </rect>

                <text
                    v-if="data.length <= 8"
                    :x="xAt(index) + barWidth / 2"
                    :y="chartHeight - barHeight(datum.income) - 6"
                    text-anchor="middle"
                    class="fill-muted-foreground"
                    style="font-size: 10px"
                >
                    {{ labelValue(datum.income) }}
                </text>

                <text :x="xAt(index) + barWidth / 2" :y="chartHeight + 18" text-anchor="middle" class="fill-muted-foreground" style="font-size: 10px">
                    {{ datum.month }}
                </text>
            </template>
        </svg>
    </div>
</template>
