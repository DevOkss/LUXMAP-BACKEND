<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Pencil, QrCode, Download, Trash2 } from 'lucide-vue-next';

interface QrConfiguration {
    id: number;
    type: 'time_in' | 'time_out';
    valid_from: string;
    valid_until: string;
    is_generated: boolean;
    required_years: string[] | null;
}

interface Event {
    uuid: string;
    title: string;
    description: string | null;
    venue: string | null;
    time_from: string | null;
    time_to: string | null;
    event_date: string;
    status: string;
    organization: { id: number; code: string; name: string; type: string } | null;
    qr_configurations: QrConfiguration[];
}

const props = defineProps<{
    event: Event;
    can_manage_events: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Activities', href: '/admin/events' },
    { title: 'Activity' },
];

const statusColors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
    published: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    ongoing: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    completed: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};

const statusLabels: Record<string, string> = {
    draft: 'Draft',
    published: 'Posted',
    ongoing: 'Ongoing',
    completed: 'Completed',
    cancelled: 'Cancelled',
};

function fmtTime(t: string | null): string {
    if (!t) return '—';
    const [h = 0, m = 0] = t.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const hr = h % 12 || 12;
    return `${hr}:${String(m).padStart(2, '0')} ${ampm}`;
}

function doAction(action: string) {
    router.post(route(`admin.events.${action}`, { event: props.event.uuid }), {}, { preserveScroll: true });
}

function confirmDelete() {
    if (!window.confirm('Delete this activity? This cannot be undone.')) return;
    router.delete(route('admin.events.destroy', { event: props.event.uuid }), { preserveScroll: true });
}
</script>

<template>
    <Head :title="event.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader :title="event.title">
                <template #actions>
                    <span
                        :class="statusColors[event.status] || 'bg-gray-100 text-gray-700'"
                        class="rounded-full px-3 py-1 text-xs font-medium"
                    >
                        {{ statusLabels[event.status] || event.status }}
                    </span>
                </template>
            </PageHeader>

            <div v-if="can_manage_events" class="flex flex-wrap gap-2">
                <a
                    v-if="event.status === 'draft'"
                    :href="`/admin/events/${event.uuid}/qr`"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-[#20673A] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#027F3B]"
                >
                    <QrCode class="h-4 w-4" />
                    Configure QR
                </a>
                <a
                    v-if="event.status === 'draft'"
                    :href="`/admin/events/${event.uuid}/edit`"
                    class="inline-flex items-center gap-1.5 rounded-xl border px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
                >
                    <Pencil class="h-4 w-4" />
                    Edit
                </a>
                <Button
                    v-if="event.status === 'draft' && event.qr_configurations?.length"
                    @click="doAction('publish')"
                    class="rounded-xl bg-[#20673A] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#027F3B]"
                >
                    Post
                </Button>
                <Button
                    v-if="event.status === 'published'"
                    @click="doAction('unpublish')"
                    class="rounded-xl border px-4 py-2.5 text-sm font-medium text-amber-600 hover:bg-amber-50"
                >
                    Unpost
                </Button>
                <Button
                    v-if="event.status === 'published'"
                    @click="doAction('complete')"
                    class="rounded-xl bg-[#20673A] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#027F3B]"
                >
                    Complete
                </Button>
                <a
                    :href="route('admin.events.attendance-export', { event: event.uuid })"
                    class="inline-flex items-center gap-1.5 rounded-xl border px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
                >
                    <Download class="h-4 w-4" />
                    Download Attendance
                </a>
                <Button
                    v-if="event.status === 'draft'"
                    @click="confirmDelete"
                    variant="ghost"
                    class="rounded-xl px-4 py-2.5 text-sm font-medium text-red-500 hover:bg-red-50"
                >
                    <Trash2 class="h-4 w-4" />
                    Delete
                </Button>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <Card class="overflow-hidden">
                    <CardHeader class="border-b">
                        <CardTitle>Details</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 pt-4">
                        <div>
                            <span class="text-sm text-muted-foreground">Status</span>
                            <div>
                                <span
                                    :class="statusColors[event.status] || 'bg-gray-100 text-gray-700'"
                                    class="rounded-full px-2.5 py-1 text-xs font-medium"
                                >
                                    {{ statusLabels[event.status] || event.status }}
                                </span>
                            </div>
                        </div>
                        <div>
                            <span class="text-sm text-muted-foreground">Organization</span>
                            <p class="font-medium">{{ event.organization?.name || '—' }}</p>
                        </div>
                        <div>
                            <span class="text-sm text-muted-foreground">Date</span>
                            <p class="font-medium">{{ new Date(event.event_date).toLocaleDateString() }}</p>
                        </div>
                        <div>
                            <span class="text-sm text-muted-foreground">Time</span>
                            <p class="font-medium">{{ fmtTime(event.time_from) }} – {{ fmtTime(event.time_to) }}</p>
                        </div>
                        <div v-if="event.venue">
                            <span class="text-sm text-muted-foreground">Venue</span>
                            <p class="font-medium">{{ event.venue }}</p>
                        </div>
                        <div v-if="event.description">
                            <span class="text-sm text-muted-foreground">Description</span>
                            <p class="text-sm">{{ event.description }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card class="overflow-hidden">
                    <CardHeader class="border-b">
                        <CardTitle>QR Sessions</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 pt-4">
                        <p v-if="!event.qr_configurations?.length" class="text-sm text-muted-foreground">
                            No QR configurations for this activity yet.
                        </p>
                        <div
                            v-for="config in event.qr_configurations || []"
                            :key="config.id"
                            class="flex items-center justify-between rounded-lg border p-3"
                        >
                            <div>
                                <p class="text-sm font-medium capitalize">
                                    {{ config.type === 'time_in' ? 'Time In' : 'Time Out' }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ fmtTime(config.valid_from) }} – {{ fmtTime(config.valid_until) }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <a
                                    v-if="config.is_generated && can_manage_events"
                                    :href="route('admin.events.qr-configurations.download', { event: event.uuid, config: config.id })"
                                    class="inline-flex items-center gap-1 text-xs font-medium text-[#20673A] hover:underline"
                                >
                                    <Download class="h-3.5 w-3.5" />
                                    PDF
                                </a>
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="config.is_generated ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-200' : 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-200'"
                                >
                                    {{ config.is_generated ? 'QR Generated' : 'Pending' }}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
