<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import Pagination from '@/components/Pagination.vue'
import { type BreadcrumbItem } from '@/types'
import { Head, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Search, ArrowUp, ArrowDown, ChevronDown } from 'lucide-vue-next'
import { ref, computed } from 'vue'

interface Student {
    id: number
    name: string
    email: string
    student_number: string | null
    year_level: number | null
    program: string | null
    is_enrolled: boolean
}

const props = defineProps<{
    students: { data: Student[]; current_page: number; last_page: number; total: number; per_page: number }
    programs: { id: number; name: string }[] | null
    filters: { search: string | null; status: string | null; sort: string | null; direction: string | null; years: string | null; program_id: string | null }
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Students', href: '/admin/students' },
]

const search = ref(props.filters.search || '')
const status = ref(props.filters.status || '')
const sortDir = ref(props.filters.direction || 'asc')
const selectedYears = ref<string[]>(props.filters.years ? props.filters.years.split(',') : [])
const programId = ref(props.filters.program_id || '')
const yearOpen = ref(false)

const yearOptions = [
    { value: '1', label: '1st' },
    { value: '2', label: '2nd' },
    { value: '3', label: '3rd' },
    { value: '4', label: '4th' },
]

const yearLabel = computed(() => {
    if (!selectedYears.value.length) return 'All Years'
    return selectedYears.value.map(v => yearOptions.find(y => y.value === v)?.label).join(', ')
})

const meta = computed(() => ({
    current_page: props.students.current_page,
    last_page: props.students.last_page,
    total: props.students.total,
    per_page: props.students.per_page ?? 10,
}))

const activeFilters = computed(() => ({
    search: search.value || undefined,
    status: status.value || undefined,
    sort: 'name',
    direction: sortDir.value,
    years: selectedYears.value.length ? selectedYears.value.join(',') : undefined,
    program_id: programId.value || undefined,
}))

function doSearch() {
    router.get('/admin/students', {
        search: search.value || undefined,
        status: status.value || undefined,
        sort: 'name',
        direction: sortDir.value,
        years: selectedYears.value.length ? selectedYears.value.join(',') : undefined,
        program_id: programId.value || undefined,
        per_page: meta.value.per_page,
    }, { preserveState: true })
}

function toggleYear(year: string) {
    const idx = selectedYears.value.indexOf(year)
    if (idx >= 0) selectedYears.value.splice(idx, 1)
    else selectedYears.value.push(year)
    doSearch()
}

function toggleSort() {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    doSearch()
}
</script>

<template>
    <Head title="Students" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="Students" subtitle="Students enrolled in your scope." />

            <Card class="overflow-hidden">
                <div class="flex flex-col gap-3 border-b px-5 py-4 md:flex-row md:items-center md:justify-between">
                    <p class="text-sm font-semibold">Students ({{ students.total }})</p>
                    <div class="flex flex-wrap gap-2">
                        <select
                            v-if="programs"
                            v-model="programId"
                            @change="doSearch"
                            class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">All Programs</option>
                            <option v-for="p in programs" :key="p.id" :value="String(p.id)">{{ p.name }}</option>
                        </select>
                        <div class="relative">
                            <button
                                type="button"
                                @click="yearOpen = !yearOpen"
                                class="inline-flex h-10 items-center gap-1.5 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background hover:bg-accent hover:text-accent-foreground"
                            >
                                {{ yearLabel }}
                                <ChevronDown class="size-4 opacity-50" />
                            </button>
                            <div
                                v-if="yearOpen"
                                class="absolute left-0 z-20 mt-1 w-44 rounded-md border bg-background p-2 shadow-lg"
                            >
                                <label
                                    v-for="y in yearOptions"
                                    :key="y.value"
                                    class="flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-accent"
                                >
                                    <input
                                        type="checkbox"
                                        :value="y.value"
                                        :checked="selectedYears.includes(y.value)"
                                        @change="toggleYear(y.value)"
                                        class="h-4 w-4 rounded border-gray-300"
                                    />
                                    {{ y.label }}
                                </label>
                            </div>
                        </div>
                        <select
                            v-model="status"
                            @change="doSearch"
                            class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">All</option>
                            <option value="enrolled">Enrolled</option>
                            <option value="not_enrolled">Not Enrolled</option>
                        </select>
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="search"
                                @keyup.enter="doSearch"
                                placeholder="Search by name or student #..."
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
                                    <th class="cursor-pointer select-none px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground" @click="toggleSort">
                                        <span class="inline-flex items-center gap-1">
                                            Name
                                            <ArrowUp v-if="sortDir === 'asc'" class="size-3" />
                                            <ArrowDown v-else class="size-3" />
                                        </span>
                                    </th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Student #</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Program</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Year</th>
                                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="student in students.data" :key="student.id" class="border-b transition-colors last:border-0 hover:bg-muted/40">
                                    <td class="px-5 py-3.5">
                                        <div class="font-medium">{{ student.name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ student.email }}</div>
                                    </td>
                                    <td class="px-5 py-3.5 text-muted-foreground">{{ student.student_number || '—' }}</td>
                                    <td class="px-5 py-3.5 text-muted-foreground">{{ student.program || '—' }}</td>
                                    <td class="px-5 py-3.5 text-muted-foreground">{{ student.year_level ?? '—' }}</td>
                                    <td class="px-5 py-3.5">
                                        <span
                                            v-if="student.is_enrolled"
                                            class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200"
                                        >Enrolled</span>
                                        <span
                                            v-else
                                            class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
                                        >Not Enrolled</span>
                                    </td>
                                </tr>
                                <tr v-if="students.data.length === 0">
                                    <td colspan="5" class="px-5 py-10 text-center text-muted-foreground">No students found.</td>
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
