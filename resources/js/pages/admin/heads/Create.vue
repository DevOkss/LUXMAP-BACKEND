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
import { Eye, EyeOff } from 'lucide-vue-next'
import { computed, ref } from 'vue'

interface ProgramOption {
    id: number
    code: string
    name: string
}

interface InstituteOption {
    id: number
    code: string
    name: string
    programs: ProgramOption[]
}

interface RoleOption {
    value: string
    label: string
}

const props = defineProps<{
    institutes: InstituteOption[]
    roles: RoleOption[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Heads', href: '/admin/heads' },
    { title: 'Create Head', href: '/admin/heads/create' },
]

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: '',
    institute_id: '',
    program_id: '',
})

const selectedInstitute = computed(() =>
    props.institutes.find((i) => i.id === Number(form.institute_id))
)

const showInstitute = computed(() =>
    form.role === 'institute_head' || form.role === 'sro_head'
)

const showProgram = computed(() => form.role === 'sro_head')

const showPassword = ref(false)

const togglePassword = () => {
    showPassword.value = !showPassword.value
}

const onRoleChange = () => {
    form.institute_id = ''
    form.program_id = ''
}

const onInstituteChange = () => {
    form.program_id = ''
}

const submit = () => {
    form.post('/admin/heads')
}
</script>

<template>
    <Head title="Create Head" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="Create Head Account" subtitle="Set up a new dedicated head account." />

            <Card class="max-w-2xl overflow-hidden">
                <CardHeader class="border-b">
                    <CardTitle>Head Account</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-4 p-1 pt-4">
                        <div class="space-y-2">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="form.name" placeholder="Full name" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="email">Email</Label>
                            <Input id="email" v-model="form.email" type="email" placeholder="name.head@soms.edu" />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div class="space-y-2">
                            <Label for="password">Password</Label>
                            <div class="relative">
                                <Input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    class="pr-10"
                                />
                                <button
                                    type="button"
                                    @click="togglePassword"
                                    class="absolute inset-y-0 right-0 flex items-center px-3 text-muted-foreground hover:text-foreground"
                                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                >
                                    <EyeOff v-if="showPassword" class="h-4 w-4" />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                            <InputError :message="form.errors.password" />
                        </div>

                        <div class="space-y-2">
                            <Label for="password_confirmation">Confirm Password</Label>
                            <div class="relative">
                                <Input
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    :type="showPassword ? 'text' : 'password'"
                                    class="pr-10"
                                />
                                <button
                                    type="button"
                                    @click="togglePassword"
                                    class="absolute inset-y-0 right-0 flex items-center px-3 text-muted-foreground hover:text-foreground"
                                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                >
                                    <EyeOff v-if="showPassword" class="h-4 w-4" />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="role">Head Type</Label>
                            <select
                                id="role"
                                v-model="form.role"
                                @change="onRoleChange"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="">Select a head type...</option>
                                <option v-for="role in roles" :key="role.value" :value="role.value">
                                    {{ role.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.role" />
                        </div>

                        <div v-if="showInstitute" class="space-y-2">
                            <Label for="institute_id">Institute</Label>
                            <select
                                id="institute_id"
                                v-model="form.institute_id"
                                @change="onInstituteChange"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="">Select an institute...</option>
                                <option v-for="institute in institutes" :key="institute.id" :value="institute.id">
                                    {{ institute.name }} ({{ institute.code }})
                                </option>
                            </select>
                            <InputError :message="form.errors.institute_id" />
                        </div>

                        <div v-if="showProgram" class="space-y-2">
                            <Label for="program_id">Program</Label>
                            <select
                                id="program_id"
                                v-model="form.program_id"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="">Select a program...</option>
                                <option v-for="program in selectedInstitute?.programs || []" :key="program.id" :value="program.id">
                                    {{ program.name }} ({{ program.code }})
                                </option>
                            </select>
                            <InputError :message="form.errors.program_id" />
                        </div>

                        <div class="flex items-center gap-4">
                            <Button type="submit" :disabled="form.processing">Create Head</Button>
                            <Link href="/admin/heads">
                                <Button variant="outline" type="button">Cancel</Button>
                            </Link>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
