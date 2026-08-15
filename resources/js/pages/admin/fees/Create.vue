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
import { computed } from 'vue';

interface OrgOption {
    id: number;
    code: string;
    name: string;
    type: string;
}

interface TermOption {
    id: number;
    name: string;
    is_active: boolean;
}

const props = defineProps<{
    organizations: OrgOption[];
    academic_terms: TermOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Fees', href: '/admin/fees' },
    { title: 'New Fee' },
];

const activeTerm = computed(() => props.academic_terms.find((t) => t.is_active) || props.academic_terms[0]);

const form = useForm({
    organization_id: props.organizations[0] ? String(props.organizations[0].id) : '',
    name: '',
    description: '',
    amount: '',
    academic_term_id: activeTerm.value ? String(activeTerm.value.id) : '',
    required_years: ['1', '2', '3', '4'] as string[],
    due_date: '',
});

const allYears = computed({
    get: () => form.required_years.length === 4 || form.required_years.includes('all'),
    set: (v: boolean) => {
        form.required_years = v ? ['all'] : [];
    },
});

function toggleYear(y: string) {
    const idx = form.required_years.indexOf(y);
    if (idx >= 0) form.required_years.splice(idx, 1);
    else form.required_years.push(y);
}

const submit = () => {
    form.required_years = allYears.value ? ['all'] : form.required_years;
    form.post(route('admin.fees.store'));
};
</script>

<template>
    <Head title="New Fee" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="New Fee" subtitle="Create a fee draft for your organization. Post it to assign it to students." />

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
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="form.name" placeholder="e.g. Membership Fee" />
                            <InputError :message="form.errors.name" />
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

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="amount">Amount (₱)</Label>
                                <Input id="amount" v-model="form.amount" type="number" step="0.01" min="0" placeholder="0.00" />
                                <InputError :message="form.errors.amount" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="academic_term_id">Term</Label>
                                <select
                                    id="academic_term_id"
                                    v-model="form.academic_term_id"
                                    class="w-full rounded-xl border border-gray-300 bg-white px-5 py-3 outline-none transition focus:ring-2 focus:ring-[#20673A]"
                                >
                                    <option value="" disabled>Select term</option>
                                    <option v-for="t in academic_terms" :key="t.id" :value="String(t.id)">
                                        {{ t.name }}{{ t.is_active ? ' (Current)' : '' }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.academic_term_id" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label>Required Students</Label>
                            <div class="flex flex-wrap gap-4">
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" v-model="allYears" class="rounded" /> All Students
                                </label>
                                <label v-for="y in ['1', '2', '3', '4']" :key="y" class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" :checked="form.required_years.includes('all') || form.required_years.includes(y)" @change="toggleYear(y)" class="rounded" />
                                    {{ ['', '1st', '2nd', '3rd', '4th'][Number(y)] }} Year
                                </label>
                            </div>
                            <InputError :message="form.errors.required_years" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="due_date">Due Date</Label>
                            <Input id="due_date" v-model="form.due_date" type="date" />
                            <InputError :message="form.errors.due_date" />
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-2">
                            <a href="/admin/fees" class="rounded-xl px-4 py-2.5 text-sm font-medium text-gray-500 hover:bg-gray-100">
                                Cancel
                            </a>
                            <Button type="submit" :disabled="form.processing" class="rounded-xl bg-[#20673A] px-6 py-2.5 font-semibold text-white hover:bg-[#027F3B]">
                                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                                {{ form.processing ? 'Saving...' : 'Create Fee' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
