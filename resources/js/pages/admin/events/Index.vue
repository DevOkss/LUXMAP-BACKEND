<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { type BreadcrumbItem } from '@/types'
import { Head, Link } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Plus } from 'lucide-vue-next'

interface Event {
    id: number
    uuid: string
    title: string
    description: string | null
    venue: string | null
    event_date: string
    time_from: string | null
    time_to: string | null
    status: string
    organization: { id: number; code: string; name: string } | null
}

defineProps<{
    events: { data: Event[]; current_page: number; last_page: number; total: number }
    can_manage_events: boolean
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Activities', href: '/admin/events' },
]

const statusColors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
    published: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    ongoing: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    completed: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
}

const statusLabels: Record<string, string> = {
    draft: 'Draft',
    published: 'Posted',
    ongoing: 'Ongoing',
    completed: 'Completed',
    cancelled: 'Cancelled',
}

function fmtTime(t: string | null): string {
    if (!t) return '—';
    const [h = 0, m = 0] = t.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const hr = h % 12 || 12;
    return `${hr}:${String(m).padStart(2, '0')} ${ampm}`;
}
</script>

<template>
    <Head title="Activities" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="Activities" subtitle="Browse all scheduled student activities.">
                <template #actions>
                    <Link v-if="can_manage_events" href="/admin/events/create">
                        <Button class="rounded-xl bg-[#20673A] font-semibold text-white hover:bg-[#027F3B]">
                            <Plus class="h-4 w-4" />
                            New Activity
                        </Button>
                    </Link>
                </template>
            </PageHeader>

            <Card class="overflow-hidden">
                <div class="border-b px-5 py-4">
                    <p class="text-sm font-semibold">All Activities ({{ events.total }})</p>
                </div>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50 text-left">
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Title</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Organization</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Date</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Time</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Status</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="event in events.data" :key="event.id" class="border-b transition-colors last:border-0 hover:bg-muted/40">
                                    <td class="px-5 py-3.5">
                                        <Link :href="`/admin/events/${event.uuid}`" class="font-medium text-primary hover:underline">
                                            {{ event.title }}
                                        </Link>
                                        <p v-if="event.venue" class="text-xs text-muted-foreground">{{ event.venue }}</p>
                                    </td>
                                    <td class="px-5 py-3.5 text-muted-foreground">{{ event.organization?.name || 'N/A' }}</td>
                                    <td class="px-5 py-3.5">{{ new Date(event.event_date).toLocaleDateString() }}</td>
                                    <td class="px-5 py-3.5 whitespace-nowrap">{{ fmtTime(event.time_from) }} – {{ fmtTime(event.time_to) }}</td>
                                    <td class="px-5 py-3.5">
                                        <span :class="statusColors[event.status]" class="rounded-full px-2.5 py-1 text-xs font-medium">
                                            {{ statusLabels[event.status] || event.status }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                        <Link :href="`/admin/events/${event.uuid}`">
                                            <span class="text-sm font-medium text-primary hover:underline">View</span>
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="events.data.length === 0">
                                    <td colspan="5" class="px-5 py-10 text-center text-muted-foreground">No activities found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="events.last_page > 1" class="flex flex-col gap-3 border-t px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-muted-foreground">Page {{ events.current_page }} of {{ events.last_page }}</p>

                        <div class="flex gap-2">
                            <Link v-if="events.current_page > 1" :href="`/admin/events?page=${events.current_page - 1}`" class="rounded-lg border px-3 py-1.5 text-sm transition-colors hover:bg-accent">Previous</Link>
                            <Link v-if="events.current_page < events.last_page" :href="`/admin/events?page=${events.current_page + 1}`" class="rounded-lg border px-3 py-1.5 text-sm transition-colors hover:bg-accent">Next</Link>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
