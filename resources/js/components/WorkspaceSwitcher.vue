<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar'
import { usePage } from '@inertiajs/vue3'
import { ChevronsUpDown, Building2 } from 'lucide-vue-next'

interface Workspace {
    id: string
    name: string
    type: string
    organization_id: number | null
}

const page = usePage<{ auth: { user: { name: string } }; workspaces?: Workspace[] }>()
const workspaces = (page.props as any).workspaces as Workspace[] | undefined
const currentWorkspace = (page.props as any).currentWorkspace as Workspace | undefined
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton size="lg" class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground">
                        <div class="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                            <Building2 class="size-4" />
                        </div>
                        <div class="grid flex-1 text-left text-sm leading-tight">
                            <span class="truncate font-semibold">{{ currentWorkspace?.name || 'Workspace' }}</span>
                            <span class="truncate text-xs">{{ currentWorkspace?.type || 'Student' }}</span>
                        </div>
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-[--radix-dropdown-menu-trigger-width] min-w-56 rounded-lg" side="bottom" align="start" :side-offset="4">
                    <DropdownMenuItem v-for="ws in workspaces" :key="ws.id" as-child>
                        <a :href="`/workspace/switch/${ws.organization_id || 'student'}`" class="cursor-pointer">
                            <Building2 class="mr-2 size-4" />
                            <span>{{ ws.name }}</span>
                        </a>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
