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
import { ref } from 'vue'

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Institute and Programs', href: '/admin/institutes' },
    { title: 'Create', href: '' },
]

const form = useForm({
    code: '',
    name: '',
    logo: null as File | null,
    is_active: true,
})

const previewUrl = ref<string | null>(null)

const onLogoSelected = (event: Event) => {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    if (!file) return

    form.logo = file
    previewUrl.value = URL.createObjectURL(file)
}

const submit = () => {
    form.post('/admin/institutes', { forceFormData: true })
}
</script>

<template>
    <Head title="Create Institute" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="Create Institute" subtitle="Add a new institute to your organization tree." />

            <Card class="max-w-2xl overflow-hidden">
                <CardHeader class="border-b">
                    <CardTitle>Institute Details</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-4 p-1 pt-4">
                        <div class="space-y-2">
                            <Label for="code">Code</Label>
                            <Input id="code" v-model="form.code" placeholder="e.g., ICS" />
                            <InputError :message="form.errors.code" />
                        </div>

                        <div class="space-y-2">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="form.name" placeholder="e.g., Institute of Computer Studies" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="logo">Logo</Label>
                            <div v-if="previewUrl" class="mb-2">
                                <img :src="previewUrl" alt="Logo preview" class="h-20 w-20 rounded-lg border object-cover" />
                            </div>
                            <Input
                                id="logo"
                                type="file"
                                accept="image/png,image/jpeg,image/jpg,image/webp"
                                @change="onLogoSelected"
                            />
                            <InputError :message="form.errors.logo" />
                        </div>

                        <div class="flex items-center gap-2">
                            <input id="is_active" type="checkbox" v-model="form.is_active" class="h-4 w-4 rounded border-gray-300" />
                            <Label for="is_active">Active</Label>
                        </div>

                        <div class="flex items-center gap-4">
                            <Button type="submit" :disabled="form.processing">Create</Button>
                            <Link href="/admin/institutes">
                                <Button variant="outline" type="button">Cancel</Button>
                            </Link>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
