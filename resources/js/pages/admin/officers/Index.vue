<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { type BreadcrumbItem } from '@/types'
import { Head, Link, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { UserPlus } from 'lucide-vue-next'

interface Assignment {
    organization_id: number
    organization_code: string
    organization_name: string
    role: string | null
    position: string | null
    academic_term_id: number | null
    academic_term: string | null
}

interface Officer {
    id: number
    name: string
    email: string
    student_number: string | null
    assignments: Assignment[]
}

interface AcademicTermOption {
    id: number
    name: string
    is_active: boolean
}

const props = defineProps<{
    officers: { data: Officer[]; meta: { current_page: number; last_page: number; total: number } }
    academic_terms: AcademicTermOption[]
    selected_term: number | null
    filters: { academic_term_id: number | null }
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Officers', href: '/admin/officers' },
]

function changeTerm(termId: number | null) {
    router.get('/admin/officers', { academic_term_id: termId || undefined }, { preserveState: false, preserveScroll: true })
}

function revoke(officer: Officer, assignment: Assignment) {
    if (!confirm(`Revoke ${officer.name} from ${assignment.organization_name}${assignment.academic_term ? ' for ' + assignment.academic_term : ''}?`)) return
    router.delete('/admin/officers', {
        data: { user_id: officer.id, organization_id: assignment.organization_id, academic_term_id: assignment.academic_term_id },
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Officers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="Officers" subtitle="Manage officer assignments across organizations.">
                <template #actions>
                    <div class="flex items-center gap-2">
                        <select
                            :value="props.selected_term"
                            aria-label="Academic term"
                            class="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            @change="(e: Event) => changeTerm(Number((e.target as HTMLSelectElement).value) || null)"
                        >
                            <option v-for="term in props.academic_terms" :key="term.id" :value="term.id">
                                {{ term.name }}{{ term.is_active ? ' (current)' : '' }}
                            </option>
                        </select>
                        <Link :href="`/admin/officers/assign?academic_term_id=${props.selected_term ?? ''}`">
                            <Button>
                                <UserPlus class="size-4" />
                                Assign Officer
                            </Button>
                        </Link>
                    </div>
                </template>
            </PageHeader>

            <Card class="overflow-hidden">
                <div class="border-b px-5 py-4">
                    <p class="text-sm font-semibold">Officer Assignments ({{ officers.total }})</p>
                </div>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50 text-left">
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Name</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Student #</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Assignments</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-muted-foreground"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="officer in officers.data" :key="officer.id" class="border-b transition-colors last:border-0 hover:bg-muted/40">
                                    <td class="px-5 py-3.5">
                                        <div class="font-medium">{{ officer.name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ officer.email }}</div>
                                    </td>
                                    <td class="px-5 py-3.5 text-muted-foreground">{{ officer.student_number || '—' }}</td>
                                    <td class="px-5 py-3.5">
                                        <div v-if="officer.assignments.length" class="flex flex-wrap gap-1">
                                            <span v-for="assignment in officer.assignments" :key="`${assignment.organization_id}-${assignment.academic_term_id ?? 'null'}`" class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                                {{ assignment.organization_code }} · {{ assignment.position || assignment.role?.replaceAll('_', ' ') }}
                                                <span v-if="assignment.academic_term" class="ml-1 text-primary">({{ assignment.academic_term }})</span>
                                                <span v-else class="ml-1 text-amber-600">(legacy)</span>
                                            </span>
                                        </div>
                                        <span v-else class="text-xs text-muted-foreground">None</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                        <button
                                            v-for="assignment in officer.assignments"
                                            :key="assignment.organization_id"
                                            @click="revoke(officer, assignment)"
                                            class="ml-3 text-sm text-destructive hover:underline"
                                        >
                                            Revoke ({{ assignment.organization_code }})
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="officers.data.length === 0">
                                    <td colspan="4" class="px-5 py-10 text-center text-muted-foreground">No officers found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="officers.last_page > 1" class="flex flex-col gap-3 border-t px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-muted-foreground">Page {{ officers.current_page }} of {{ officers.last_page }}</p>

                        <div class="flex gap-2">
                            <Link v-if="officers.current_page > 1" :href="`/admin/officers?page=${officers.current_page - 1}`" class="rounded-lg border px-3 py-1.5 text-sm transition-colors hover:bg-accent">Previous</Link>
                            <Link v-if="officers.current_page < officers.last_page" :href="`/admin/officers?page=${officers.current_page + 1}`" class="rounded-lg border px-3 py-1.5 text-sm transition-colors hover:bg-accent">Next</Link>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
