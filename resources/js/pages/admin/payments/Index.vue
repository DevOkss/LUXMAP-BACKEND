<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Download, Info } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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
    academic_term?: string | null;
    user: { id: number; name: string; student_number: string | null } | null;
    organization: { id: number; name: string } | null;
    items: Array<{
        fee_type: string;
        amount: number;
        status: string;
        isExempted: boolean;
        fee?: { id: number; name: string } | null;
        event?: { id: number; title: string } | null;
    }>;
}

interface SubmissionGroup {
    group_key: string;
    status: string;
    reference_number: string | null;
    payment_channel?: string | null;
    receipt_image_url: string | null;
    submitted_at: string | null;
    academic_term?: string | null;
    organization: { id: number; name: string } | null;
    student: { id: number; name: string; student_number: string | null } | null;
    total: number;
    items: Array<{ fee_type: string; amount: number; fee?: { id: number; name: string } | null; event?: { id: number; title: string } | null }>;
}

interface OutstandingStudent {
    id: number;
    name: string;
    student_number: string | null;
    year_level?: number | null;
    total_balance: number;
    has_obligations: boolean;
}

interface AcademicTermOption {
    id: number;
    name: string;
    is_active: boolean;
}

const props = defineProps<{
    tab: string;
    transactions: TransactionBatch[];
    transactions_pagination: { current_page: number; last_page: number; total: number; per_page: number };
    transactions_total: number;
    pending: SubmissionGroup[];
    pending_pagination: { current_page: number; last_page: number; total: number; per_page: number };
    pending_total: number;
    pending_search: string;
    outstanding: {
        students: OutstandingStudent[];
        searched: boolean;
        search: string;
        pagination: { current_page: number; last_page: number; total: number; per_page: number };
    };
    outstanding_total: number;
    filters: Record<string, string>;
    academic_terms: AcademicTermOption[];
    selected_term: number | null;
    export_fees: Array<{ id: number; label: string }>;
    export_penalty_events: Array<{ id: number; label: string }>;
    page: number;
    per_page: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payments', href: '/admin/payments' },
];

const tabs = [
    { key: 'transactions', label: 'Transactions' },
    { key: 'pending', label: 'Pending Verification' },
    { key: 'outstanding', label: 'Outstanding' },
];

const activeTab = computed(() => (props.tab === 'pending' || props.tab === 'outstanding' ? props.tab : 'transactions'));

const summary = computed(() => {
    if (activeTab.value === 'pending') {
        return { label: 'Total Pending Verification', amount: props.pending_total };
    }
    if (activeTab.value === 'outstanding') {
        return { label: 'Total Outstanding', amount: props.outstanding_total };
    }
    return { label: 'Total Transactions', amount: props.transactions_total };
});

const search = ref(props.filters.search || '');

const selectedTerm = ref<number | null>(props.selected_term ?? null);

const pendingSearch = ref(props.pending_search || '');

const txnSearch = ref(props.filters.search || '');
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');

const filtersOpen = ref(false);

const hasDateFilter = computed(() => !!(dateFrom.value || dateTo.value));
const hasTxnFilters = computed(() => !!(txnSearch.value || hasDateFilter.value));

function itemLabel(item: { fee_type: string; fee?: { name: string } | null; event?: { title: string } | null }): string {
    if (item.fee?.name) return item.fee.name;
    if (item.event?.title) return item.event.title;
    return item.fee_type === 'penalty' ? 'Penalty' : 'Fee';
}

function formatDate(value?: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleString();
}

function switchTab(key: string) {
    router.get('/admin/payments', { tab: key, academic_term_id: selectedTerm.value ?? undefined }, { preserveState: true, preserveScroll: true });
}

function changeTerm() {
    router.get(
        '/admin/payments',
        { tab: activeTab.value, academic_term_id: selectedTerm.value ?? undefined, search: search.value || undefined },
        { preserveState: false, preserveScroll: true },
    );
}

function runSearch() {
    router.get(
        '/admin/payments',
        { tab: 'outstanding', academic_term_id: selectedTerm.value ?? undefined, search: search.value },
        { preserveState: false, preserveScroll: true },
    );
}

function runPendingSearch() {
    router.get(
        '/admin/payments',
        {
            tab: 'pending',
            academic_term_id: selectedTerm.value ?? undefined,
            pending_search: pendingSearch.value || undefined,
        },
        { preserveState: false, preserveScroll: true },
    );
}

function runTxnFilters() {
    filtersOpen.value = false;
    router.get(
        '/admin/payments',
        {
            tab: 'transactions',
            academic_term_id: selectedTerm.value ?? undefined,
            search: txnSearch.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        },
        { preserveState: false, preserveScroll: true },
    );
}

function applyDateFilters() {
    runTxnFilters();
}

function resetTxnFilters() {
    txnSearch.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    runTxnFilters();
}

const exportOpen = ref(false);
const includeFees = ref(true);
const includePenalties = ref(true);
const selectedFeeIds = ref<number[]>([]);
const selectedEventIds = ref<number[]>([]);

function openExport() {
    includeFees.value = true;
    includePenalties.value = true;
    selectedFeeIds.value = [];
    selectedEventIds.value = [];
    exportOpen.value = true;
}

function toggleSelection(list: number[], id: number) {
    return list.includes(id) ? list.filter((v) => v !== id) : [...list, id];
}

function downloadExport() {
    const params: Record<string, string> = {
        academic_term_id: selectedTerm.value?.toString() ?? '',
        include_fees: includeFees.value ? '1' : '0',
        include_penalties: includePenalties.value ? '1' : '0',
    };
    if (selectedFeeIds.value.length > 0 && includeFees.value) {
        params.fee_ids = selectedFeeIds.value.join(',');
    }
    if (selectedEventIds.value.length > 0 && includePenalties.value) {
        params.event_ids = selectedEventIds.value.join(',');
    }
    const query = Object.entries(params)
        .filter(([, value]) => value !== '')
        .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value)}`)
        .join('&');
    window.location.href = `/admin/payments/export${query ? `?${query}` : ''}`;
}

const statusColors: Record<string, string> = {
    paid: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    exempted: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200',
    refunded: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-200',
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
};
</script>

<template>
    <Head title="Payments" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="Payments" subtitle="Outstanding balances, pending verifications, and confirmed transactions." />

            <div class="grid gap-4">
                <Card>
                    <CardContent class="flex flex-col gap-1 p-5">
                        <p class="text-sm font-medium text-muted-foreground">{{ summary.label }}</p>
                        <p class="text-2xl font-bold">₱{{ summary.amount.toFixed(2) }}</p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-3">
                    <div class="flex items-center gap-1 rounded-lg bg-muted p-1">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            class="rounded-md px-3.5 py-1.5 text-sm font-medium transition-colors"
                            :class="activeTab === tab.key ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                            @click="switchTab(tab.key)"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <select
                        v-model="selectedTerm"
                        aria-label="Academic term"
                        class="flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        @change="changeTerm"
                    >
                        <option v-for="term in academic_terms" :key="term.id" :value="term.id">
                            {{ term.name }}{{ term.is_active ? ' (current)' : '' }}
                        </option>
                    </select>
                </div>

                <!-- Transactions -->
                <div v-if="activeTab === 'transactions'" class="p-0">
                    <form class="flex flex-wrap items-center gap-2 border-b px-5 py-3" @submit.prevent="runTxnFilters">
                        <input
                            v-model="txnSearch"
                            type="text"
                            placeholder="Search student name or number..."
                            class="h-9 w-56 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        />
                        <button
                            type="button"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm font-medium text-muted-foreground hover:bg-muted"
                            :class="hasDateFilter ? 'border-primary text-primary' : ''"
                            @click="filtersOpen = true"
                        >
                            Date Filters{{ hasDateFilter ? ' · Active' : '' }}
                        </button>
                        <button
                            v-if="hasTxnFilters"
                            type="button"
                            class="rounded-md border border-input bg-background px-3 py-1.5 text-sm font-medium text-muted-foreground hover:bg-muted"
                            @click="resetTxnFilters"
                        >
                            Reset
                        </button>
                        <button
                            type="button"
                            class="ml-auto inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3.5 text-sm font-medium text-primary-foreground shadow-sm hover:bg-primary/90"
                            @click="openExport"
                        >
                            <Download class="size-4" />
                            Export
                        </button>
                    </form>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50 text-left">
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">#</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Student</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Obligations</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Amount</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Method</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Reference</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Status</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Date</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="batch in transactions"
                                    :key="batch.uuid"
                                    class="border-b transition-colors last:border-0 hover:bg-muted/40"
                                >
                                    <td class="px-5 py-3.5 text-xs text-muted-foreground">#{{ batch.uuid.slice(0, 8) }}</td>
                                    <td class="px-5 py-3.5">
                                        <div class="font-medium">{{ batch.user?.name || 'N/A' }}</div>
                                        <div class="text-xs text-muted-foreground">{{ batch.user?.student_number || '' }}</div>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex flex-wrap gap-1">
                                            <span
                                                v-for="(item, i) in batch.items"
                                                :key="i"
                                                class="inline-block rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground"
                                            >
                                                {{ itemLabel(item) }}
                                            </span>
                                        </div>
                                        <div v-if="batch.count > batch.items.length" class="mt-1 text-xs text-muted-foreground">
                                            +{{ batch.count - batch.items.length }} more
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 font-medium">{{ batch.isExempted ? '₱0.00' : `₱${batch.total.toFixed(2)}` }}</td>
                                    <td class="px-5 py-3.5 capitalize text-muted-foreground">{{ (batch.payment_method || '').replace('_', ' ') }}</td>
                                    <td class="px-5 py-3.5 text-muted-foreground">{{ batch.reference_number || '—' }}</td>
                                    <td class="px-5 py-3.5">
                                        <span :class="statusColors[batch.status]" class="rounded-full px-2.5 py-1 text-xs font-medium capitalize">
                                            {{ batch.isExempted ? 'Exempted' : batch.status }}{{ batch.count > 1 ? ` · ${batch.count}` : '' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-xs text-muted-foreground">{{ formatDate(batch.created_at) }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                        <Link :href="`/admin/payments/${batch.uuid}`" class="text-sm font-medium text-primary hover:underline"
                                            >View</Link
                                        >
                                    </td>
                                </tr>
                                <tr v-if="transactions.length === 0">
                                    <td colspan="9" class="px-5 py-10 text-center text-muted-foreground">No transactions yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination
                        :meta="transactions_pagination"
                        :filters="{
                            tab: 'transactions',
                            academic_term_id: selectedTerm?.toString(),
                            search: txnSearch,
                            date_from: dateFrom,
                            date_to: dateTo,
                        }"
                    />
                </div>

                <!-- Pending verification -->
                <div v-if="activeTab === 'pending'" class="p-0">
                    <form class="flex flex-wrap items-center gap-2 border-b px-5 py-3" @submit.prevent="runPendingSearch">
                        <input
                            v-model="pendingSearch"
                            type="text"
                            placeholder="Search student name or number..."
                            class="h-9 w-56 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        />
                        <button type="submit" class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-white hover:bg-primary/90">
                            Search
                        </button>
                    </form>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50 text-left">
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Student</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Organization</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Obligations</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Amount</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Reference</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Submitted</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="group in pending"
                                    :key="group.group_key"
                                    class="border-b transition-colors last:border-0 hover:bg-muted/40"
                                >
                                    <td class="px-5 py-3.5">
                                        <div class="font-medium">{{ group.student?.name || 'N/A' }}</div>
                                        <div class="text-xs text-muted-foreground">{{ group.student?.student_number || '' }}</div>
                                    </td>
                                    <td class="px-5 py-3.5 text-muted-foreground">{{ group.organization?.name || '—' }}</td>
                                    <td class="px-5 py-3.5 text-muted-foreground">
                                        <span
                                            v-for="(item, i) in group.items"
                                            :key="i"
                                            class="mr-1 inline-block rounded bg-muted px-1.5 py-0.5 text-xs"
                                        >
                                            {{ item.fee?.name || item.event?.title || (item.fee_type === 'penalty' ? 'Penalty' : 'Fee') }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 font-medium">₱{{ group.total.toFixed(2) }}</td>
                                    <td class="px-5 py-3.5 text-muted-foreground">{{ group.reference_number || '—' }}</td>
                                    <td class="px-5 py-3.5 text-muted-foreground">
                                        {{ group.submitted_at ? new Date(group.submitted_at).toLocaleString() : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                        <Link
                                            :href="`/admin/payments/submissions/${group.group_key}`"
                                            class="text-sm font-medium text-primary hover:underline"
                                            >Verify</Link
                                        >
                                    </td>
                                </tr>
                                <tr v-if="pending.length === 0">
                                    <td colspan="7" class="px-5 py-10 text-center text-muted-foreground">No submissions awaiting verification.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination
                        :meta="pending_pagination"
                        :filters="{ tab: 'pending', academic_term_id: selectedTerm?.toString(), pending_search: pendingSearch }"
                    />
                </div>

                <!-- Outstanding -->
                <div v-if="activeTab === 'outstanding'" class="p-5">
                    <form class="mb-4 flex max-w-md gap-2" @submit.prevent="runSearch">
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search student name or number..."
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        />
                        <button type="submit" class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-white hover:bg-primary/90">
                            Search
                        </button>
                    </form>

                    <div class="overflow-x-auto rounded-lg border">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50 text-left">
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Student</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Student #</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Year</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        Outstanding Balance
                                    </th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="student in outstanding.students"
                                    :key="student.id"
                                    class="border-b transition-colors last:border-0 hover:bg-muted/40"
                                >
                                    <td class="px-5 py-3.5 font-medium">{{ student.name }}</td>
                                    <td class="px-5 py-3.5 text-muted-foreground">{{ student.student_number }}</td>
                                    <td class="px-5 py-3.5 text-muted-foreground">{{ student.year_level || '—' }}</td>
                                    <td class="px-5 py-3.5">
                                        <span :class="student.has_obligations ? 'font-semibold text-amber-600' : 'text-muted-foreground'">
                                            ₱{{ student.total_balance.toFixed(2) }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                        <Link
                                            :href="`/admin/payments/students/${student.id}/obligations`"
                                            class="inline-flex size-8 items-center justify-center rounded-md border border-input bg-background text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                            :aria-label="`View obligations for ${student.name}`"
                                        >
                                            <Info class="size-4" />
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="outstanding.students.length === 0">
                                    <td colspan="5" class="px-5 py-10 text-center text-muted-foreground">
                                        {{
                                            outstanding.search
                                                ? 'No students found for this search.'
                                                : 'No students in your scope for the selected term.'
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination
                        :meta="outstanding.pagination"
                        :filters="{ tab: 'outstanding', academic_term_id: selectedTerm?.toString(), search: search }"
                    />
                </div>
            </Card>

            <Dialog
                :open="filtersOpen"
                @update:open="
                    (v: boolean) => {
                        filtersOpen = v;
                    }
                "
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Date range filter</DialogTitle>
                        <DialogDescription>Show transactions recorded within this date range.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4">
                        <div class="grid gap-2">
                            <label class="text-sm font-medium" for="date-from">From</label>
                            <input
                                id="date-from"
                                v-model="dateFrom"
                                type="date"
                                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                        </div>
                        <div class="grid gap-2">
                            <label class="text-sm font-medium" for="date-to">To</label>
                            <input
                                id="date-to"
                                v-model="dateTo"
                                type="date"
                                class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" @click="filtersOpen = false">Cancel</Button>
                        <Button @click="applyDateFilters">Apply</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                :open="exportOpen"
                @update:open="
                    (v: boolean) => {
                        exportOpen = v;
                    }
                "
            >
                <DialogContent class="max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Export transactions</DialogTitle>
                        <DialogDescription>
                            Download the selected term's transactions as an Excel file. Waived (exempted) rows are highlighted in amber.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-5">
                        <div class="grid gap-3">
                            <label class="flex items-center gap-2 text-sm font-medium">
                                <Checkbox
                                    :checked="includeFees"
                                    @update:checked="
                                        (v: boolean | 'indeterminate') => {
                                            includeFees = v === true;
                                        }
                                    "
                                />
                                Include fees
                            </label>
                            <div v-if="includeFees && export_fees.length > 0" class="max-h-40 overflow-y-auto rounded-md border p-3">
                                <p class="mb-2 text-xs text-muted-foreground">Leave unselected to include all fees.</p>
                                <label v-for="fee in export_fees" :key="fee.id" class="flex items-center gap-2 py-0.5 text-sm">
                                    <Checkbox
                                        :checked="selectedFeeIds.includes(fee.id)"
                                        @update:checked="
                                            () => {
                                                selectedFeeIds = toggleSelection(selectedFeeIds, fee.id);
                                            }
                                        "
                                    />
                                    {{ fee.label }}
                                </label>
                            </div>
                            <p v-else-if="includeFees" class="text-xs text-muted-foreground">No fees available for the selected term.</p>
                        </div>

                        <div class="grid gap-3">
                            <label class="flex items-center gap-2 text-sm font-medium">
                                <Checkbox
                                    :checked="includePenalties"
                                    @update:checked="
                                        (v: boolean | 'indeterminate') => {
                                            includePenalties = v === true;
                                        }
                                    "
                                />
                                Include penalties
                            </label>
                            <div v-if="includePenalties && export_penalty_events.length > 0" class="max-h-40 overflow-y-auto rounded-md border p-3">
                                <p class="mb-2 text-xs text-muted-foreground">Leave unselected to include all penalties.</p>
                                <label v-for="event in export_penalty_events" :key="event.id" class="flex items-center gap-2 py-0.5 text-sm">
                                    <Checkbox
                                        :checked="selectedEventIds.includes(event.id)"
                                        @update:checked="
                                            (v) => {
                                                selectedEventIds = toggleSelection(selectedEventIds, event.id);
                                            }
                                        "
                                    />
                                    {{ event.label }}
                                </label>
                            </div>
                            <p v-else-if="includePenalties" class="text-xs text-muted-foreground">
                                No penalty events available for the selected term.
                            </p>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" @click="exportOpen = false">Cancel</Button>
                        <Button :disabled="!includeFees && !includePenalties" @click="downloadExport">Download Excel</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
