<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { type BreadcrumbItem } from '@/types'
import { Head } from '@inertiajs/vue3'
import { Card, CardContent } from '@/components/ui/card'

interface Assignment {
    organization_id: number
    organization_code: string
    organization_name: string
    role: string | null
    assigned_at: string | null
}

interface Adviser {
    id: number
    name: string
    email: string
    assignments: Assignment[]
}

defineProps<{
    advisers: Adviser[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Advisers', href: '/admin/advisers' },
]

const roleLabel = (role: string | null): string => {
    if (role === 'institute_head') return 'ISC Adviser'
    if (role === 'sro_head') return 'SRO Adviser'
    return role?.replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase()) || '—'
}
</script>

<template>
    <Head title="Advisers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="Advisers" subtitle="View the ISC and SRO advisers across your organizations." />

            <Card class="overflow-hidden">
                <div class="border-b px-5 py-4">
                    <p class="text-sm font-semibold">Advisers ({{ advisers.length }})</p>
                </div>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50 text-left">
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Name</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Role</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Scope</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Assigned</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="adviser in advisers" :key="adviser.id" class="border-b transition-colors last:border-0 hover:bg-muted/40">
                                    <td class="px-5 py-3.5">
                                        <div class="font-medium">{{ adviser.name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ adviser.email }}</div>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span v-if="adviser.assignments.length" class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium capitalize text-primary">
                                            {{ roleLabel(adviser.assignments[0].role) }}
                                        </span>
                                        <span v-else class="text-xs text-muted-foreground">None</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-muted-foreground">
                                        <span v-if="adviser.assignments.length">{{ adviser.assignments[0].organization_name }}</span>
                                        <span v-else>—</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-muted-foreground">
                                        {{ adviser.assignments[0]?.assigned_at || '—' }}
                                    </td>
                                </tr>
                                <tr v-if="advisers.length === 0">
                                    <td colspan="4" class="px-5 py-10 text-center text-muted-foreground">No advisers found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
