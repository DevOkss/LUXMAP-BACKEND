<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Plus, Save } from 'lucide-vue-next';
import { ref } from 'vue';

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

interface PenaltyOrg {
    id: number;
    code: string;
    name: string;
    type: string;
    current_amount: number | null;
    effective_at: string | null;
}

const props = defineProps<{
    fees: Fee[];
    can_manage_fees: boolean;
    can_manage_penalties: boolean;
    penalties: PenaltyOrg[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Fees', href: '/admin/fees' },
];

type Mode = 'fees' | 'penalty';
const mode = ref<Mode>('fees');

const amountDrafts = ref<Record<number, string>>(
    Object.fromEntries(props.penalties.map(p => [p.id, p.current_amount !== null ? String(p.current_amount) : '']))
);
const saving = ref<Record<number, boolean>>({});

function savePenalty(org: PenaltyOrg): void {
    const amount = amountDrafts.value[org.id];
    if (amount === undefined || amount === '' || Number.isNaN(Number(amount))) return;

    saving.value = { ...saving.value, [org.id]: true };
    router.post('/admin/fees/penalty', {
        organization_id: org.id,
        amount: Number(amount),
    }, {
        onFinish: () => {
            saving.value = { ...saving.value, [org.id]: false };
        },
    });
}

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

function typeLabel(type: string): string {
    const labels: Record<string, string> = {
        ssc: 'Student Council',
        isc: 'Institute',
        sro: 'Program',
    };
    return labels[type] || type;
}
</script>

<template>
    <Head title="Fees" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="Fees & Penalties" subtitle="Manage membership and service fees, and the per-organization penalty amount.">
                <template #actions>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center rounded-lg border bg-muted/40 p-0.5">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="rounded-md px-4"
                                :class="mode === 'fees' ? 'bg-white text-foreground shadow-sm dark:bg-zinc-800' : 'text-muted-foreground'"
                                @click="mode = 'fees'"
                            >
                                Fees
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="rounded-md px-4"
                                :class="mode === 'penalty' ? 'bg-white text-foreground shadow-sm dark:bg-zinc-800' : 'text-muted-foreground'"
                                @click="mode = 'penalty'"
                            >
                                Penalty
                            </Button>
                        </div>
                        <Link v-if="mode === 'fees' && can_manage_fees" href="/admin/fees/create">
                            <Button class="rounded-xl bg-[#20673A] font-semibold text-white hover:bg-[#027F3B]">
                                <Plus class="h-4 w-4" />
                                New Fee
                            </Button>
                        </Link>
                    </div>
                </template>
            </PageHeader>

            <!-- Fees tab -->
            <Card v-if="mode === 'fees'" class="overflow-hidden">
                <div class="border-b px-5 py-4">
                    <p class="text-sm font-semibold">All Fees ({{ fees.length }})</p>
                </div>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50 text-left">
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Name</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Organization</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Amount</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Term</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Due Date</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Assigned</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Status</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="fee in fees" :key="fee.id" class="border-b transition-colors last:border-0 hover:bg-muted/40">
                                    <td class="px-5 py-3.5 font-medium">{{ fee.name }}</td>
                                    <td class="px-5 py-3.5 text-muted-foreground">{{ fee.organization?.name || 'N/A' }}</td>
                                    <td class="px-5 py-3.5 font-medium">₱{{ fee.amount.toFixed(2) }}</td>
                                    <td class="px-5 py-3.5 text-muted-foreground">{{ fee.term || '—' }}</td>
                                    <td class="px-5 py-3.5 text-muted-foreground">{{ fee.due_date ? new Date(fee.due_date).toLocaleDateString() : '—' }}</td>
                                    <td class="px-5 py-3.5 text-muted-foreground">{{ fee.users_count }}</td>
                                    <td class="px-5 py-3.5">
                                        <span :class="statusColors[fee.status]" class="rounded-full px-2.5 py-1 text-xs font-medium capitalize">{{ fee.status }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                        <Link :href="`/admin/fees/${fee.id}`" class="text-sm font-medium text-primary hover:underline">View</Link>
                                    </td>
                                </tr>
                                <tr v-if="fees.length === 0">
                                    <td colspan="8" class="px-5 py-10 text-center text-muted-foreground">No fees found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Penalty tab -->
            <Card v-else class="overflow-hidden">
                <div class="border-b px-5 py-4">
                    <p class="text-sm font-semibold">Penalty Amount by Organization</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        The amount charged to students who miss a required event. Heads can update the amount for their own organization.
                    </p>
                </div>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50 text-left">
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Organization</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Type</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Current Amount</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Effective Since</th>
                                    <th v-if="can_manage_penalties" class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Set Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="org in penalties" :key="org.id" class="border-b transition-colors last:border-0 hover:bg-muted/40">
                                    <td class="px-5 py-3.5 font-medium">{{ org.name }}</td>
                                    <td class="px-5 py-3.5 text-muted-foreground">{{ typeLabel(org.type) }}</td>
                                    <td class="px-5 py-3.5 font-medium">
                                        ₱{{ org.current_amount !== null ? org.current_amount.toFixed(2) : '0.00' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-muted-foreground">
                                        {{ org.effective_at || 'Not set' }}
                                    </td>
                                    <td v-if="can_manage_penalties" class="px-5 py-3.5">
                                        <form class="flex items-center gap-2" @submit.prevent="savePenalty(org)">
                                            <div class="relative">
                                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">₱</span>
                                                <Input
                                                    v-model="amountDrafts[org.id]"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    placeholder="0.00"
                                                    class="w-32 pl-7"
                                                />
                                            </div>
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                size="sm"
                                                :disabled="saving[org.id]"
                                                class="rounded-lg"
                                            >
                                                <Save class="h-3.5 w-3.5" />
                                                {{ saving[org.id] ? 'Saving…' : 'Save' }}
                                            </Button>
                                        </form>
                                    </td>
                                </tr>
                                <tr v-if="penalties.length === 0">
                                    <td :colspan="can_manage_penalties ? 5 : 4" class="px-5 py-10 text-center text-muted-foreground">No organizations in scope.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
