<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { type BreadcrumbItem } from '@/types'
import { Head, Link, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { computed } from 'vue'

interface CalendarEvent {
    id: number
    uuid: string
    title: string
    event_date: string
    status: string
    organization: { id: number; code: string; name: string } | null
}

const props = defineProps<{
    events: CalendarEvent[]
    month: number
    year: number
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Calendar', href: '/admin/calendar' },
]

const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

const eventsByDate = computed<Record<string, CalendarEvent[]>>(() => {
    const map: Record<string, CalendarEvent[]> = {}
    for (const event of props.events) {
        if (!map[event.event_date]) map[event.event_date] = []
        map[event.event_date].push(event)
    }
    return map
})

const firstDay = computed(() => new Date(props.year, props.month - 1, 1).getDay())
const daysInMonth = computed(() => new Date(props.year, props.month, 0).getDate())

const todayStr = new Date().toLocaleDateString('en-CA')

function dateStr(day: number): string {
    const m = String(props.month).padStart(2, '0')
    const d = String(day).padStart(2, '0')
    return `${props.year}-${m}-${d}`
}

function changeMonth(delta: number) {
    let month = props.month + delta
    let year = props.year
    if (month > 12) { month = 1; year++ }
    if (month < 1) { month = 12; year-- }
    router.get('/admin/calendar', { month, year }, { preserveState: true })
}

function statusColor(status: string): string {
    if (status === 'ongoing') return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'
    if (status === 'completed') return 'bg-muted text-muted-foreground'
    if (status === 'cancelled') return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-200'
    return 'bg-primary/10 text-primary'
}
</script>

<template>
    <Head title="Calendar" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="Calendar of Activities" subtitle="View activities across your organizations by month.">
                <template #actions>
                    <div class="flex items-center gap-2">
                        <Button variant="outline" size="icon" @click="changeMonth(-1)">
                            <ChevronLeft class="h-4 w-4" />
                        </Button>
                        <span class="min-w-40 text-center text-sm font-semibold">{{ monthNames[month - 1] }} {{ year }}</span>
                        <Button variant="outline" size="icon" @click="changeMonth(1)">
                            <ChevronRight class="h-4 w-4" />
                        </Button>
                    </div>
                </template>
            </PageHeader>

            <Card class="overflow-hidden">
                <div class="border-b px-5 py-4">
                    <p class="text-sm font-semibold">Activities</p>
                </div>
                <CardContent class="p-5">
                    <div class="overflow-x-auto">
                        <div class="grid grid-cols-7 gap-px overflow-hidden rounded-lg border bg-border" style="min-width: 42rem">
                        <div v-for="day in dayNames" :key="day" class="bg-background p-2 text-center text-xs font-medium text-muted-foreground">
                            {{ day }}
                        </div>
                        <div v-for="i in firstDay" :key="`pad-${i}`" class="bg-background p-2 min-h-24" />
                        <div v-for="day in daysInMonth" :key="day" class="bg-background p-2 min-h-24">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium" :class="dateStr(day) === todayStr ? 'text-primary' : ''">{{ day }}</span>
                            </div>
                            <div class="mt-1 flex flex-col gap-1">
                                <Link
                                    v-for="event in eventsByDate[dateStr(day)] || []"
                                    :key="event.id"
                                    :href="`/admin/events/${event.uuid}`"
                                    class="rounded px-1.5 py-0.5 text-[11px] leading-tight hover:opacity-80"
                                    :class="statusColor(event.status)"
                                >
                                    {{ event.title }}
                                </Link>
                            </div>
                        </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
