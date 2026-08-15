<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Card, CardContent } from '@/components/ui/card';
import { ref } from 'vue';

interface LogEntry {
    id: number;
    user: { id: number; name: string; student_number: string | null } | null;
    action: string;
    details: Record<string, unknown> | null;
    event: { uuid: string; title: string } | null;
    created_at: string;
}

interface LogsMeta {
    data: LogEntry[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}

const props = defineProps<{
    logs: LogsMeta;
    filters?: { from?: string; to?: string };
}>();

const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');

function applyFilters() {
    router.get('/admin/activity-logs', { from: from.value || undefined, to: to.value || undefined }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function resetFilters() {
    from.value = '';
    to.value = '';
    router.get('/admin/activity-logs', {}, { preserveState: true, preserveScroll: true });
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Activity Log', href: '/admin/activity-logs' },
];

const actionLabels: Record<string, string> = {
    created: 'Event created',
    updated: 'Event updated',
    deleted: 'Event deleted',
    published: 'Event posted',
    unpublished: 'Event unposted',
    completed: 'Event completed',
    qr_created: 'QR configuration created',
    qr_updated: 'QR configuration updated',
    qr_generated: 'QR code generated',
    qr_deleted: 'QR configuration removed',
    attendance_exported: 'Attendance exported',
};

function actionLabel(action: string): string {
    return actionLabels[action] || action;
}
</script>

<template>
    <Head title="Activity Log" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="Activity Log" subtitle="Actions performed by officers in your organizations." />

            <Card>
                <CardContent class="flex flex-wrap items-end gap-3 p-4">
                    <div class="grid gap-1.5">
                        <label for="log-from" class="text-xs font-semibold text-muted-foreground">From</label>
                        <input
                            id="log-from"
                            v-model="from"
                            type="date"
                            class="h-9 rounded-lg border border-input bg-background px-3 text-sm"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <label for="log-to" class="text-xs font-semibold text-muted-foreground">To</label>
                        <input
                            id="log-to"
                            v-model="to"
                            type="date"
                            class="h-9 rounded-lg border border-input bg-background px-3 text-sm"
                        />
                    </div>
                    <button
                        @click="applyFilters"
                        class="h-9 rounded-lg bg-[#20673A] px-4 text-sm font-semibold text-white hover:bg-[#027F3B]"
                    >
                        Apply
                    </button>
                    <button
                        @click="resetFilters"
                        class="h-9 rounded-lg border px-4 text-sm font-medium text-gray-600 hover:bg-gray-100"
                    >
                        Reset
                    </button>
                </CardContent>
            </Card>

            <Card class="overflow-hidden">
                <div class="border-b px-5 py-4">
                    <p class="text-sm font-semibold">All Actions ({{ logs.total }})</p>
                </div>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50 text-left">
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Officer</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Action</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Event</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="log in logs.data" :key="log.id" class="border-b transition-colors last:border-0 hover:bg-muted/40">
                                    <td class="px-5 py-3.5">
                                        <p class="font-medium">{{ log.user?.name || '—' }}</p>
                                        <p v-if="log.user?.student_number" class="text-xs text-muted-foreground">{{ log.user.student_number }}</p>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">
                                            {{ actionLabel(log.action) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <Link v-if="log.event" :href="`/admin/events/${log.event.uuid}`" class="font-medium text-primary hover:underline">
                                            {{ log.event.title }}
                                        </Link>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-muted-foreground">
                                        {{ new Date(log.created_at).toLocaleString() }}
                                    </td>
                                </tr>
                                <tr v-if="logs.data.length === 0">
                                    <td colspan="4" class="px-5 py-10 text-center text-muted-foreground">No actions logged yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination :meta="logs" :filters="filters" />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
