<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import DashboardBarChart from '@/components/charts/DashboardBarChart.vue';
import DashboardDonut from '@/components/charts/DashboardDonut.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowUpRight, Banknote, Building2, CalendarDays, GraduationCap, ShieldCheck, Wallet } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface TermOption {
    id: number;
    name: string;
    is_active: boolean;
}

interface CurrentTerm {
    id: number;
    name: string;
    start_date: string | null;
    end_date: string | null;
}

interface OrgShape {
    id: number;
    code: string;
    name: string;
    type: string;
}

interface DashboardStats {
    total_income: number;
    exempted_amount: number;
    total_students: number;
    total_officers: number;
    pending_verifications: number;
}

interface IncomeDatum {
    month: string;
    income: number;
    highlight?: boolean;
}

interface OrgBreakdownRow {
    organization: OrgShape;
    students: number;
    officers: number;
}

interface RecentPayment {
    id: number;
    amount: number;
    status: string;
    paid_at: string | null;
    user: { name: string } | null;
}

interface UpcomingEvent {
    uuid: string;
    title: string;
    event_date: string;
    venue: string | null;
    organization: { id: number; code: string; name: string } | null;
}

const props = defineProps<{
    stats: DashboardStats;
    terms: TermOption[];
    selected_term: number | null;
    current_term: CurrentTerm | null;
    scope_orgs: OrgShape[];
    income_chart: IncomeDatum[];
    income_breakdown: { fees: number; penalties: number };
    org_breakdown: OrgBreakdownRow[];
    recent_payments: RecentPayment[];
    upcoming_events: UpcomingEvent[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const selectedTerm = ref<number | null>(props.selected_term);
watch(
    () => props.selected_term,
    (value) => {
        selectedTerm.value = value;
    },
);

const php = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

function formatPhp(value: number | string | null | undefined): string {
    return php.format(Number(value ?? 0));
}

function changeTerm() {
    router.get('/dashboard', { academic_term_id: selectedTerm.value || undefined }, { preserveState: true, preserveScroll: true });
}

const selectedTermOption = computed(() => props.terms.find((term) => term.id === selectedTerm.value));
const selectedTermName = computed(() => selectedTermOption.value?.name ?? '');
const currentTermName = computed(() => props.current_term?.name ?? 'No active academic term');

const scopeLabel = computed(() => (props.scope_orgs.length ? props.scope_orgs.map((org) => org.code).join(' · ') : 'All organizations'));

const pageSubtitle = computed(
    () =>
        `Overview of collections, students, and officers ${selectedTermName.value ? `for ${selectedTermName.value}` : ''} across ${scopeLabel.value}.`,
);

const currentTermRange = computed(() => {
    if (!props.current_term?.start_date || !props.current_term?.end_date) {
        return null;
    }

    return `${formatDate(props.current_term.start_date)} – ${formatDate(props.current_term.end_date)}`;
});

const heroGradient = 'linear-gradient(135deg, hsl(150 62% 12%), hsl(var(--primary)) 70%)';

const feeColor = 'hsl(var(--primary))';
const penaltyColor = 'hsl(38 92% 50%)';

const incomeTotal = computed(() => (props.income_breakdown.fees ?? 0) + (props.income_breakdown.penalties ?? 0));
const incomeSegments = computed(() => [
    { label: 'Fees', value: props.income_breakdown?.fees ?? 0, color: feeColor },
    { label: 'Penalties', value: props.income_breakdown?.penalties ?? 0, color: penaltyColor },
]);

const incomeChartTotal = computed(() => props.income_chart.reduce((sum, datum) => sum + datum.income, 0));

function percentage(value: number): number {
    if (incomeTotal.value <= 0) {
        return 0;
    }

    return Math.round((value / incomeTotal.value) * 100);
}

function formatDate(value: string): string {
    return new Date(`${value}T00:00:00`).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

const typeLabels: Record<string, string> = {
    ssc: 'School',
    isc: 'Institute',
    sro: 'Program',
};

const typeBadges: Record<string, string> = {
    ssc: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    isc: 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
    sro: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
};

const statusColors: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    completed: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    failed: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="Dashboard" :subtitle="pageSubtitle">
                <template #actions>
                    <label class="flex items-center gap-2">
                        <span class="hidden text-sm font-medium text-muted-foreground md:inline">Term</span>
                        <select
                            v-model="selectedTerm"
                            aria-label="Academic term"
                            class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            @change="changeTerm"
                        >
                            <option v-for="term in terms" :key="term.id" :value="term.id">
                                {{ term.name }}{{ term.is_active ? ' (current)' : '' }}
                            </option>
                        </select>
                    </label>
                </template>
            </PageHeader>

            <!-- Current term -->
            <section class="relative overflow-hidden rounded-2xl p-6 text-primary-foreground shadow-sm" :style="{ background: heroGradient }">
                <div class="pointer-events-none absolute -right-8 -top-12 size-44 rounded-full bg-white/10 blur-2xl" />
                <div class="pointer-events-none absolute -bottom-16 right-24 size-44 rounded-full bg-amber-300/10 blur-2xl" />

                <div class="relative flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/20">
                            <CalendarDays class="size-6" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wider text-primary-foreground/70">Current Academic Term</p>
                            <p class="mt-1 truncate text-2xl font-bold tracking-tight">{{ currentTermName }}</p>
                            <p class="mt-1 text-sm text-primary-foreground/70">
                                {{ currentTermRange ?? 'Set an active term in Academic Terms to begin collecting.' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-4">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold ring-1 ring-white/25">
                            <span class="size-1.5 rounded-full bg-emerald-300" />
                            Active
                        </span>
                        <div class="text-right">
                            <p class="text-xs font-medium uppercase tracking-wider text-primary-foreground/60">Scope</p>
                            <p class="max-w-56 truncate text-sm font-semibold">{{ scopeLabel }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Stat cards -->
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Total Income" :value="formatPhp(stats.total_income)" :icon="Wallet" tone="success" />
                <StatCard label="Total Students" :value="stats.total_students" :icon="GraduationCap" tone="primary" />
                <StatCard label="Officers" :value="stats.total_officers" :icon="ShieldCheck" tone="info" />
                <StatCard label="Pending Verifications" :value="stats.pending_verifications" :icon="AlertTriangle" tone="warning" />
            </div>

            <!-- Income charts -->
            <div class="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader class="flex-row items-center justify-between space-y-0 border-b pb-4">
                        <CardTitle class="text-base font-semibold">Income Trend</CardTitle>
                        <span
                            class="rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground"
                            :title="'Amount waived via exemptions'"
                        >
                            {{ formatPhp(stats.exempted_amount) }} exempted
                        </span>
                    </CardHeader>
                    <CardContent class="pt-5">
                        <div v-if="income_chart.length" class="flex items-center gap-6">
                            <DashboardBarChart :data="income_chart" :format-value="formatPhp" class="min-w-0 flex-1" />
                            <div class="hidden shrink-0 border-l pl-6 md:block">
                                <p class="text-sm text-muted-foreground">Term total</p>
                                <p class="mt-1 text-2xl font-bold">{{ formatPhp(incomeChartTotal) }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">collected payments</p>
                            </div>
                        </div>
                        <div v-else class="py-10 text-center text-sm text-muted-foreground">No collected income for the selected term yet.</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="border-b pb-4">
                        <CardTitle class="text-base font-semibold">Income Breakdown</CardTitle>
                    </CardHeader>
                    <CardContent class="pt-5">
                        <div v-if="incomeTotal > 0" class="flex flex-col items-center gap-6 md:flex-row">
                            <DashboardDonut :segments="incomeSegments" center-title="Term income" :center-value="formatPhp(incomeTotal)" />
                            <div class="flex-1 space-y-3">
                                <div v-for="segment in incomeSegments" :key="segment.label" class="flex items-center justify-between text-sm">
                                    <span class="flex items-center gap-2 font-medium">
                                        <span class="size-2.5 rounded-full" :style="{ backgroundColor: segment.color }" />
                                        {{ segment.label }}
                                    </span>
                                    <span class="text-right">
                                        <span class="block font-medium">{{ formatPhp(segment.value) }}</span>
                                        <span class="block text-xs text-muted-foreground">{{ percentage(segment.value) }}%</span>
                                    </span>
                                </div>
                                <div class="flex items-center justify-between border-t pt-3 text-sm">
                                    <span class="text-muted-foreground">Waived (exemptions)</span>
                                    <span class="font-medium">{{ formatPhp(stats.exempted_amount) }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="py-10 text-center text-sm text-muted-foreground">Nothing collected for the selected term yet.</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Students & officers by organization -->
            <Card>
                <CardHeader class="border-b pb-4">
                    <CardTitle class="text-base font-semibold">Students &amp; Officers by Organization</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="org_breakdown.length === 0" class="py-10 text-center text-sm text-muted-foreground">
                        No organizations in your scope.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-xs uppercase tracking-wide text-muted-foreground">
                                    <th class="px-5 py-3 font-medium">Organization</th>
                                    <th class="px-5 py-3 text-center font-medium">Type</th>
                                    <th class="px-5 py-3 text-center font-medium">Students</th>
                                    <th class="px-5 py-3 text-center font-medium">Officers</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="row in org_breakdown" :key="row.organization.id" class="transition-colors hover:bg-muted/40">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                <Building2 class="size-4" />
                                            </div>
                                            <div class="min-w-0">
                                                <p class="truncate font-medium">{{ row.organization.name }}</p>
                                                <p class="text-xs text-muted-foreground">{{ row.organization.code }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <span
                                            class="inline-block rounded-full px-2.5 py-1 text-xs font-medium"
                                            :class="typeBadges[row.organization.type] ?? 'bg-muted text-muted-foreground'"
                                        >
                                            {{ typeLabels[row.organization.type] ?? row.organization.type.toUpperCase() }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-center font-semibold">{{ row.students }}</td>
                                    <td class="px-5 py-3 text-center font-semibold">{{ row.officers }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Recent activity -->
            <div class="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader class="border-b pb-4">
                        <CardTitle class="text-base font-semibold">Recent Payments</CardTitle>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="recent_payments.length === 0" class="py-10 text-center text-sm text-muted-foreground">No recent payments.</div>
                        <ul v-else class="divide-y">
                            <li v-for="payment in recent_payments" :key="payment.id" class="flex items-center justify-between px-5 py-3 text-sm">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                        <Banknote class="size-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate font-medium">{{ payment.user?.name || 'N/A' }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ payment.paid_at ? new Date(payment.paid_at).toLocaleDateString() : '' }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    <p class="font-medium">{{ formatPhp(payment.amount) }}</p>
                                    <span :class="statusColors[payment.status]" class="rounded-full px-2 py-0.5 text-xs font-medium capitalize">
                                        {{ payment.status }}
                                    </span>
                                </div>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="border-b pb-4">
                        <CardTitle class="text-base font-semibold">Upcoming Activities</CardTitle>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="upcoming_events.length === 0" class="py-10 text-center text-sm text-muted-foreground">No upcoming activities.</div>
                        <ul v-else class="divide-y">
                            <li v-for="event in upcoming_events" :key="event.uuid">
                                <Link
                                    :href="`/admin/events/${event.uuid}`"
                                    class="flex items-center justify-between px-5 py-3 text-sm transition-colors hover:bg-muted/40"
                                >
                                    <div class="min-w-0">
                                        <p class="truncate font-medium">{{ event.title }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ new Date(event.event_date).toLocaleDateString() }}
                                            <template v-if="event.organization?.name"> · {{ event.organization.name }}</template>
                                        </p>
                                    </div>
                                    <ArrowUpRight class="size-4 shrink-0 text-muted-foreground" />
                                </Link>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
