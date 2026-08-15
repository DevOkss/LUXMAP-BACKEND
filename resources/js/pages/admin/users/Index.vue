<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { type BreadcrumbItem } from '@/types'
import { Head, Link, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Search } from 'lucide-vue-next'
import Pagination from '@/components/Pagination.vue'
import { ref, computed } from 'vue'

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
    email_verified_at: string | null
    primary_role: string
    organizations: OrgAssignment[]
}

const props = defineProps<{
    users: { data: User[]; current_page: number; last_page: number; total: number; per_page: number }
    filters: { search: string | null; role: string | null }
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users', href: '/admin/users' },
]

const search = ref(props.filters.search || '')
const role = ref(props.filters.role || '')

const meta = computed(() => ({
    current_page: props.users.current_page,
    last_page: props.users.last_page,
    total: props.users.total,
    per_page: props.users.per_page ?? 10,
}))

const activeFilters = computed(() => ({
    search: search.value || undefined,
    role: role.value || undefined,
}))

function doSearch() {
    router.get('/admin/users', {
        search: search.value || undefined,
        role: role.value || undefined,
        per_page: meta.value.per_page,
    }, { preserveState: true })
}
</script>

<template>
    <Head title="Users" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="Users" subtitle="Browse all registered student accounts." />

            <Card class="overflow-hidden">
                <div class="flex flex-col gap-3 border-b px-5 py-4 md:flex-row md:items-center md:justify-between">
                    <p class="text-sm font-semibold">All Users ({{ users.total }})</p>
                    <div class="flex flex-wrap gap-2">
                        <select
                            v-model="role"
                            @change="doSearch"
                            class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="">Students</option>
                            <option value="heads">Heads</option>
                            <option value="officers">Officers</option>
                        </select>
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="search"
                                @keyup.enter="doSearch"
                                placeholder="Search by name, email, or ID..."
                                class="w-full sm:w-64 pl-8"
                            />
                        </div>
                        <Button @click="doSearch">Search</Button>
                    </div>
                </div>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50 text-left">
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Name</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Student #</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Role</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Assignments</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Status</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="user in users.data" :key="user.id" class="border-b transition-colors last:border-0 hover:bg-muted/40">
                                    <td class="px-5 py-3.5">
                                        <div class="font-medium">{{ user.name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ user.email }}</div>
                                    </td>
                                    <td class="px-5 py-3.5 text-muted-foreground">{{ user.student_number || '—' }}</td>
                                    <td class="px-5 py-3.5">
                                        <span class="rounded-full bg-secondary px-2.5 py-1 text-xs font-medium capitalize text-secondary-foreground">{{ user.primary_role.replaceAll('_', ' ') }}</span>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div v-if="user.organizations.length" class="flex flex-wrap gap-1">
                                            <span v-for="org in user.organizations" :key="org.id" class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                                {{ org.code }} · {{ org.role?.replaceAll('_', ' ') }}
                                            </span>
                                        </div>
                                        <span v-else class="text-xs text-muted-foreground">None</span>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span :class="user.is_enrolled ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200'" class="rounded-full px-2.5 py-1 text-xs font-medium">
                                            {{ user.is_enrolled ? 'Enrolled' : 'Not Enrolled' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                        <Link :href="`/admin/users/${user.id}`" class="text-sm font-medium text-primary hover:underline">View</Link>
                                    </td>
                                </tr>
                                <tr v-if="users.data.length === 0">
                                    <td colspan="6" class="px-5 py-10 text-center text-muted-foreground">No users found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination :meta="meta" :filters="activeFilters" />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
