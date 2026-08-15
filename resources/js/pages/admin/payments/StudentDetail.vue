<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { type BreadcrumbItem } from '@/types'
import { Head, useForm } from '@inertiajs/vue3'
import { Card, CardContent } from '@/components/ui/card'

interface FeeObligation {
    id: number
    name: string
    description?: string | null
    organization: { id: number; name: string } | null
    academic_term?: string | null
    amount: number
    due_date?: string | null
    obligation_key?: string
}

interface PenaltyObligation {
    organization_id?: number
    event_id: number
    event: { id: number; title: string; org?: { id: number; name: string } | null; organization?: { id: number; name: string } | null }
    absences?: number
    amount: number
    academic_term?: string | null
    obligation_key?: string
}

interface PaymentAccount {
    id: number
    account_name: string
    account_provider?: string | null
    account_number: string
    qr_code_image_url?: string | null
    is_active: boolean
}

interface OrgOption {
    id: number
    name: string
    type?: string | null
    payment_account: PaymentAccount | null
}

const props = defineProps<{
    student: {
        id: number
        name: string
        student_number: string | null
        email?: string | null
        course_program?: string | null
        year_level?: number
    }
    fees: FeeObligation[]
    penalties: PenaltyObligation[]
    organizations: OrgOption[]
    term: string | null
    can_process: boolean
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payments', href: '/admin/payments' },
    { title: 'Student', href: '/admin/payments' },
]

// Record Cash
const cashForm = useForm({
    user_id: props.student.id,
    organization_id: props.organizations[0]?.id ?? 0,
    fee_ids: [] as number[],
    event_ids: [] as number[],
    notes: '',
})

// Exempt
const exemptForm = useForm({
    user_id: props.student.id,
    organization_id: props.organizations[0]?.id ?? 0,
    fee_ids: [] as number[],
    event_ids: [] as number[],
    reason: '',
})

const totals = () => {
    const fees = props.fees.reduce((s, f) => s + (f.organization?.id === cashForm.organization_id ? f.amount : 0), 0)
    const penalties = props.penalties
        .filter((p) => (p.event?.organization?.id ?? p.event?.org?.id) === cashForm.organization_id)
        .reduce((s, p) => s + p.amount, 0)
    return fees + penalties
}

const feeAccount = () => props.organizations.find((o) => o.id === cashForm.organization_id)?.payment_account || null

function submitCash() {
    cashForm.post('/admin/payments/cash', { preserveScroll: true })
}

function submitExempt() {
    exemptForm.organization_id = cashForm.organization_id
    exemptForm.fee_ids = [...cashForm.fee_ids]
    exemptForm.event_ids = [...cashForm.event_ids]
    exemptForm.post('/admin/payments/exempt', { preserveScroll: true })
}
</script>

<template>
    <Head title="Student Obligations" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader
                :title="`${student.name}`"
                :subtitle="`${student.student_number || ''}${student.course_program ? ' · ' + student.course_program : ''}`"
            />

            <Card>
                <div class="border-b px-5 py-4">
                    <p class="text-sm font-semibold">Outstanding Obligations</p>
                    <p class="text-xs text-muted-foreground">Amounts are computed dynamically by SOMS. Term: {{ term || 'No active term' }}</p>
                </div>
                <CardContent class="p-5 space-y-6">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Organization</label>
                        <select v-model="cashForm.organization_id" class="flex h-9 w-full max-w-md rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm outline-none focus-visible:ring-1 focus-visible:ring-ring">
                            <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                        </select>
                    </div>

                    <div v-if="fees.length" class="space-y-2">
                        <p class="text-sm font-medium">Fees</p>
                        <label v-for="fee in fees.filter((f) => f.organization?.id === cashForm.organization_id)" :key="fee.id" class="flex items-center justify-between gap-3 rounded-lg border px-4 py-3 text-sm">
                            <span class="flex items-center gap-3">
                                <input v-model="cashForm.fee_ids" type="checkbox" :value="fee.id" class="size-4 accent-primary" />
                                <span>
                                    <span class="font-medium">{{ fee.name }}</span>
                                    <span class="ml-2 text-muted-foreground">₱{{ fee.amount.toFixed(2) }}</span>
                                </span>
                            </span>
                        </label>
                    </div>

                    <div v-if="penalties.length" class="space-y-2">
                        <p class="text-sm font-medium">Penalties</p>
                        <label v-for="penalty in penalties.filter((p) => (p.event?.organization?.id ?? p.event?.org?.id) === cashForm.organization_id)" :key="penalty.event_id" class="flex items-center justify-between gap-3 rounded-lg border px-4 py-3 text-sm">
                            <span class="flex items-center gap-3">
                                <input v-model="cashForm.event_ids" type="checkbox" :value="penalty.event_id" class="size-4 accent-primary" />
                                <span>
                                    <span class="font-medium">{{ penalty.event.title }}</span>
                                    <span v-if="penalty.absences" class="ml-2 text-muted-foreground">{{ penalty.absences }} missing QR{{ penalty.absences > 1 ? 's' : '' }}</span>
                                    <span class="ml-2 text-muted-foreground">₱{{ penalty.amount.toFixed(2) }}</span>
                                </span>
                            </span>
                        </label>
                    </div>

                    <p v-if="!fees.length && !penalties.length" class="text-sm text-muted-foreground">This student has no outstanding obligations in your scope.</p>

                    <div class="flex items-center justify-between rounded-lg bg-muted/50 px-4 py-3">
                        <span class="text-sm font-medium">Selected total</span>
                        <span class="text-lg font-bold">₱{{ totals().toFixed(2) }}</span>
                    </div>

                    <template v-if="can_process">
                        <div class="flex flex-wrap gap-3">
                            <button
                                type="button"
                                :disabled="cashForm.processing || totals() <= 0"
                                class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90 disabled:opacity-50"
                                @click="submitCash"
                            >
                                {{ cashForm.processing ? 'Recording...' : 'Record Cash Payment' }}
                            </button>
                            <button
                                type="button"
                                class="rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-muted disabled:opacity-50"
                                :disabled="exemptForm.processing || totals() <= 0"
                                @click="submitExempt"
                            >
                                {{ exemptForm.processing ? 'Exempting...' : 'Exempt / Waive' }}
                            </button>
                        </div>

                        <div v-if="cashForm.errors.items || exemptForm.errors.items" class="rounded-md bg-destructive/10 px-4 py-3 text-sm text-destructive">
                            {{ cashForm.errors.items || exemptForm.errors.items }}
                        </div>

                        <template v-if="feeAccount">
                            <div class="rounded-lg border border-dashed p-4 text-sm">
                                <p class="font-medium">{{ feeAccount().account_name }}</p>
                                <p class="text-muted-foreground">{{ feeAccount().account_provider || 'Your' }} account · {{ feeAccount().account_number }}</p>
                            </div>
                        </template>

                        <label v-if="exemptForm.errors?.reason" class="text-sm text-destructive">{{ exemptForm.errors.reason }}</label>
                        <input
                            v-model="exemptForm.reason"
                            placeholder="Exemption reason (required to waive)"
                            class="flex h-9 w-full max-w-md rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        />
                    </template>
                    <div v-else class="rounded-md bg-muted px-4 py-3 text-sm text-muted-foreground">
                        You are viewing this student's obligations. Cash payments, exemptions, and verifications are handled by the organization's officers.
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>