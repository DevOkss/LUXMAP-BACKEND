<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { type BreadcrumbItem } from '@/types'
import { Head, Link, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Pencil, Trash2 } from 'lucide-vue-next'

interface Assignment {
    organization_id: number
    organization_code: string
    organization_name: string
    role: string | null
    position: string | null
    assigned_at: string | null
}

interface HeadUser {
    id: number
    name: string
    email: string
    is_enrolled: boolean
    assignments: Assignment[]
}

const props = defineProps<{
    head: HeadUser
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Heads', href: '/admin/heads' },
    { title: props.head.name, href: `/admin/heads/${props.head.id}` },
]

const roleLabel = (role: string | null): string => {
    if (!role) return '—'
    if (role === 'institute_head') return 'ISC Adviser'
    if (role === 'sro_head') return 'SRO Adviser'
    return role.replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function destroy() {
    if (confirm(`Delete head account "${props.head.name}"? This cannot be undone.`)) {
        router.delete(`/admin/heads/${props.head.id}`)
    }
}
</script>

<template>
    <Head :title="head.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader :title="head.name" :subtitle="head.email">
                <template #actions>
                    <div class="flex items-center gap-2">
                        <Link :href="`/admin/heads/${head.id}/edit`">
                            <Button variant="outline">
                                <Pencil class="size-4" />
                                Edit
                            </Button>
                        </Link>
                        <Button variant="destructive" @click="destroy">
                            <Trash2 class="size-4" />
                            Delete
                        </Button>
                    </div>
                </template>
            </PageHeader>

            <Card class="overflow-hidden">
                <CardHeader class="border-b">
                    <CardTitle>Head Assignment</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="head.assignments.length" class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50 text-left">
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Role</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Organization</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Assigned At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="assignment in head.assignments" :key="assignment.organization_id" class="border-b transition-colors last:border-0 hover:bg-muted/40">
                                    <td class="px-5 py-3.5">
                                        <span class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium capitalize text-primary">
                                            {{ roleLabel(assignment.role) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div class="font-medium">{{ assignment.organization_name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ assignment.organization_code }}</div>
                                    </td>
                                    <td class="px-5 py-3.5 text-muted-foreground">{{ assignment.assigned_at }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="px-5 py-10 text-center text-sm text-muted-foreground">No head assignment.</p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
