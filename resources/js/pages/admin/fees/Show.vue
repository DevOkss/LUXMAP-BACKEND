<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Pencil, Trash2 } from 'lucide-vue-next';

interface Fee {
    id: number;
    name: string;
    description: string | null;
    amount: number;
    term: string | null;
    required_years: string[] | null;
    due_date: string | null;
    status: string;
    users_count: number;
    organization: { id: number; name: string } | null;
}

const props = defineProps<{
    fee: Fee;
    can_manage_fees: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Fees', href: '/admin/fees' },
    { title: props.fee.name, href: `/admin/fees/${props.fee.id}` },
];

const statusColors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
    posted: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
};

function ordinal(n: number): string {
    if (n % 100 >= 11 && n % 100 <= 13) return 'th';
    if (n % 10 === 1) return 'st';
    if (n % 10 === 2) return 'nd';
    if (n % 10 === 3) return 'rd';
    return 'th';
}

function yearsLabel(years: string[] | null): string {
    if (!years || years.includes('all')) return 'All Students';
    const sorted = [...years].sort((a, b) => Number(a) - Number(b));
    return `${sorted.map(y => `${y}${ordinal(Number(y))}`).join(', ')} Year`;
}

function doAction(action: 'publish' | 'unpublish') {
    router.post(route(`admin.fees.${action}`, { fee: props.fee.id }), {}, { preserveScroll: true });
}

function confirmDelete() {
    if (!window.confirm(`Delete "${props.fee.name}"? Assigned students will be unassigned, but payment records are kept.`)) return;
    router.delete(route('admin.fees.destroy', { fee: props.fee.id }), { preserveScroll: true });
}
</script>

<template>
    <Head :title="fee.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader :title="fee.name" subtitle="Fee details and student assignments.">
                <template #actions>
                    <span :class="statusColors[fee.status]" class="rounded-full px-3 py-1 text-xs font-medium capitalize">{{ fee.status }}</span>
                </template>
            </PageHeader>

            <div v-if="can_manage_fees" class="flex flex-wrap gap-2">
                <Button
                    v-if="fee.status === 'draft'"
                    @click="doAction('publish')"
                    class="rounded-xl bg-[#20673A] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#027F3B]"
                >
                    Post
                </Button>
                <Button
                    v-if="fee.status === 'posted'"
                    @click="doAction('unpublish')"
                    class="rounded-xl border px-4 py-2.5 text-sm font-medium text-amber-600 hover:bg-amber-50"
                >
                    Unpost
                </Button>
                <a
                    :href="`/admin/fees/${fee.id}/edit`"
                    class="inline-flex items-center gap-1.5 rounded-xl border px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
                >
                    <Pencil class="h-4 w-4" />
                    Edit
                </a>
                <Button
                    @click="confirmDelete"
                    class="rounded-xl px-4 py-2.5 text-sm font-medium text-red-500 hover:bg-red-50"
                    variant="ghost"
                >
                    <Trash2 class="h-4 w-4" />
                    Delete
                </Button>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <Card class="overflow-hidden">
                    <CardHeader class="border-b">
                        <CardTitle>Fee Details</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 pt-4">
                        <div class="flex justify-between"><span class="text-muted-foreground">Amount</span><span class="text-xl font-bold">₱{{ fee.amount.toFixed(2) }}</span></div>
                        <div class="flex justify-between"><span class="text-muted-foreground">Term</span><span class="font-medium">{{ fee.term || '—' }}</span></div>
                        <div class="flex justify-between"><span class="text-muted-foreground">Required Students</span><span class="font-medium">{{ yearsLabel(fee.required_years) }}</span></div>
                        <div class="flex justify-between"><span class="text-muted-foreground">Due Date</span><span class="font-medium">{{ fee.due_date ? new Date(fee.due_date).toLocaleDateString() : 'No due date' }}</span></div>
                        <div class="flex justify-between"><span class="text-muted-foreground">Assigned Students</span><span class="font-medium">{{ fee.users_count }}</span></div>
                        <div class="flex justify-between"><span class="text-muted-foreground">Status</span><span :class="statusColors[fee.status]" class="rounded px-2 py-0.5 text-xs font-medium">{{ fee.status }}</span></div>
                    </CardContent>
                </Card>

                <Card class="overflow-hidden">
                    <CardHeader class="border-b">
                        <CardTitle>Organization</CardTitle>
                    </CardHeader>
                    <CardContent class="pt-4">
                        <div class="flex justify-between"><span class="text-muted-foreground">Name</span><span class="font-medium">{{ fee.organization?.name || 'N/A' }}</span></div>
                    </CardContent>
                </Card>

                <Card v-if="fee.description" class="overflow-hidden md:col-span-2">
                    <CardHeader class="border-b">
                        <CardTitle>Description</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm text-muted-foreground whitespace-pre-wrap">{{ fee.description }}</p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
