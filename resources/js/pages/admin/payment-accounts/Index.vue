<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Account {
    id: number;
    organization: { id: number; name: string } | null;
    account_name: string;
    account_provider?: string | null;
    account_number: string;
    qr_code_image_url?: string | null;
    is_active: boolean;
}

interface OrgOption {
    id: number;
    name: string;
    type?: string | null;
    has_account: boolean;
}

const props = defineProps<{
    accounts: Account[];
    organizations: OrgOption[];
    can_manage: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payment Account', href: '/admin/payment-accounts' },
];

const form = useForm({
    organization_id: props.organizations[0]?.id ?? 0,
    account_name: '',
    account_provider: '',
    account_number: '',
    qr_code_image: null as File | null,
    is_active: true,
});

const canManage = computed(() => props.can_manage);

const previewUrl = ref<string | null>(null);
const viewing = ref<Account | null>(null);

function onFileChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    form.qr_code_image = file;
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }
    if (file) {
        previewUrl.value = URL.createObjectURL(file);
    }
}

function submit() {
    form.post('/admin/payment-accounts', {
        onSuccess: () => {
            form.reset('account_name', 'account_provider', 'account_number', 'qr_code_image');
            if (previewUrl.value) {
                URL.revokeObjectURL(previewUrl.value);
                previewUrl.value = null;
            }
        },
    });
}

function remove(account: Account) {
    if (confirm(`Remove payment account for ${account.organization?.name}?`)) {
        router.delete(`/admin/payment-accounts/${account.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Payment Account" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader
                title="Organization Payment Account"
                subtitle="The official/authorized payment destination students use for cashless payments."
            />

            <div :class="canManage ? 'grid gap-6 lg:grid-cols-2' : 'grid gap-6'">
                <Card v-if="canManage">
                    <div class="border-b px-5 py-4">
                        <p class="text-sm font-semibold">Configure account</p>
                    </div>
                    <CardContent class="space-y-4 p-5 text-sm">
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium">Organization</label>
                            <select
                                v-model="form.organization_id"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option v-for="org in organizations" :key="org.id" :value="org.id">
                                    {{ org.name }} {{ org.has_account ? '(has account)' : '' }}
                                </option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium">Account name</label>
                            <input
                                v-model="form.account_name"
                                type="text"
                                placeholder="e.g. SSC Official Account"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                            <p v-if="form.errors.account_name" class="text-sm text-destructive">{{ form.errors.account_name }}</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label class="block text-sm font-medium">Provider / Wallet</label>
                                <input
                                    v-model="form.account_provider"
                                    type="text"
                                    placeholder="GCash, Maya, Bank"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-sm font-medium">Account / Wallet number</label>
                                <input
                                    v-model="form.account_number"
                                    type="text"
                                    placeholder="e.g. 0917 123 4567"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                />
                                <p v-if="form.errors.account_number" class="text-sm text-destructive">{{ form.errors.account_number }}</p>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium">QR code image (optional)</label>
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="text-sm file:mr-3 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white"
                                @change="onFileChange"
                            />
                            <img
                                v-if="previewUrl"
                                :src="previewUrl"
                                alt="Selected QR preview"
                                class="mt-2 h-28 w-28 rounded-lg border object-contain"
                            />
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.is_active" type="checkbox" class="size-4 accent-primary" />
                            Active (shown to students)
                        </label>
                        <button
                            type="button"
                            :disabled="form.processing"
                            class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90 disabled:opacity-50"
                            @click="submit"
                        >
                            {{ form.processing ? 'Saving...' : 'Save account' }}
                        </button>
                    </CardContent>
                </Card>

                <Card>
                    <div class="border-b px-5 py-4">
                        <p class="text-sm font-semibold">Configured accounts</p>
                    </div>
                    <CardContent class="space-y-3 p-5 text-sm">
                        <div v-for="account in accounts" :key="account.id" class="rounded-lg border p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-medium">{{ account.organization?.name }}</p>
                                    <p class="mt-1">{{ account.account_name }}</p>
                                    <p class="text-muted-foreground">{{ account.account_provider || 'Official' }} · {{ account.account_number }}</p>
                                    <button
                                        v-if="account.qr_code_image_url"
                                        type="button"
                                        class="mt-2 inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
                                        @click="viewing = account"
                                    >
                                        <img :src="account.qr_code_image_url" alt="Payment QR" class="h-10 w-10 rounded border object-contain" />
                                        View QR
                                    </button>
                                    <span v-else class="mt-2 inline-block text-muted-foreground">No QR image</span>
                                </div>
                                <button
                                    v-if="canManage"
                                    type="button"
                                    class="rounded-md p-1 text-destructive hover:bg-destructive/10"
                                    @click="remove(account)"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                        <p v-if="accounts.length === 0" class="py-8 text-center text-muted-foreground">No payment accounts configured yet.</p>
                    </CardContent>
                </Card>
            </div>

            <Dialog
                :open="!!viewing"
                @update:open="
                    (v: boolean) => {
                        if (!v) viewing = null;
                    }
                "
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{{ viewing?.organization?.name }} — payment QR</DialogTitle>
                        <DialogDescription>
                            {{ viewing?.account_name }} · {{ viewing?.account_provider || 'Official' }} · {{ viewing?.account_number }}
                        </DialogDescription>
                    </DialogHeader>
                    <div class="flex justify-center py-2">
                        <img :src="viewing?.qr_code_image_url" alt="Payment QR" class="max-h-96 max-w-full rounded-lg border object-contain" />
                    </div>
                    <DialogFooter>
                        <a
                            v-if="viewing?.qr_code_image_url"
                            :href="viewing.qr_code_image_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="rounded-md border border-input px-4 py-2 text-sm font-medium hover:bg-muted"
                        >
                            Open in new tab
                        </a>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
