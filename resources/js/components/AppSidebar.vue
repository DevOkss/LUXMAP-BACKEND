<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Sidebar, SidebarContent, SidebarGroup, SidebarGroupLabel, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    LayoutGrid, Users, Calendar, CalendarRange, DollarSign,
    CreditCard, Bell, Building2, UserCog, ShieldCheck, GraduationCap, History, ArrowLeftRight, CalendarClock,
    ClipboardList, Wallet, MonitorSmartphone
} from 'lucide-vue-next';
const page = usePage();
const permissions = (page.props as any).permissions as { modules?: string[]; role?: string } | undefined;
const modules = permissions?.modules || [];
const role = permissions?.role || 'student';

const currentPath = computed(() => page.url.split('?')[0]);

const isActive = (href: string) => {
    if (href === '/dashboard') return currentPath.value === '/dashboard';
    return currentPath.value === href || currentPath.value.startsWith(href + '/');
};

const moduleIcons: Record<string, object> = {
    dashboard: LayoutGrid,
    heads: UserCog,
    advisers: UserCog,
    users: Users,
    officers: ShieldCheck,
    events: Calendar,
    calendar: CalendarRange,
    fees: DollarSign,
    payments: CreditCard,
    payment_submissions: ClipboardList,
    payment_accounts: Wallet,
    notifications: Bell,
    institutes: Building2,
    students: GraduationCap,
    activity_logs: History,
    shift_requests: ArrowLeftRight,
    academic_terms: CalendarClock,
    device_bindings: MonitorSmartphone,
};

const moduleNames: Record<string, string> = {
    dashboard: 'Dashboard',
    heads: 'Heads',
    advisers: 'Advisers',
    users: 'Users',
    officers: 'Officers',
    events: 'Activities',
    calendar: 'Calendar',
    fees: 'Fees',
    payments: 'Payments',
    payment_submissions: 'Pending Verification',
    payment_accounts: 'Payment Account',
    notifications: 'Notifications',
    institutes: 'Institute and Programs',
    students: 'Students',
    activity_logs: 'Activity Log',
    shift_requests: 'Shift Requests',
    academic_terms: 'Academic Terms',
    device_bindings: 'Device Bindings',
};

const moduleRoutes: Record<string, string> = {
    dashboard: '/dashboard',
    heads: '/admin/heads',
    advisers: '/admin/advisers',
    users: '/admin/users',
    officers: '/admin/officers',
    events: '/admin/events',
    calendar: '/admin/calendar',
    fees: '/admin/fees',
    payments: '/admin/payments',
    payment_submissions: '/admin/payments?tab=pending',
    payment_accounts: '/admin/payment-accounts',
    notifications: '/admin/notifications',
    institutes: '/admin/institutes',
    students: '/admin/students',
    activity_logs: '/admin/activity-logs',
    shift_requests: '/admin/shift-requests',
    academic_terms: '/admin/academic-terms',
    device_bindings: '/admin/device-bindings',
};

const officerModules = modules.filter(m =>
    ['dashboard', 'heads', 'advisers', 'users', 'officers', 'events', 'calendar', 'fees', 'payments', 'payment_submissions', 'payment_accounts', 'notifications', 'institutes', 'students', 'activity_logs', 'shift_requests', 'academic_terms', 'device_bindings'].includes(m)
);

const studentModules = modules.filter(m =>
    ['dashboard', 'attendance_scanner', 'attendance_queue', 'attendance_history', 'fees', 'payments', 'receipts', 'notifications', 'profile', 'settings'].includes(m)
);

const mainNavItems: NavItem[] = role !== 'student'
    ? officerModules.map(m => ({
        title: moduleNames[m] || m,
        href: moduleRoutes[m] || `/${m}`,
        icon: moduleIcons[m] as object,
    }))
    : studentModules.map(m => ({
        title: moduleNames[m] || m,
        href: moduleRoutes[m] || `/${m}`,
        icon: moduleIcons[m] as object,
    }));

const roleLabels: Record<string, string> = {
    super_admin: 'Super Admin',
    ssc_head: 'SSC Head',
    institute_head: 'Institute Head',
    sro_head: 'SRO Head',
    ssc_officer: 'SSC Officer',
    isc_officer: 'ISC Officer',
    sro_officer: 'SRO Officer',
    student: 'Student',
};

const roleLabel = computed(() => roleLabels[role] || role);
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar">
        <SidebarHeader class="border-b">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" class="bg-transparent hover:bg-transparent">
                        <AppLogo />
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <div class="px-3 pb-3 group-data-[collapsible=icon]:hidden">
                <span class="flex w-full items-center justify-center rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">
                    {{ roleLabel }}
                </span>
            </div>
        </SidebarHeader>

        <SidebarContent class="pt-4">
            <SidebarGroup class="mt-4 px-2 py-0">
                <SidebarGroupLabel class="px-3 text-xs font-semibold uppercase tracking-wider">Main Menu</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in mainNavItems" :key="item.title">
                        <SidebarMenuButton
                            as-child
                            :is-active="isActive(item.href)"
                            class="gap-3 rounded-lg py-2.5 data-[active=true]:bg-primary/10 data-[active=true]:font-medium data-[active=true]:text-primary"
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" class="size-[18px]" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>
    </Sidebar>
    <slot />
</template>
