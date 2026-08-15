<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { type BreadcrumbItem } from '@/types'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { Card, CardContent } from '@/components/ui/card'
import { computed } from 'vue'

interface Submission {
    group_key: string
    status: string
    rejection_reason?: string | null
    reference_number: string | null
    payment_channel?: string | null
    receipt_image_url?: string | null
    submitted_at?: string | null
    verified_at?: string | null
    academic_term?: string | null
    student: { id: number; name: string; student_number: string | null } | null
    organization: { id: number; name: string } | null
    verifiedBy?: { id: number; name: string } | null
    total: number
    items: Array<{ fee_type: string; amount: number; status?: string; fee?: { id: number; name: string } | null; event?: { id: number; title: string } | null }>
}

const props = defineProps<{
    submission: Submission
    can_verify: boolean
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payments', href: '/admin/payments' },
    { title: 'Verification', href: '/admin/payments' },
]

const rejectForm = useForm({ rejection_reason: '' })

const isPending = computed(() => props.submission.status === 'pending')
const isSuperAdminViewOnly = computed(() => !props.can_verify)

function approve() {
    useForm({}).post(`/admin/payments/submissions/${props.submission.group_key}/approve`, { preserveScroll: true })
}

function reject() {
    rejectForm.post(`/admin/payments/submissions/${props.submission.group_key}/reject`, { preserveScroll: true })
}

const label = (item: Submission['items'][number]) => item.fee?.name || item.event?.title || (item.fee_type === 'penalty' ? 'Penalty' : 'Fee')
</script>

<template>
    <Head title="Verify Payment" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="Payment Verification" :subtitle="`Submission ${submission.group_key}`" />

            <Card>
                <div class="border-b px-5 py-4 flex items-center justify-between">
                    <p class="text-sm font-semibold">Submission details</p>
                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium capitalize text-amber-800">{{ submission.status }}</span>
                </div>
                <CardContent class="p-5 space-y-4 text-sm">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-muted-foreground">Student</p>
                            <p class="font-medium">{{ submission.student?.name }}</p>
                            <p class="text-muted-foreground">{{ submission.student?.student_number }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-muted-foreground">Organization</p>
                            <p class="font-medium">{{ submission.organization?.name }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-muted-foreground">Academic Term</p>
                            <p class="font-medium">{{ submission.academic_term || '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-muted-foreground">Payment Reference</p>
                            <p class="font-medium">{{ submission.reference_number || '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-muted-foreground">Submitted</p>
                            <p class="font-medium">{{ submission.submitted_at ? new Date(submission.submitted_at).toLocaleString() : '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-muted-foreground">Channel</p>
                            <p class="font-medium capitalize">{{ submission.payment_channel || '—' }}</p>
                        </div>
                    </div>

                    <div>
                        <p class="text-xs uppercase tracking-wide text-muted-foreground mb-2">Obligations being paid</p>
                        <div class="space-y-2">
                            <div v-for="(item, i) in submission.items" :key="i" class="flex items-center justify-between rounded-lg border px-4 py-3">
                                <div>
                                    <p class="font-medium">{{ label(item) }}</p>
                                    <p class="text-xs text-muted-foreground capitalize">{{ item.fee_type }}</p>
                                </div>
                                <p class="font-semibold">₱{{ item.amount.toFixed(2) }}</p>
                            </div>
                            <div class="flex items-center justify-between rounded-lg bg-muted/50 px-4 py-3">
                                <p class="font-medium">Total</p>
                                <p class="text-lg font-bold">₱{{ submission.total.toFixed(2) }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="submission.receipt_image_url" class="space-y-2">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Uploaded receipt</p>
                        <a :href="submission.receipt_image_url" target="_blank" class="text-primary hover:underline">
                            View uploaded receipt image
                        </a>
                    </div>

                    <div v-if="submission.verified_at" class="text-muted-foreground">
                        Verified by {{ submission.verifiedBy?.name || 'an officer' }} on {{ new Date(submission.verified_at).toLocaleString() }}
                    </div>

                    <div v-if="submission.rejection_reason" class="rounded-md bg-destructive/10 px-4 py-3 text-destructive">
                        Rejection reason: {{ submission.rejection_reason }}
                    </div>

                    <div v-if="isSuperAdminViewOnly" class="rounded-md bg-muted px-4 py-3 text-muted-foreground">
                        You are viewing as super admin. Verification is handled by the organization's heads and officers.
                    </div>

                    <div v-if="isPending && can_verify" class="flex flex-col gap-4 pt-2">
                        <div>
                            <button
                                type="button"
                                class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                                @click="approve"
                            >
                                Approve Payment
                            </button>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Only approve after verifying the reference number, amount, and date/time in the organization's official account.
                            </p>
                        </div>

                        <div class="max-w-md space-y-2">
                            <label class="block text-sm font-medium">Rejection reason</label>
                            <textarea
                                v-model="rejectForm.rejection_reason"
                                rows="2"
                                placeholder="Reason for rejection (required)"
                                class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            ></textarea>
                            <p v-if="rejectForm.errors.rejection_reason" class="text-sm text-destructive">{{ rejectForm.errors.rejection_reason }}</p>
                            <button
                                type="button"
                                :disabled="rejectForm.processing"
                                class="rounded-md border border-destructive/40 bg-destructive/10 px-4 py-2 text-sm font-medium text-destructive hover:bg-destructive/20 disabled:opacity-50"
                                @click="reject"
                            >
                                {{ rejectForm.processing ? 'Rejecting...' : 'Reject Payment' }}
                            </button>
                        </div>
                    </div>

                    <div class="pt-2">
                        <Link href="/admin/payments?tab=pending" class="text-sm font-medium text-primary hover:underline">← Back to pending verifications</Link>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>