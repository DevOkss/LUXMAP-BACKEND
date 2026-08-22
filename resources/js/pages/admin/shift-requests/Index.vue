<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import Pagination from '@/components/Pagination.vue'
import { type BreadcrumbItem } from '@/types'
import { Head, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from '@/components/ui/dialog'
import { ref } from 'vue'

interface ShiftRequestRow {
    id: number
    student: { name: string; student_number: string | null } | null
    current: { institute: string | null; program: string | null }
    requested: { institute: string | null; program: string | null }
    reason: string | null
    status: 'pending' | 'approved' | 'rejected'
    remarks: string | null
    reviewed_at: string | null
    created_at: string | null
}

const props = defineProps<{
    requests: { data: ShiftRequestRow[]; current_page: number; last_page: number; total: number; per_page: number }
    filters: { status: string | null }
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Shift Requests', href: '/admin/shift-requests' },
]

const meta = {
    current_page: props.requests.current_page,
    last_page: props.requests.last_page,
    total: props.requests.total,
    per_page: props.requests.per_page ?? 20,
}

const reviewing = ref<ShiftRequestRow | null>(null)
const remarks = ref('')

function openReview(row: ShiftRequestRow) {
    reviewing.value = row
    remarks.value = ''
}

function confirmApprove() {
    if (!reviewing.value) return
    router.patch(
        `/admin/shift-requests/${reviewing.value.id}/approve`,
        {
            data: { remarks: remarks.value || null },
            preserveScroll: true,
            onSuccess: () => {
                reviewing.value = null
                remarks.value = ''
            },
        },
    )
}

function confirmReject() {
    if (!reviewing.value) return
    router.patch(
        `/admin/shift-requests/${reviewing.value.id}/reject`,
        {
            data: { remarks: remarks.value || null },
            preserveScroll: true,
            onSuccess: () => {
                reviewing.value = null
                remarks.value = ''
            },
        },
    )
}

const statusStyles: Record<ShiftRequestRow['status'], string> = {
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
}
</script>

<template>
    <Head title="Shift Requests" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="Shift Requests" subtitle="Review student requests to shift institute or program." />

            <Card class="overflow-hidden">
                <div class="flex flex-col gap-3 border-b px-5 py-4">
                    <p class="text-sm font-semibold">Shift Requests ({{ requests.total }})</p>
                </div>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50 text-left">
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Student</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Current</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Requested</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Reason</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Status</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in requests.data" :key="row.id" class="border-b transition-colors last:border-0 hover:bg-muted/40">
                                    <td class="px-5 py-3.5">
                                        <div class="font-medium">{{ row.student?.name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ row.student?.student_number }}</div>
                                    </td>
                                    <td class="px-5 py-3.5 text-muted-foreground">
                                        {{ row.current.institute || '—' }}
                                        <span v-if="row.current.program" class="block text-xs">{{ row.current.program }}</span>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="font-medium">{{ row.requested.institute }}</span>
                                        <span v-if="row.requested.program" class="block text-xs text-muted-foreground">{{ row.requested.program }}</span>
                                    </td>
                                    <td class="max-w-[240px] px-5 py-3.5 text-muted-foreground">{{ row.reason || '—' }}</td>
                                    <td class="px-5 py-3.5">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusStyles[row.status]">
                                            {{ row.status }}
                                        </span>
                                        <p v-if="row.remarks" class="mt-1 text-xs text-muted-foreground">{{ row.remarks }}</p>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <Button v-if="row.status === 'pending'" variant="outline" size="sm" @click="openReview(row)">
                                            Review
                                        </Button>
                                        <span v-else class="text-xs text-muted-foreground">Reviewed</span>
                                    </td>
                                </tr>
                                <tr v-if="requests.data.length === 0">
                                    <td colspan="6" class="px-5 py-10 text-center text-muted-foreground">No shift requests found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination :meta="meta" :filters="{ status: filters.status }" />
                </CardContent>
            </Card>

            <Dialog v-if="reviewing" :open="!!reviewing" @update:open="(v: boolean) => { if (!v) reviewing = null }">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Review shift request</DialogTitle>
                        <DialogDescription>
                            <span class="font-medium">{{ reviewing.student?.name }}</span> requests shifting from
                            {{ reviewing.current.institute }} / {{ reviewing.current.program }} to
                            {{ reviewing.requested.institute }} / {{ reviewing.requested.program }}.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium">Remarks (optional)</label>
                        <Input v-model="remarks" placeholder="Notes for the student" />
                    </div>
                    <DialogFooter>
                        <Button variant="destructive" @click="confirmReject">Reject</Button>
                        <Button @click="confirmApprove">Approve</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>