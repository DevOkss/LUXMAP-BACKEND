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

const props = defineProps<{
    institute: {
        id: number
        code: string
        name: string
        logo_url: string | null
        is_active: boolean
    }
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Institute and Programs', href: '/admin/institutes' },
    { title: props.institute.name, href: `/admin/institutes/${props.institute.id}` },
    { title: 'Edit', href: '' },
]

const form = useForm({
    code: props.institute.code,
    name: props.institute.name,
    logo: null as File | null,
    remove_logo: false,
    is_active: props.institute.is_active,
})

const previewUrl = ref<string | null>(props.institute.logo_url)

const onLogoSelected = (event: Event) => {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    if (!file) return

    form.logo = file
    form.remove_logo = false
    previewUrl.value = URL.createObjectURL(file)
}

const removeLogo = () => {
    form.logo = null
    form.remove_logo = true
    previewUrl.value = null
}

const submit = () => {
    form.post(`/admin/institutes/${props.institute.id}`, {
        forceFormData: true,
        _method: 'put',
    })
}
</script>

<template>
    <Head :title="'Edit ' + institute.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="Edit Institute" :subtitle="institute.name" />

            <Card class="max-w-2xl overflow-hidden">
                <CardHeader class="border-b">
                    <CardTitle>Institute Details</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-4 p-1 pt-4">
                        <div class="space-y-2">
                            <Label for="code">Code</Label>
                            <Input id="code" v-model="form.code" />
                            <InputError :message="form.errors.code" />
                        </div>

                        <div class="space-y-2">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="form.name" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="logo">Logo</Label>
                            <div v-if="previewUrl" class="mb-2 flex items-center gap-4">
                                <img :src="previewUrl" alt="Logo preview" class="h-20 w-20 rounded-lg border object-cover" />
                                <Button type="button" variant="outline" @click="removeLogo">Remove Logo</Button>
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
                            <Button type="submit" :disabled="form.processing">Update</Button>
                            <Link :href="`/admin/institutes/${institute.id}`">
                                <Button variant="outline" type="button">Cancel</Button>
                            </Link>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
