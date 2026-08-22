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

interface BindingRow {
    id: number
    user: { id: number | null; name: string | null; student_number: string | null; email: string | null } | null
    device_fingerprint: string
    device_meta: Record<string, unknown> | null
    bound_at: string | null
}

const props = defineProps<{
    bindings: { data: BindingRow[]; current_page: number; last_page: number; total: number; per_page: number }
    filters: { q: string | null }
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Device Bindings', href: '/admin/device-bindings' },
]

const meta = {
    current_page: props.bindings.current_page,
    last_page: props.bindings.last_page,
    total: props.bindings.total,
    per_page: props.bindings.per_page ?? 20,
}

const search = ref(props.filters.q || '')
const target = ref<BindingRow | null>(null)
const reason = ref('')

function applySearch() {
    router.get('/admin/device-bindings', { q: search.value || undefined }, { preserveState: true, replace: true })
}

let searchTimer: ReturnType<typeof setTimeout> | null = null
function debouncedSearch() {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(applySearch, 400)
}

function openUnbind(row: BindingRow) {
    target.value = row
    reason.value = ''
}

function confirmUnbind() {
    if (!target.value) return
    router.delete(
        `/admin/device-bindings/${target.value.id}`,
        {
            data: { reason: reason.value },
            preserveScroll: true,
            onSuccess: () => {
                target.value = null
                reason.value = ''
            },
        },
    )
}

function shortFingerprint(fingerprint: string): string {
    if (fingerprint.length <= 16) return fingerprint
    return `${fingerprint.slice(0, 8)}…${fingerprint.slice(-6)}`
}

function deviceLabel(meta: Record<string, unknown> | null): string {
    if (!meta || !meta.platform) return 'Unknown device'
    return `${meta.platform}${meta.screen ? ' · ' + meta.screen : ''}`
}

function formatDate(value: string | null): string {
    return value ? new Date(value.replace(' ', 'T')).toLocaleString() : '—'
}
</script>

<template>
    <Head title="Device Bindings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="Device Bindings" subtitle="Students bound to a single ATTEND device with face verification." />

            <Card class="overflow-hidden">
                <div class="flex flex-col gap-3 border-b px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm font-semibold">Bound Devices ({{ bindings.total }})</p>
                    <div class="w-full sm:w-72">
                        <Input
                            v-model="search"
                            placeholder="Search by name or ID number…"
                            @input="debouncedSearch"
                        />
                    </div>
                </div>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50 text-left">
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Student</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Device</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Fingerprint</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Bound At</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in bindings.data" :key="row.id" class="border-b transition-colors last:border-0 hover:bg-muted/40">
                                    <td class="px-5 py-3.5">
                                        <div class="font-medium">{{ row.user?.name || '—' }}</div>
                                        <div class="text-xs text-muted-foreground">
                                            {{ row.user?.student_number || '—' }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 text-muted-foreground">{{ deviceLabel(row.device_meta) }}</td>
                                    <td class="px-5 py-3.5">
                                        <code class="rounded bg-muted px-1.5 py-0.5 text-xs">{{ shortFingerprint(row.device_fingerprint) }}</code>
                                    </td>
                                    <td class="px-5 py-3.5 text-muted-foreground">{{ formatDate(row.bound_at) }}</td>
                                    <td class="px-5 py-3.5">
                                        <Button variant="outline" size="sm" class="text-red-600" @click="openUnbind(row)">
                                            Unbind
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="bindings.data.length === 0">
                                    <td colspan="5" class="px-5 py-10 text-center text-muted-foreground">No device bindings found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination :meta="meta" :filters="{ q: filters.q }" />
                </CardContent>
            </Card>

            <Dialog v-if="target" :open="!!target" @update:open="(v: boolean) => { if (!v) target = null }">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Unbind device</DialogTitle>
                        <DialogDescription>
                            Remove the device binding for
                            <span class="font-medium">{{ target.user?.name }}</span>?
                            The face profile on the server is kept so the student can re-bind without re-enrolling.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium">Reason (required)</label>
                        <Input v-model="reason" placeholder="e.g. Lost phone, device transfer dispute" />
                    </div>
                    <DialogFooter>
                        <Button variant="outline" @click="target = null">Cancel</Button>
                        <Button variant="destructive" :disabled="!reason.trim()" @click="confirmUnbind">Unbind</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>