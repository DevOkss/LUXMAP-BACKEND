<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { LoaderCircle } from 'lucide-vue-next';

interface OrgOption {
    id: number;
    code: string;
    name: string;
    type: string;
}

const props = defineProps<{
    organizations: OrgOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Activities', href: '/admin/events' },
    { title: 'New Activity' },
];

const form = useForm({
    organization_id: props.organizations[0] ? String(props.organizations[0].id) : '',
    title: '',
    description: '',
    venue: '',
    event_date: '',
    time_from: '',
    time_to: '',
});

const submit = () => {
    form.post(route('admin.events.store'));
};
</script>

<template>
    <Head title="New Activity" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="New Activity" subtitle="Create a draft activity for your organization." />

            <Card class="max-w-2xl">
                <CardContent class="p-6">
                    <form @submit.prevent="submit" class="flex flex-col gap-5">
                        <div class="grid gap-2">
                            <Label for="organization_id">Organization</Label>
                            <select
                                id="organization_id"
                                v-model="form.organization_id"
                                class="w-full rounded-xl border border-gray-300 bg-white px-5 py-3 outline-none transition focus:ring-2 focus:ring-[#20673A]"
                            >
                                <option v-for="org in organizations" :key="org.id" :value="String(org.id)">
                                    {{ org.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.organization_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="title">Title</Label>
                            <Input id="title" v-model="form.title" placeholder="Activity title" />
                            <InputError :message="form.errors.title" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="description">Description</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                placeholder="Optional description"
                                class="w-full rounded-xl border border-gray-300 px-5 py-3 outline-none transition focus:ring-2 focus:ring-[#20673A]"
                            ></textarea>
                            <InputError :message="form.errors.description" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="venue">Venue</Label>
                            <Input id="venue" v-model="form.venue" placeholder="Venue (optional)" />
                            <InputError :message="form.errors.venue" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="event_date">Date</Label>
                            <Input id="event_date" v-model="form.event_date" type="date" />
                            <InputError :message="form.errors.event_date" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="time_from">Starts at</Label>
                                <Input id="time_from" v-model="form.time_from" type="time" />
                                <InputError :message="form.errors.time_from" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="time_to">Ends at</Label>
                                <Input id="time_to" v-model="form.time_to" type="time" />
                                <InputError :message="form.errors.time_to" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-2">
                            <a href="/admin/events" class="rounded-xl px-4 py-2.5 text-sm font-medium text-gray-500 hover:bg-gray-100">
                                Cancel
                            </a>
                            <Button type="submit" :disabled="form.processing" class="rounded-xl bg-[#20673A] px-6 py-2.5 font-semibold text-white hover:bg-[#027F3B]">
                                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                                {{ form.processing ? 'Saving...' : 'Create Draft' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
