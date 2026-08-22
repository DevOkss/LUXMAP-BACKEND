<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { type BreadcrumbItem } from '@/types'
import { Head, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { ref } from 'vue'

interface AcademicTermRow {
    id: number
    academic_year: string
    semester: string
    start_date: string | null
    end_date: string | null
    is_active: boolean
    enrollments_count: number
}

defineProps<{
    terms: AcademicTermRow[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Academic Terms', href: '/admin/academic-terms' },
]

const academic_year = ref('')
const semester = ref('1st')
const start_date = ref('')
const end_date = ref('')

function submitTerm() {
    router.post('/admin/academic-terms', {
        academic_year: academic_year.value,
        semester: semester.value,
        start_date: start_date.value || null,
        end_date: end_date.value || null,
    }, { preserveScroll: true, onSuccess: () => {
        academic_year.value = ''
        semester.value = '1st'
        start_date.value = ''
        end_date.value = ''
    } })
}

function activate(row: AcademicTermRow) {
    router.post(`/admin/academic-terms/${row.id}/activate`, {}, { preserveScroll: true })
}

const semesterLabel: Record<string, string> = {
    '1st': '1st Semester',
    '2nd': '2nd Semester',
    summer: 'Summer',
}
</script>

<template>
    <Head title="Academic Terms" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="Academic Terms" subtitle="Manage the school year and semester that determines organization membership for every student." />

            <Card>
                <div class="border-b px-5 py-4">
                    <p class="text-sm font-semibold">Add a new term</p>
                </div>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-5">
                        <div class="grid gap-1.5">
                            <label class="text-xs font-medium text-muted-foreground">School year</label>
                            <Input v-model="academic_year" placeholder="2026-2027" />
                        </div>
                        <div class="grid gap-1.5">
                            <label class="text-xs font-medium text-muted-foreground">Semester</label>
                            <select v-model="semester" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-ring">
                                <option value="1st">1st Semester</option>
                                <option value="2nd">2nd Semester</option>
                                <option value="summer">Summer</option>
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <label class="text-xs font-medium text-muted-foreground">Start date</label>
                            <Input v-model="start_date" type="date" />
                        </div>
                        <div class="grid gap-1.5">
                            <label class="text-xs font-medium text-muted-foreground">End date</label>
                            <Input v-model="end_date" type="date" />
                        </div>
                        <div class="grid items-end gap-1.5">
                            <Button @click="submitTerm">Add term</Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="overflow-hidden">
                <div class="flex flex-col gap-3 border-b px-5 py-4">
                    <p class="text-sm font-semibold">Terms ({{ terms.length }})</p>
                </div>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/50 text-left">
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">School year</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Semester</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Start</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">End</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Enrolled</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Status</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="term in terms" :key="term.id" class="border-b transition-colors last:border-0 hover:bg-muted/40">
                                    <td class="px-5 py-3.5">
                                        <span class="font-medium">{{ term.academic_year }}</span>
                                        <span v-if="term.is_active" class="ml-2 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200">Current</span>
                                    </td>
                                    <td class="px-5 py-3.5">{{ semesterLabel[term.semester] || term.semester }}</td>
                                    <td class="px-5 py-3.5 text-muted-foreground">{{ term.start_date || '—' }}</td>
                                    <td class="px-5 py-3.5 text-muted-foreground">{{ term.end_date || '—' }}</td>
                                    <td class="px-5 py-3.5 text-muted-foreground">{{ term.enrollments_count }}</td>
                                    <td class="px-5 py-3.5 text-muted-foreground">{{ term.is_active ? 'Active' : 'Inactive' }}</td>
                                    <td class="px-5 py-3.5">
                                        <Button v-if="!term.is_active" variant="outline" size="sm" @click="activate(term)">
                                            Set as current term
                                        </Button>
                                        <span v-else class="text-xs text-muted-foreground">Active</span>
                                    </td>
                                </tr>
                                <tr v-if="terms.length === 0">
                                    <td colspan="7" class="px-5 py-10 text-center text-muted-foreground">No academic terms yet. Add one above.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>