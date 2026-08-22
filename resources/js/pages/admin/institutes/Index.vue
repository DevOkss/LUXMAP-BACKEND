<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { type BreadcrumbItem } from '@/types'
import { Head, Link } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Building2, Plus } from 'lucide-vue-next'

interface Institute {
    id: number
    code: string
    name: string
    logo_url: string | null
    is_active: boolean
    programs_count: number
}

defineProps<{
    institutes: Institute[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Institute and Programs', href: '/admin/institutes' },
]

const logoFallback = '/branding/luxmap.png'
</script>

<template>
    <Head title="Institute and Programs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="Institute and Programs" subtitle="Manage institutes and their programs.">
                <template #actions>
                    <Link href="/admin/institutes/create">
                        <Button>
                            <Plus class="size-4" />
                            Create Institute
                        </Button>
                    </Link>
                </template>
            </PageHeader>

            <div v-if="institutes.length === 0" class="rounded-xl border p-12 text-center text-muted-foreground">
                No institutes yet. Create one to get started.
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="institute in institutes" :key="institute.id" class="overflow-hidden transition-shadow hover:shadow-md">
                    <CardHeader class="border-b bg-muted/30">
                        <div class="flex items-center gap-4">
                            <img
                                :src="institute.logo_url || logoFallback"
                                :alt="institute.name"
                                class="h-14 w-14 rounded-lg border bg-background object-cover"
                            />
                            <div class="min-w-0">
                                <CardTitle class="truncate text-base">{{ institute.name }}</CardTitle>
                                <p class="text-sm text-muted-foreground">{{ institute.code }}</p>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-3 pt-4">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">Programs</span>
                            <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">{{ institute.programs_count }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">Status</span>
                            <span :class="institute.is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'" class="font-medium">
                                {{ institute.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="flex gap-2 pt-1">
                            <Link :href="`/admin/institutes/${institute.id}`" class="flex-1">
                                <Button variant="outline" class="w-full">
                                    <Building2 class="size-4" />
                                    Manage
                                </Button>
                            </Link>
                            <Link :href="`/admin/institutes/${institute.id}/edit`">
                                <Button variant="ghost" class="w-full">Edit</Button>
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
