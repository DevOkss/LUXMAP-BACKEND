<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { type BreadcrumbItem } from '@/types'
import { Head, Link, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { UserPlus } from 'lucide-vue-next'
import { ref } from 'vue'

interface Assignment {
    organization_id: number
    organization_code: string
    organization_name: string
    role: string | null
}

interface HeadUser {
    id: number
    name: string
    email: string
    student_number: string | null
    is_enrolled: boolean
    assignments: Assignment[]
}

const props = defineProps<{
    heads: { data: HeadUser[]; meta: { current_page: number; last_page: number; total: number } }
    filters: { search: string | null; role: string | null }
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Heads', href: '/admin/heads' },
]

const roleFilter = ref(props.filters.role || '')

function doFilter() {
    router.get('/admin/heads', {
        role: roleFilter.value || undefined,
    }, { preserveState: true })
}

const roleLabel = (role: string | null): string => {
    if (!role) return '—'
    if (role === 'institute_head') return 'ISC Adviser'
    if (role === 'sro_head') return 'SRO Adviser'
    return role.replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}
</script>

<template>
    <Head title="Heads" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="Heads" subtitle="Manage dedicated head accounts for your organizations.">
                <template #actions>
                    <Link href="/admin/heads/create">
                        <Button>
                            <UserPlus class="size-4" />
                            Create Head
                        </Button>
                    </Link>
                </template>
            </PageHeader>

            <Card class="overflow-hidden">
                <div class="flex flex-col gap-3 border-b px-5 py-4 md:flex-row md:items-center md:justify-between">
                    <p class="text-sm font-semibold">Head Accounts ({{ heads.total }})</p>
                    <div class="flex gap-2">
                        <select
                            v-model="roleFilter"
                            @change="doFilter"
                            class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">All</option>
                            <option value="ssc_head">SSC Head</option>
                            <option value="institute_head">ISC Adviser</option>
                            <option value="sro_head">SRO Adviser</option>
                        </select>
                    </div>
                </div>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50 text-left">
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Name</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Role</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Scope</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="head in heads.data" :key="head.id" class="border-b transition-colors last:border-0 hover:bg-muted/40">
                                    <td class="px-5 py-3.5">
                                        <div class="font-medium">{{ head.name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ head.email }}</div>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span v-if="head.assignments.length" class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium capitalize text-primary">
                                            {{ roleLabel(head.assignments[0].role) }}
                                        </span>
                                        <span v-else class="text-xs text-muted-foreground">None</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-muted-foreground">
                                        <span v-if="head.assignments.length">{{ head.assignments[0].organization_name }}</span>
                                        <span v-else>—</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                        <Link :href="`/admin/heads/${head.id}`" class="text-sm font-medium text-primary hover:underline">View</Link>
                                    </td>
                                </tr>
                                <tr v-if="heads.data.length === 0">
                                    <td colspan="4" class="px-5 py-10 text-center text-muted-foreground">No heads found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="heads.last_page > 1" class="flex flex-col gap-3 border-t px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-muted-foreground">Page {{ heads.current_page }} of {{ heads.last_page }}</p>

                        <div class="flex gap-2">
                            <Link v-if="heads.current_page > 1" :href="`/admin/heads?page=${heads.current_page - 1}`" class="rounded-lg border px-3 py-1.5 text-sm transition-colors hover:bg-accent">Previous</Link>
                            <Link v-if="heads.current_page < heads.last_page" :href="`/admin/heads?page=${heads.current_page + 1}`" class="rounded-lg border px-3 py-1.5 text-sm transition-colors hover:bg-accent">Next</Link>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
