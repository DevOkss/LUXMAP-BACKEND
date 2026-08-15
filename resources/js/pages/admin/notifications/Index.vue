<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import { type BreadcrumbItem } from '@/types'
import { Head, router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { CheckCheck } from 'lucide-vue-next'

interface Notification {
    id: string
    title: string
    body: string
    read_at: string | null
    created_at: string
    is_read: boolean
}

defineProps<{
    notifications: Notification[]
    unread_count: number
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Logs', href: '/admin/notifications' },
]

function markRead(id: string) {
    router.post(`/admin/notifications/${id}/read`)
}

function markAllRead() {
    router.post('/admin/notifications/read-all')
}
</script>

<template>
    <Head title="Logs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="Logs" subtitle="All the latest updates for your account.">
                <template #actions>
                    <Button v-if="unread_count > 0" variant="outline" @click="markAllRead">
                        <CheckCheck class="size-4" />
                        Mark All Read
                    </Button>
                </template>
            </PageHeader>

            <Card class="overflow-hidden">
                <div class="border-b px-5 py-4">
                    <p class="text-sm font-semibold">
                        All Notifications
                        <span v-if="unread_count > 0" class="ml-2 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">{{ unread_count }} unread</span>
                    </p>
                </div>
                <CardContent class="p-0">
                    <div v-if="notifications.length === 0" class="px-5 py-12 text-center text-muted-foreground">
                        No notifications yet.
                    </div>
                    <ul v-else class="divide-y">
                        <li
                            v-for="n in notifications"
                            :key="n.id"
                            :class="['flex items-start gap-3 px-5 py-4 transition-colors hover:bg-muted/40', !n.is_read ? 'bg-primary/5' : '']"
                        >
                            <span class="mt-1.5 size-2 shrink-0 rounded-full" :class="n.is_read ? 'bg-transparent' : 'bg-primary'" />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-sm">{{ n.title }}</span>
                                </div>
                                <p class="mt-1 text-sm text-muted-foreground">{{ n.body }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">{{ new Date(n.created_at).toLocaleString() }}</p>
                            </div>
                            <button v-if="!n.is_read" @click="markRead(n.id)" class="shrink-0 text-xs font-medium text-primary hover:underline">Mark read</button>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
