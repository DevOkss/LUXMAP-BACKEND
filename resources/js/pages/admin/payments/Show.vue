<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { BadgeCheck, FileText, User } from 'lucide-vue-next';
import { computed } from 'vue';

interface BatchItem {
    id: number;
    fee_type: string;
    amount: number;
    status: string;
    isExempted: boolean;
    notes?: string | null;
    fee?: { id: number; name: string } | null;
    event?: { id: number; title: string } | null;
}

interface BatchReceipt {
    id: number;
    receipt_number: string;
    issued_at: string | null;
    fee_type: string;
    amount: number;
}

interface TransactionBatch {
    batch_id: string | null;
    uuid: string;
    id: number;
    total: number;
    count: number;
    status: string;
    isExempted?: boolean;
    payment_method?: string | null;
    reference_number: string | null;
    paid_at?: string | null;
    exempted_at?: string | null;
    created_at?: string | null;
    notes?: string | null;
    exemptedBy?: { id: number; name: string } | null;
    processedBy?: { id: number; name: string } | null;
    receipts?: BatchReceipt[];
    items: BatchItem[];
}

interface PersonRef {
    id: number;
    name: string;
    student_number: string | null;
}

interface OrgRef {
    id: number;
    name: string;
}

const props = defineProps<{
    batch: TransactionBatch;
    student: PersonRef | null;
    organization: OrgRef | null;
    term: string | null;
    history: TransactionBatch[];
    can_process: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payments', href: '/admin/payments' },
    { title: '#' + props.batch.uuid.slice(0, 8), href: `/admin/payments/${props.batch.uuid}` },
];

const statusColors: Record<string, string> = {
    paid: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    exempted: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200',
    refunded: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-200',
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
    approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
};

const receiptNumbers = computed(() => (props.batch.receipts || []).map((r) => r.receipt_number));
const primaryReceipt = computed(() => receiptNumbers.value[0] || null);

function money(value: number): string {
    return `₱${value.toFixed(2)}`;
}

function itemLabel(item: BatchItem): string {
    if (item.fee?.name) return item.fee.name;
    if (item.event?.title) return item.event.title;
    return item.fee_type === 'penalty' ? 'Penalty' : 'Fee';
}

function formatDate(value?: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleString();
}

const TENS: string[] = ['', 'Ten', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
const ONES: string[] = [
    '',
    'One',
    'Two',
    'Three',
    'Four',
    'Five',
    'Six',
    'Seven',
    'Eight',
    'Nine',
    'Ten',
    'Eleven',
    'Twelve',
    'Thirteen',
    'Fourteen',
    'Fifteen',
    'Sixteen',
    'Seventeen',
    'Eighteen',
    'Nineteen',
];

function twoDigits(n: number): string {
    if (n < 20) return ONES[n];
    return `${TENS[Math.floor(n / 10)]}${n % 10 ? ' ' + ONES[n % 10] : ''}`;
}

function threeDigits(n: number): string {
    const hundreds = Math.floor(n / 100);
    const rest = n % 100;
    let out = '';
    if (hundreds) out += `${ONES[hundreds]} Hundred`;
    if (rest) out += `${out ? ' ' : ''}${twoDigits(rest)}`;
    return out;
}

function numberToWords(value: number): string {
    const amount = Math.round(value * 100);
    const whole = Math.floor(amount / 100);
    const cents = amount % 100;
    let out = '';
    const billion = Math.floor(whole / 1000000000);
    const million = Math.floor((whole % 1000000000) / 1000000);
    const thousand = Math.floor((whole % 1000000) / 1000);
    const remainder = whole % 1000;
    if (billion) out += `${threeDigits(billion)} Billion `;
    if (million) out += `${threeDigits(million)} Million `;
    if (thousand) out += `${threeDigits(thousand)} Thousand `;
    if (remainder) out += threeDigits(remainder);
    out = out.trim() || 'Zero';
    out += ` Pesos and ${String(cents).padStart(2, '0')}/100`;
    return out;
}
</script>

<template>
    <Head :title="'Payment #' + batch.uuid.slice(0, 8)" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader :title="'Payment #' + batch.uuid.slice(0, 8)" :subtitle="`${term ? term + ' · ' : ''}${student?.name || 'Student'}`">
                <template #actions>
                    <span :class="statusColors[batch.status]" class="rounded-full px-3 py-1 text-xs font-medium capitalize">{{
                        batch.isExempted ? 'Exempted' : batch.status
                    }}</span>
                </template>
            </PageHeader>

            <!-- Official receipt-style panel -->
            <Card class="overflow-hidden">
                <CardHeader class="border-b bg-muted/40">
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="size-4" />
                        Payment Receipt
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-6">
                    <div class="mx-auto max-w-2xl rounded-lg border border-dashed p-6 shadow-sm">
                        <div class="flex items-start justify-between border-b pb-4">
                            <div>
                                <p class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Official Receipt</p>
                                <p class="mt-1 font-mono text-lg font-semibold">#{{ primaryReceipt || batch.uuid.slice(0, 8).toUpperCase() }}</p>
                                <p v-if="receiptNumbers.length > 1" class="text-xs text-muted-foreground">
                                    + {{ receiptNumbers.length - 1 }} more receipt{{ receiptNumbers.length > 2 ? 's' : '' }} issued
                                </p>
                            </div>
                            <div class="text-right text-xs text-muted-foreground">
                                <p>Date</p>
                                <p class="font-medium text-foreground">{{ formatDate(batch.created_at || batch.paid_at) }}</p>
                                <p v-if="batch.academic_term" class="mt-1">Term</p>
                                <p v-if="batch.academic_term" class="font-medium text-foreground">{{ batch.academic_term }}</p>
                            </div>
                        </div>

                        <div class="border-b py-4">
                            <div class="flex items-start justify-between gap-6">
                                <div>
                                    <p class="text-xs uppercase tracking-wider text-muted-foreground">Payer</p>
                                    <p class="font-medium">{{ student?.name || 'N/A' }}</p>
                                    <p v-if="student?.student_number" class="text-xs text-muted-foreground">{{ student.student_number }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs uppercase tracking-wider text-muted-foreground">Organization</p>
                                    <p class="font-medium">{{ organization?.name || 'N/A' }}</p>
                                    <p class="text-xs text-muted-foreground">{{ (batch.payment_method || '').replace('_', ' ') || '—' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="py-4">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b text-left text-xs uppercase tracking-wider text-muted-foreground">
                                        <th class="pb-2 font-semibold">Description</th>
                                        <th class="pb-2 text-right font-semibold">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in batch.items" :key="item.id" class="border-b last:border-0">
                                        <td class="py-2.5">
                                            <p class="font-medium">{{ itemLabel(item) }}</p>
                                            <p v-if="item.isExempted" class="text-xs font-semibold uppercase text-indigo-500">Exempted</p>
                                            <p v-else-if="item.notes" class="text-xs text-muted-foreground">{{ item.notes }}</p>
                                        </td>
                                        <td class="py-2.5 text-right font-mono">{{ item.isExempted ? '₱0.00' : money(item.amount) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="text-sm font-semibold">
                                        <td class="pt-3 uppercase tracking-wider text-muted-foreground">
                                            Total ({{ batch.count }} {{ batch.count > 1 ? 'items' : 'item' }})
                                        </td>
                                        <td class="pt-3 text-right font-mono text-base">{{ batch.isExempted ? '₱0.00' : money(batch.total) }}</td>
                                    </tr>
                                </tfoot>
                            </table>

                            <p class="mt-4 text-xs italic text-muted-foreground">{{ numberToWords(batch.isExempted ? 0 : batch.total) }} Only</p>
                        </div>

                        <div
                            v-if="batch.isExempted"
                            class="rounded-md border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-900 dark:bg-indigo-900/30"
                        >
                            <div class="flex items-center gap-2">
                                <BadgeCheck class="size-4 text-indigo-500" />
                                <p class="text-sm font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-300">Exemption Granted</p>
                            </div>
                            <p v-if="batch.notes" class="mt-2 whitespace-pre-wrap text-sm">{{ batch.notes }}</p>
                            <p class="mt-2 text-xs text-muted-foreground">
                                Granted by
                                <span class="font-medium text-foreground">{{ batch.exemptedBy?.name || batch.processedBy?.name || 'Officer' }}</span>
                                on {{ formatDate(batch.exempted_at || batch.created_at) }}
                            </p>
                        </div>

                        <div v-if="batch.notes && !batch.isExempted" class="mt-4 rounded-md bg-muted p-3 text-sm">
                            <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Notes</p>
                            <p class="mt-1 whitespace-pre-wrap text-muted-foreground">{{ batch.notes }}</p>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t pt-4 text-xs">
                            <div>
                                <p class="text-muted-foreground">Reference</p>
                                <p class="font-mono font-medium">{{ batch.reference_number || '—' }}</p>
                            </div>
                            <div v-if="batch.receipts && batch.receipts.length" class="text-right">
                                <p class="text-muted-foreground">Official Receipt(s) Issued</p>
                                <p v-for="r in batch.receipts" :key="r.id" class="font-mono font-medium">
                                    {{ r.receipt_number }} · {{ formatDate(r.issued_at) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Student card -->
            <Card>
                <CardHeader class="border-b">
                    <CardTitle class="flex items-center gap-2">
                        <User class="size-4" />
                        Student
                    </CardTitle>
                </CardHeader>
                <CardContent class="grid gap-3 pt-4 sm:grid-cols-2">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Name</span><span class="font-medium">{{ student?.name || 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Student No.</span><span class="font-medium">{{ student?.student_number || 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Organization</span><span class="font-medium">{{ organization?.name || 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Academic Term</span><span class="font-medium">{{ term || 'N/A' }}</span>
                    </div>
                </CardContent>
            </Card>

            <!-- All payments this term -->
            <Card>
                <CardHeader class="border-b">
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="size-4" />
                        All Payments This Term
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50 text-left">
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">#</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Organization</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Obligations</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Amount</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Status</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Date</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in history"
                                    :key="row.uuid"
                                    class="border-b text-xs transition-colors last:border-0 hover:bg-muted/40"
                                    :class="row.uuid === batch.uuid ? 'bg-primary/5' : ''"
                                >
                                    <td class="px-5 py-3 text-muted-foreground">#{{ row.uuid.slice(0, 8) }}</td>
                                    <td class="px-5 py-3 text-muted-foreground">{{ row.organization?.name || '—' }}</td>
                                    <td class="px-5 py-3">
                                        <span
                                            v-for="(item, i) in row.items"
                                            :key="i"
                                            class="mr-1 inline-block rounded bg-muted px-1.5 py-0.5 text-xs"
                                        >
                                            {{ itemLabel(item) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 font-medium">{{ row.isExempted ? '₱0.00' : money(row.total) }}</td>
                                    <td class="px-5 py-3">
                                        <span :class="statusColors[row.status]" class="rounded-full px-2 py-0.5 text-xs font-medium capitalize">{{
                                            row.isExempted ? 'Exempted' : row.status
                                        }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-muted-foreground">{{ formatDate(row.created_at) }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right">
                                        <Link :href="`/admin/payments/${row.uuid}`" class="text-xs font-medium text-primary hover:underline"
                                            >View</Link
                                        >
                                    </td>
                                </tr>
                                <tr v-if="history.length === 0">
                                    <td colspan="7" class="px-5 py-10 text-center text-muted-foreground">No other payments this term.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
