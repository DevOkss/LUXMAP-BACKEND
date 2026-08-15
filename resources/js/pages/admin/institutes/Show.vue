<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { type BreadcrumbItem } from '@/types'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import InputError from '@/components/InputError.vue'
import { Pencil, Plus, Trash2 } from 'lucide-vue-next'
import { ref } from 'vue'

interface Program {
    id: number
    code: string
    name: string
    is_active: boolean
}

interface Institute {
    id: number
    code: string
    name: string
    logo_url: string | null
    description?: string | null
    is_active: boolean
    programs: Program[]
}

const props = defineProps<{
    institute: Institute
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Institute and Programs', href: '/admin/institutes' },
    { title: props.institute.name, href: `/admin/institutes/${props.institute.id}` },
]

const programForm = useForm({
    code: '',
    name: '',
})

const addProgram = () => {
    programForm.post(`/admin/institutes/${props.institute.id}/programs`, {
        preserveScroll: true,
        onSuccess: () => programForm.reset(),
    })
}

const editing = useForm({
    code: '',
    name: '',
})

const editingId = ref<number | null>(null)

const startEdit = (program: Program) => {
    editingId.value = program.id
    editing.code = program.code
    editing.name = program.name
}

const saveEdit = (program: Program) => {
    editing.put(`/admin/programs/${program.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null
            editing.reset()
        },
    })
}

const cancelEdit = () => {
    editingId.value = null
    editing.reset()
}

const deleteProgram = (program: Program) => {
    if (confirm(`Delete program "${program.name}"?`)) {
        router.delete(`/admin/programs/${program.id}`, { preserveScroll: true })
    }
}

const destroy = () => {
    if (confirm('Are you sure you want to delete this institute and all its programs?')) {
        router.delete(`/admin/institutes/${props.institute.id}`)
    }
}
</script>

<template>
    <Head :title="institute.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader :title="institute.name" :subtitle="institute.code">
                <template #actions>
                    <div class="flex items-center gap-2">
                        <Link :href="`/admin/institutes/${institute.id}/edit`">
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

            <div class="grid gap-4 lg:grid-cols-3">
                <Card class="overflow-hidden lg:col-span-2">
                    <CardHeader class="border-b">
                        <CardTitle>Programs ({{ institute.programs.length }})</CardTitle>
                    </CardHeader>
                    <CardContent class="p-4">
                        <ul v-if="institute.programs.length" class="space-y-2">
                            <li
                                v-for="program in institute.programs"
                                :key="program.id"
                                class="flex items-center justify-between gap-3 rounded-lg border p-3"
                            >
                                <template v-if="editingId === program.id">
                                    <div class="flex flex-1 flex-col gap-2 sm:flex-row">
                                        <Input v-model="editing.code" placeholder="Code" class="sm:w-32" />
                                        <Input v-model="editing.name" placeholder="Name" class="flex-1" />
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <Button size="sm" @click="saveEdit(program)" :disabled="editing.processing">Save</Button>
                                        <Button size="sm" variant="ghost" @click="cancelEdit">Cancel</Button>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="min-w-0">
                                        <p class="font-medium">{{ program.name }}</p>
                                        <p class="text-xs text-muted-foreground">{{ program.code }}</p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1">
                                        <span
                                            :class="program.is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'"
                                            class="text-xs"
                                        >
                                            {{ program.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                        <Button size="sm" variant="ghost" @click="startEdit(program)">Edit</Button>
                                        <Button size="sm" variant="ghost" class="text-destructive" @click="deleteProgram(program)">Delete</Button>
                                    </div>
                                </template>
                            </li>
                        </ul>
                        <p v-else class="py-4 text-center text-sm text-muted-foreground">No programs yet.</p>
                    </CardContent>
                </Card>

                <Card class="overflow-hidden">
                    <CardHeader class="border-b">
                        <CardTitle>Add Program</CardTitle>
                    </CardHeader>
                    <CardContent class="p-4">
                        <form @submit.prevent="addProgram" class="space-y-4 p-1">
                            <div class="space-y-2">
                                <Label for="code">Code</Label>
                                <Input id="code" v-model="programForm.code" placeholder="e.g., BSCS" />
                                <InputError :message="programForm.errors.code" />
                            </div>

                            <div class="space-y-2">
                                <Label for="name">Name</Label>
                                <Input id="name" v-model="programForm.name" placeholder="e.g., Bachelor of Science in Computer Science" />
                                <InputError :message="programForm.errors.name" />
                            </div>

                            <Button type="submit" :disabled="programForm.processing" class="w-full">
                                <Plus class="size-4" />
                                Add Program
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
