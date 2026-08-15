<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import GeofenceMap from '@/components/GeofenceMap.vue';
import InputError from '@/components/InputError.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { LoaderCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface EventModel {
    uuid: string;
    title: string;
    status: string;
    event_date: string;
}

interface QrConfig {
    id: number;
    type: 'time_in' | 'time_out';
    valid_from: string;
    valid_until: string;
    is_generated: boolean;
    qr_data: string | null;
    latitude: number | string | null;
    longitude: number | string | null;
    geofence_radius: number | null;
    required_years: string[] | null;
}

const props = defineProps<{
    event: EventModel;
    configs: QrConfig[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Activities', href: '/admin/events' },
    { title: 'QR Configurations' },
];

const form = useForm({
    type: 'time_in' as 'time_in' | 'time_out',
    valid_from: '',
    valid_until: '',
    latitude: null as number | null,
    longitude: null as number | null,
    geofence_radius: 100,
    required_years: ['1', '2', '3', '4'] as string[],
    reuse_previous: false,
});

const geofenceCoords = computed({
    get: () => ({
        lat: form.latitude ?? 8.065254,
        lng: form.longitude ?? 123.756733,
        radius: form.geofence_radius,
    }),
    set: (v: { lat: number; lng: number; radius: number }) => {
        form.latitude = v.lat;
        form.longitude = v.lng;
        form.geofence_radius = v.radius;
    },
});

const allYears = computed({
    get: () => form.required_years.length === 4 || form.required_years.includes('all'),
    set: (v: boolean) => {
        form.required_years = v ? ['1', '2', '3', '4'] : [];
    },
});

function toggleYear(y: string) {
    const idx = form.required_years.indexOf(y);
    if (idx >= 0) form.required_years.splice(idx, 1);
    else form.required_years.push(y);
}

const lastGeneratedConfig = computed<QrConfig | null>(() => {
    const generated = props.configs.filter(c => c.is_generated);
    return generated.length ? generated[generated.length - 1] : null;
});

watch(() => form.reuse_previous, (checked) => {
    if (checked && lastGeneratedConfig.value) {
        const src = lastGeneratedConfig.value;
        form.latitude = src.latitude != null ? Number(src.latitude) : null;
        form.longitude = src.longitude != null ? Number(src.longitude) : null;
        form.geofence_radius = src.geofence_radius ?? 100;
        const years = src.required_years ?? ['1', '2', '3', '4'];
        form.required_years = years.includes('all') ? ['1', '2', '3', '4'] : years;
    }
});

function fmtTime(t: string | null): string {
    if (!t) return '—';
    const [h = 0, m = 0] = t.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const hr = h % 12 || 12;
    return `${hr}:${String(m).padStart(2, '0')} ${ampm}`;
}

function saveConfig() {
    const payload: Record<string, unknown> = {
        type: form.type,
        valid_from: form.valid_from,
        valid_until: form.valid_until,
    };

    if (form.reuse_previous && lastGeneratedConfig.value) {
        // Omit geofence/required-years so the backend copies them from the
        // last generated session's configuration.
        payload.reuse_from = lastGeneratedConfig.value.id;
    } else {
        payload.latitude = form.latitude ?? null;
        payload.longitude = form.longitude ?? null;
        payload.geofence_radius = Number(form.geofence_radius) || 100;
        payload.required_years = allYears.value ? ['all'] : form.required_years;
    }

    form.post(route('admin.events.qr-configurations.store', { event: props.event.uuid }), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('valid_from', 'valid_until', 'reuse_previous');
            form.latitude = null;
            form.longitude = null;
            form.geofence_radius = 100;
            form.required_years = ['1', '2', '3', '4'];
        },
    });
}

function generateQr(configId: number) {
    router.post(
        route('admin.events.qr-configurations.generate', { event: props.event.uuid, config: configId }),
        {},
        { preserveScroll: true },
    );
}

const removeOpen = ref(false);
const removingId = ref<number | null>(null);
const removing = ref(false);

function promptRemove(configId: number) {
    removingId.value = configId;
    removeOpen.value = true;
}

function confirmRemove() {
    if (removingId.value === null || removing.value) return;
    removing.value = true;
    router.delete(route('admin.events.qr-configurations.destroy', { event: props.event.uuid, config: removingId.value }), {
        preserveScroll: true,
        onSuccess: () => {
            removeOpen.value = false;
            removingId.value = null;
        },
        onFinish: () => {
            removing.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`QR Configurations - ${event.title}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader :title="event.title" subtitle="Manage QR attendance sessions for this activity." />

            <div v-if="configs.length" class="space-y-2">
                <h3 class="text-sm font-semibold text-muted-foreground">Existing QR Sessions</h3>
                <Card v-for="config in configs" :key="config.id">
                    <CardContent class="flex items-center justify-between gap-4 p-4">
                        <div>
                            <p class="text-sm font-semibold capitalize">
                                {{ config.type === 'time_in' ? 'Time In' : 'Time Out' }}
                            </p>
                            <p class="text-xs text-muted-foreground">{{ fmtTime(config.valid_from) }} – {{ fmtTime(config.valid_until) }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="config.is_generated ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-200' : 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-200'"
                            >
                                {{ config.is_generated ? 'Generated' : 'Pending' }}
                            </span>
                            <a
                                v-if="config.is_generated"
                                :href="route('admin.events.qr-configurations.download', { event: event.uuid, config: config.id })"
                                class="text-xs font-medium text-[#20673A] hover:underline"
                            >
                                Download PDF
                            </a>
                            <button
                                v-if="!config.is_generated"
                                @click="generateQr(config.id)"
                                class="text-xs font-medium text-[#20673A] hover:underline"
                            >
                                Generate
                            </button>
                            <button @click="promptRemove(config.id)" class="text-xs font-medium text-red-500 hover:underline">
                                Remove
                            </button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardContent class="space-y-4 p-6">
                    <h3 class="text-sm font-semibold">New QR Configuration</h3>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="type">QR Type</Label>
                            <select
                                id="type"
                                v-model="form.type"
                                class="w-full rounded-xl border border-gray-300 bg-white px-5 py-3 outline-none transition focus:ring-2 focus:ring-[#20673A]"
                            >
                                <option value="time_in">Time In</option>
                                <option value="time_out">Time Out</option>
                            </select>
                        </div>
                        <div class="flex items-end gap-2 pb-1">
                            <Input id="valid_from" v-model="form.valid_from" type="time" placeholder="From" />
                            <Input id="valid_until" v-model="form.valid_until" type="time" placeholder="Until" />
                        </div>
                    </div>
                    <InputError :message="form.errors.valid_from || form.errors.valid_until" />

                    <div class="grid gap-2">
                        <Label>Attendance Location</Label>
                        <GeofenceMap v-model="geofenceCoords" />
                        <div class="mt-1 flex items-center gap-3">
                            <Label class="shrink-0">Radius:</Label>
                            <input
                                v-model.number="form.geofence_radius"
                                type="range"
                                min="10"
                                max="500"
                                step="10"
                                class="flex-1 accent-[#20673A]"
                            />
                            <span class="w-16 text-right text-sm font-medium tabular-nums">{{ form.geofence_radius }}m</span>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Pin: {{ form.latitude != null ? Number(form.latitude).toFixed(7) : '—' }},
                            {{ form.longitude != null ? Number(form.longitude).toFixed(7) : '—' }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label>Required Students</Label>
                        <div class="flex flex-wrap gap-4">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" v-model="allYears" class="rounded" /> All Students
                            </label>
                            <label v-for="y in ['1', '2', '3', '4']" :key="y" class="flex items-center gap-2 text-sm">
                                <input type="checkbox" :checked="form.required_years.includes(y)" @change="toggleYear(y)" class="rounded" />
                                {{ ['', '1st', '2nd', '3rd', '4th'][Number(y)] }} Year
                            </label>
                        </div>
                        <InputError :message="form.errors.required_years" />
                    </div>

                    <label class="flex items-center gap-2 text-sm" :class="{ 'cursor-not-allowed opacity-50': !lastGeneratedConfig }">
                        <input type="checkbox" v-model="form.reuse_previous" :disabled="!lastGeneratedConfig" class="rounded" />
                        Reuse the last session's location and years
                    </label>
                    <p v-if="!lastGeneratedConfig" class="text-xs text-muted-foreground">
                        Generate a QR code first to enable reuse of its location and required years.
                    </p>

                    <Button
                        @click="saveConfig"
                        :disabled="form.processing"
                        class="w-full rounded-xl bg-[#20673A] py-4 font-semibold text-white hover:bg-[#027F3B]"
                    >
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        {{ form.processing ? 'Saving...' : 'Save QR Configuration' }}
                    </Button>
                </CardContent>
            </Card>
        </div>

        <Dialog v-model:open="removeOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove QR Configuration</DialogTitle>
                    <DialogDescription>
                        Attendances recorded using this QR will be deleted. This cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:justify-end">
                    <DialogClose as-child>
                        <Button variant="outline">Cancel</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        @click="confirmRemove"
                        :disabled="removing"
                        class="bg-red-600 text-white hover:bg-red-700"
                    >
                        {{ removing ? 'Removing...' : 'Remove' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
