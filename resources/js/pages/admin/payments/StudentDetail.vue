<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { type BreadcrumbItem } from '@/types'
import { Head, useForm } from '@inertiajs/vue3'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { computed, ref } from 'vue'

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

// Preview modal state — requirement: receipt preview before saving
const showPreview = ref(false)
const previewType = ref<'cash' | 'exempt'>('cash')

const selectedFees = computed(() =>
    props.fees.filter((f) => f.organization?.id === cashForm.organization_id && cashForm.fee_ids.includes(f.id)),
)
const selectedPenalties = computed(() =>
    props.penalties.filter((p) => (p.event?.organization?.id ?? p.event?.org?.id) === cashForm.organization_id && cashForm.event_ids.includes(p.event_id)),
)
const previewItems = computed(() => {
    const feeRows = selectedFees.value.map((f) => ({ label: f.name, amount: f.amount, type: 'Fee' }))
    const penRows = selectedPenalties.value.map((p) => ({ label: p.event.title, amount: p.amount, type: 'Penalty' }))
    return [...feeRows, ...penRows]
})
const previewTotal = computed(() => previewItems.value.reduce((s, r) => s + r.amount, 0))
const previewOrg = computed(() => props.organizations.find((o) => o.id === cashForm.organization_id) || null)
const previewDate = computed(() => new Date().toLocaleString())

const totals = () => {
    const fees = props.fees
        .filter((f) => f.organization?.id === cashForm.organization_id && cashForm.fee_ids.includes(f.id))
        .reduce((s, f) => s + f.amount, 0)
    const penalties = props.penalties
        .filter((p) => (p.event?.organization?.id ?? p.event?.org?.id) === cashForm.organization_id && cashForm.event_ids.includes(p.event_id))
        .reduce((s, p) => s + p.amount, 0)
    return fees + penalties
}

const feeAccount = computed(() => props.organizations.find((o) => o.id === cashForm.organization_id)?.payment_account || null)

function openCashPreview() {
    if (totals() <= 0) return
    previewType.value = 'cash'
    showPreview.value = true
}
function openExemptPreview() {
    if (totals() <= 0) return
    if (!exemptForm.reason.trim()) {
        // Require reason to show preview for exempt
        exemptForm.setError('reason', 'Exemption reason is required.')
        return
    }
    previewType.value = 'exempt'
    showPreview.value = true
}

function submitCash() {
    showPreview.value = false
    cashForm.post('/admin/payments/cash', { preserveScroll: true })
}

function submitExempt() {
    showPreview.value = false
    exemptForm.organization_id = cashForm.organization_id
    exemptForm.fee_ids = [...cashForm.fee_ids]
    exemptForm.event_ids = [...cashForm.event_ids]
    exemptForm.post('/admin/payments/exempt', { preserveScroll: true })
}

function confirmPreview() {
    if (previewType.value === 'cash') {
        submitCash()
    } else {
        submitExempt()
    }
}

// Amount in words for preview (mirrors Show.vue)
const TENS: string[] = ['', 'Ten', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety']
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
]
function twoDigits(n: number): string {
    if (n < 20) return ONES[n]
    return `${TENS[Math.floor(n / 10)]}${n % 10 ? ' ' + ONES[n % 10] : ''}`
}
function threeDigits(n: number): string {
    const hundreds = Math.floor(n / 100)
    const rest = n % 100
    let out = ''
    if (hundreds) out += `${ONES[hundreds]} Hundred`
    if (rest) out += `${out ? ' ' : ''}${twoDigits(rest)}`
    return out
}
function numberToWords(value: number): string {
    const amount = Math.round(value * 100)
    const whole = Math.floor(amount / 100)
    const cents = amount % 100
    let out = ''
    const billion = Math.floor(whole / 1000000000)
    const million = Math.floor((whole % 1000000000) / 1000000)
    const thousand = Math.floor((whole % 1000000) / 1000)
    const remainder = whole % 1000
    if (billion) out += `${threeDigits(billion)} Billion `
    if (million) out += `${threeDigits(million)} Million `
    if (thousand) out += `${threeDigits(thousand)} Thousand `
    if (remainder) out += threeDigits(remainder)
    out = out.trim() || 'Zero'
    const pesoWord = whole === 1 ? 'Peso' : 'Pesos'
    out += ` ${pesoWord}`
    if (cents > 0) {
        const centWord = cents === 1 ? 'Centavo' : 'Centavos'
        out += ` and ${twoDigits(cents)} ${centWord}`
    }
    return out
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
                                @click="openCashPreview"
                            >
                                Record Cash Payment
                            </button>
                            <button
                                type="button"
                                class="rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-muted disabled:opacity-50"
                                :disabled="exemptForm.processing || totals() <= 0"
                                @click="openExemptPreview"
                            >
                                {{ exemptForm.processing ? 'Exempting...' : 'Exempt / Waive' }}
                            </button>
                        </div>

                        <div v-if="cashForm.errors.items || exemptForm.errors.items" class="rounded-md bg-destructive/10 px-4 py-3 text-sm text-destructive">
                            {{ cashForm.errors.items || exemptForm.errors.items }}
                        </div>

                        <template v-if="feeAccount">
                            <div class="rounded-lg border border-dashed p-4 text-sm">
                                <p class="font-medium">{{ feeAccount.account_name }}</p>
                                <p class="text-muted-foreground">{{ feeAccount.account_provider || 'Your' }} account · {{ feeAccount.account_number }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">Walk-in cash does not require an online payment account — this account is for cashless submissions.</p>
                            </div>
                        </template>
                        <template v-else>
                            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-900 dark:bg-amber-900/20">
                                <p class="font-medium text-amber-800 dark:text-amber-200">No online payment account for this organization</p>
                                <p class="text-amber-700 dark:text-amber-300">Walk-in cash payments are still available — officers can record cash for their scoped organization without an online recipient account.</p>
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

            <!-- Receipt Preview Modal — required before saving -->
            <Dialog :open="showPreview" @update:open="(v: boolean) => (showPreview = v)">
                <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-[620px]">
                    <DialogHeader>
                        <DialogTitle>Receipt Preview</DialogTitle>
                        <DialogDescription>
                            Review the receipt details before confirming. The payment will not be saved until you confirm.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="mx-auto w-full max-w-[560px] rounded-lg border border-dashed p-6 shadow-sm">
                        <div class="flex items-start justify-between border-b pb-4">
                            <div>
                                <p class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Official Receipt Preview</p>
                                <p class="mt-1 font-mono text-sm font-semibold text-muted-foreground">Will be generated on confirmation</p>
                                <p class="text-xs text-muted-foreground">Preview · {{ previewDate }}</p>
                            </div>
                            <div class="text-right text-xs text-muted-foreground">
                                <p>Date</p>
                                <p class="font-medium text-foreground">{{ previewDate }}</p>
                                <p v-if="term" class="mt-1">Term</p>
                                <p v-if="term" class="font-medium text-foreground">{{ term }}</p>
                            </div>
                        </div>

                        <div class="border-b py-4">
                            <div class="flex items-start justify-between gap-6">
                                <div>
                                    <p class="text-xs uppercase tracking-wider text-muted-foreground">Payer</p>
                                    <p class="font-medium">{{ student.name }}</p>
                                    <p v-if="student.student_number" class="text-xs text-muted-foreground">{{ student.student_number }}</p>
                                    <p v-if="student.course_program" class="text-xs text-muted-foreground">{{ student.course_program }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs uppercase tracking-wider text-muted-foreground">Organization</p>
                                    <p class="font-medium">{{ previewOrg?.name || 'N/A' }}</p>
                                    <p class="text-xs text-muted-foreground">{{ previewType === 'exempt' ? 'Exemption' : 'Cash' }}</p>
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
                                    <tr v-for="(item, idx) in previewItems" :key="idx" class="border-b last:border-0">
                                        <td class="py-2.5">
                                            <p class="font-medium">{{ item.label }}</p>
                                            <p class="text-xs text-muted-foreground">{{ item.type }}</p>
                                        </td>
                                        <td class="py-2.5 text-right font-mono">{{ previewType === 'exempt' ? '₱0.00' : `₱${item.amount.toFixed(2)}` }}</td>
                                    </tr>
                                    <tr v-if="previewItems.length === 0">
                                        <td colspan="2" class="py-4 text-center text-sm text-muted-foreground">No items selected.</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="text-sm font-semibold">
                                        <td class="pt-3 uppercase tracking-wider text-muted-foreground">Total ({{ previewItems.length }} {{ previewItems.length === 1 ? 'item' : 'items' }})</td>
                                        <td class="pt-3 text-right font-mono text-base">{{ previewType === 'exempt' ? '₱0.00' : `₱${previewTotal.toFixed(2)}` }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                            <p class="mt-4 text-xs italic text-muted-foreground">{{ numberToWords(previewType === 'exempt' ? 0 : previewTotal) }} Only</p>
                            <p v-if="previewType === 'cash'" class="mt-2 text-xs text-muted-foreground">Processed by officer on {{ previewDate }} · One receipt will be issued for all selected fees.</p>
                            <p v-else class="mt-2 text-xs text-muted-foreground">Exemption reason: {{ exemptForm.reason || '—' }}</p>
                        </div>

                        <div v-if="cashForm.notes || exemptForm.reason" class="rounded-md bg-muted p-3 text-sm">
                            <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Notes</p>
                            <p class="mt-1 whitespace-pre-wrap text-muted-foreground">{{ previewType === 'exempt' ? exemptForm.reason : cashForm.notes || '—' }}</p>
                        </div>
                    </div>

                    <DialogFooter class="mt-4">
                        <Button variant="outline" @click="showPreview = false">Cancel</Button>
                        <Button :disabled="previewItems.length === 0" @click="confirmPreview">
                            {{ previewType === 'exempt' ? 'Confirm Exemption' : 'Confirm Payment' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
