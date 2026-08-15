<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { type BreadcrumbItem } from '@/types'
import { Head } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

interface OrgAssignment {
    id: number
    code: string
    name: string
    role: string | null
}

interface User {
    id: number
    name: string
    email: string
    student_number: string | null
    is_enrolled: boolean
    created_at: string | null
    email_verified_at: string | null
    organizations: OrgAssignment[]
}

const props = defineProps<{
    user: User
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users', href: '/admin/users' },
    { title: props.user.name, href: `/admin/users/${props.user.id}` },
]
</script>

<template>
    <Head :title="user.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader :title="user.name" :subtitle="user.email">
                <template #actions>
                    <span :class="user.is_enrolled ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200'" class="rounded-full px-3 py-1 text-xs font-medium">
                        {{ user.is_enrolled ? 'Enrolled' : 'Not Enrolled' }}
                    </span>
                </template>
            </PageHeader>

            <Card class="overflow-hidden">
                <CardHeader class="border-b">
                    <CardTitle>Profile</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm text-muted-foreground">Email</dt>
                            <dd class="font-medium">{{ user.email }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted-foreground">Student Number</dt>
                            <dd class="font-medium">{{ user.student_number || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted-foreground">Email Verified</dt>
                            <dd class="font-medium">{{ user.email_verified_at ? 'Yes' : 'No' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted-foreground">Created</dt>
                            <dd class="font-medium">{{ user.created_at ? new Date(user.created_at).toLocaleDateString() : '—' }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <Card class="overflow-hidden">
                <CardHeader class="border-b">
                    <CardTitle>Organization Assignments</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/50 text-left">
                                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Organization</th>
                                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Code</th>
                                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="org in user.organizations" :key="org.id" class="border-b transition-colors last:border-0 hover:bg-muted/40">
                                <td class="px-5 py-3.5">{{ org.name }}</td>
                                <td class="px-5 py-3.5 text-muted-foreground">{{ org.code }}</td>
                                <td class="px-5 py-3.5 capitalize">{{ org.role?.replaceAll('_', ' ') || '—' }}</td>
                            </tr>
                            <tr v-if="user.organizations.length === 0">
                                <td colspan="3" class="px-5 py-10 text-center text-muted-foreground">No assignments yet.</td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
