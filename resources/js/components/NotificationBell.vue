<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { router, usePage } from '@inertiajs/vue3'
import { Bell, CheckCheck } from 'lucide-vue-next'

interface Notification {
    id: string
    title: string
    body: string
    created_at: string
    is_read: boolean
}

const page = usePage<{ notifications?: { unread_count: number; recent: Notification[] } | null }>()
const notifications = page.props.notifications

function markAllRead() {
    router.post('/admin/notifications/read-all', {}, { preserveScroll: true })
}

function relativeTime(value: string): string {
    const date = new Date(value)
    const seconds = Math.floor((Date.now() - date.getTime()) / 1000)
    if (seconds < 60) return 'just now'
    const minutes = Math.floor(seconds / 60)
    if (minutes < 60) return `${minutes}m ago`
    const hours = Math.floor(minutes / 60)
    if (hours < 24) return `${hours}h ago`
    const days = Math.floor(hours / 24)
    if (days < 7) return `${days}d ago`
    return date.toLocaleDateString()
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="relative rounded-full" aria-label="Notifications">
                <Bell class="size-5" />
                <span
                    v-if="notifications && notifications.unread_count > 0"
                    class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-bold text-white"
                >
                    {{ notifications.unread_count }}
                </span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-80 p-0">
            <DropdownMenuLabel class="flex items-center justify-between px-4 py-3 font-normal">
                <span class="font-semibold">Notifications</span>
                <button
                    v-if="notifications && notifications.unread_count > 0"
                    @click="markAllRead"
                    class="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                >
                    <CheckCheck class="size-3.5" />
                    Mark all read
                </button>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <div class="max-h-80 overflow-y-auto">
                <div v-if="!notifications || notifications.recent.length === 0" class="px-4 py-8 text-center text-sm text-muted-foreground">
                    No notifications yet.
                </div>
                <div v-else>
                    <a
                        v-for="n in notifications.recent"
                        :key="n.id"
                        :href="`/admin/notifications`"
                        class="flex items-start gap-3 border-b px-4 py-3 text-left transition-colors last:border-0 hover:bg-accent"
                    >
                        <span class="mt-1.5 size-2 shrink-0 rounded-full" :class="n.is_read ? 'bg-transparent' : 'bg-primary'" />
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium">{{ n.title }}</span>
                            <span class="mt-0.5 line-clamp-2 block text-xs text-muted-foreground">{{ n.body }}</span>
                            <span class="mt-1 block text-[11px] text-muted-foreground">{{ relativeTime(n.created_at) }}</span>
                        </span>
                    </a>
                </div>
            </div>
            <DropdownMenuSeparator />
            <a href="/admin/notifications" class="block py-2.5 text-center text-xs font-medium text-primary hover:underline">
                View all notifications
            </a>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
