<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { type BreadcrumbItem } from '@/types'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import InputError from '@/components/InputError.vue'
import { Check, Loader2, Search, UserCheck, X } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'

interface Target {
    id: number
    code: string
    name: string
    type: string
}

interface SearchUser {
    id: number
    name: string
    email: string
    student_number: string | null
}

defineProps<{
    target: Target | null
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Officers', href: '/admin/officers' },
    { title: 'Assign', href: '/admin/officers/assign' },
]

const form = useForm({
    user_id: '',
    position: '',
})

const query = ref('')
const results = ref<SearchUser[]>([])
const searching = ref(false)
const showResults = ref(false)

const selectedUser = computed<SearchUser | null>(() => {
    if (!form.user_id) return null
    return results.value.find((u) => u.id === Number(form.user_id)) ?? null
})

const clearSelection = () => {
    form.user_id = ''
    results.value = []
    query.value = ''
    showResults.value = false
}

const selectUser = (user: SearchUser) => {
    isSelecting = true
    form.user_id = String(user.id)
    query.value = `${user.name} (${user.student_number || user.email})`
    results.value = []
    showResults.value = false
}

let searchTimer: ReturnType<typeof setTimeout> | null = null
let isSelecting = false

watch(query, (value) => {
    if (isSelecting) {
        isSelecting = false
        return
    }

    if (form.user_id) {
        form.user_id = ''
    }

    if (searchTimer) clearTimeout(searchTimer)

    if (value.trim().length < 2) {
        results.value = []
        showResults.value = false
        return
    }

    searching.value = true
    showResults.value = true

    searchTimer = setTimeout(async () => {
        try {
            const res = await fetch(`/admin/officers/search?q=${encodeURIComponent(value.trim())}`, {
                headers: { Accept: 'application/json' },
            })
            const data = await res.json()
            results.value = data.users ?? []
        } catch {
            results.value = []
        } finally {
            searching.value = false
        }
    }, 300)
})

const submit = () => {
    form.post('/admin/officers/assign')
}
</script>

<template>
    <Head title="Assign Officer" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="Assign Officer" subtitle="Search a student by name or student ID number, then set their position." />

            <Card class="max-w-2xl">
                <CardHeader class="border-b">
                    <CardTitle>Assignment</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-4 p-1 pt-4">
                        <div v-if="target" class="rounded-lg border bg-muted/50 px-4 py-3 text-sm">
                            <span class="text-muted-foreground">Assigning to</span>
                            <span class="ml-2 font-medium">{{ target.name }}</span>
                            <span class="ml-1 text-xs text-muted-foreground">({{ target.code }})</span>
                        </div>

                        <div class="space-y-2">
                            <Label for="user-search">Student</Label>
                            <div class="relative">
                                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="user-search"
                                    v-model="query"
                                    class="pl-9 pr-9"
                                    placeholder="Search by name or student ID..."
                                    autocomplete="off"
                                />
                                <button
                                    v-if="form.user_id || query"
                                    type="button"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    @click="clearSelection"
                                >
                                    <X class="size-4" />
                                </button>

                                <div v-if="showResults" class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border bg-background shadow-lg">
                                    <div v-if="searching" class="flex items-center gap-2 px-4 py-3 text-sm text-muted-foreground">
                                        <Loader2 class="size-4 animate-spin" />
                                        Searching...
                                    </div>
                                    <ul v-else-if="results.length" class="max-h-64 divide-y overflow-y-auto">
                                        <li v-for="user in results" :key="user.id">
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-accent"
                                                @click="selectUser(user)"
                                            >
                                                <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                                    <UserCheck class="size-4" />
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-medium">{{ user.name }}</p>
                                                    <p class="truncate text-xs text-muted-foreground">
                                                        {{ user.student_number || user.email }}
                                                    </p>
                                                </div>
                                                <Check class="ml-auto size-4 shrink-0 text-primary" />
                                            </button>
                                        </li>
                                    </ul>
                                    <p v-else class="px-4 py-3 text-sm text-muted-foreground">No students found.</p>
                                </div>
                            </div>
                            <InputError :message="form.errors.user_id" />
                        </div>

                        <div v-if="selectedUser" class="flex items-center gap-3 rounded-lg border bg-primary/5 px-4 py-3">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <UserCheck class="size-4" />
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ selectedUser.name }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ selectedUser.student_number || selectedUser.email }}</p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="position">Position</Label>
                            <Input
                                id="position"
                                v-model="form.position"
                                placeholder="e.g. President, Secretary, Treasurer"
                                :disabled="!form.user_id"
                            />
                            <InputError :message="form.errors.position" />
                        </div>

                        <div class="flex items-center gap-4">
                            <Button type="submit" :disabled="form.processing || !form.user_id">Assign Officer</Button>
                            <Link href="/admin/officers">
                                <Button variant="outline" type="button">Cancel</Button>
                            </Link>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
