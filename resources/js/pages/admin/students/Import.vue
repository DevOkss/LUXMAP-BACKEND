<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface ImportError {
    row: number;
    student_number: string | null;
    message: string;
}

interface ImportResult {
    created: number;
    updated: number;
    failed: number;
    rows: number;
    errors: ImportError[];
    term: string | null;
}

type ImportStreamEvent =
    | { type: 'progress'; processed: number; total: number; percent: number }
    | ({ type: 'result' } & ImportResult)
    | { type: 'error'; message: string };

defineProps<{
    templateUrl: string;
    currentTerm: string | null;
    tempPassword: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Import Students', href: '/admin/students/import' },
];

const page = usePage();
const flashResult = computed<ImportResult | null>(() => {
    return (page.props as any).flash?.import_result ?? null;
});

const localResult = ref<ImportResult | null>(null);
const result = computed<ImportResult | null>(() => localResult.value ?? flashResult.value);

const form = useForm({
    file: null as File | null,
});

const fileName = ref('');
const importing = ref(false);
const importError = ref('');
const progress = ref(0);
const processedRows = ref(0);
const totalRows = ref(0);

const columns = ['student_number', 'name', 'email', 'phone', 'year_level', 'sex', 'is_enrolled', 'institute', 'program'];

function onFileChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    form.file = file;
    fileName.value = file?.name ?? '';
}

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

async function submit() {
    const file = form.file;

    if (!file || importing.value) {
        return;
    }

    importing.value = true;
    importError.value = '';
    progress.value = 0;
    processedRows.value = 0;
    totalRows.value = 0;
    localResult.value = null;

    const data = new FormData();
    data.append('file', file);

    try {
        const response = await fetch('/admin/students/import/stream', {
            method: 'POST',
            headers: {
                Accept: 'application/x-ndjson',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: data,
            credentials: 'same-origin',
        });

        if (!response.ok || !response.body) {
            const payload = await response.json().catch(() => null);
            throw new Error(payload?.errors?.file?.[0] ?? payload?.message ?? `Import failed (HTTP ${response.status}).`);
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        const handleLine = (line: string) => {
            const payload = JSON.parse(line) as ImportStreamEvent;

            if (payload.type === 'progress') {
                processedRows.value = payload.processed;
                totalRows.value = payload.total;
                progress.value = payload.percent;
            } else if (payload.type === 'result') {
                localResult.value = payload;
                progress.value = 100;
                form.reset('file');
                fileName.value = '';
            } else if (payload.type === 'error') {
                importError.value = payload.message;
            }
        };

        while (true) {
            const { value, done } = await reader.read();

            if (done) {
                break;
            }

            buffer += decoder.decode(value, { stream: true });

            let newline: number;
            while ((newline = buffer.indexOf('\n')) !== -1) {
                const line = buffer.slice(0, newline).trim();
                buffer = buffer.slice(newline + 1);

                if (line) {
                    handleLine(line);
                }
            }
        }

        buffer += decoder.decode();

        if (buffer.trim()) {
            handleLine(buffer.trim());
        }
    } catch (error) {
        importError.value = error instanceof Error ? error.message : 'Import failed. Please try again.';
    } finally {
        importing.value = false;
    }
}
</script>

<template>
    <Head title="Import Students" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6">
            <PageHeader title="Import Students" subtitle="Create or update student accounts from an Excel file." />

            <div class="grid gap-6 lg:grid-cols-5">
                <Card class="lg:col-span-3">
                    <CardHeader class="border-b">
                        <CardTitle>Upload spreadsheet</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4 p-5">
                        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-6 text-center">
                            <p class="text-sm font-medium text-gray-900">
                                {{ fileName || 'Choose an .xlsx, .xls, or .csv file' }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500">The first row must contain the column headers.</p>
                            <Input
                                id="file"
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                class="mx-auto mt-4 max-w-sm cursor-pointer"
                                @change="onFileChange"
                            />
                            <InputError :message="form.errors.file" class="mt-2" />
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <Button type="button" :disabled="importing || !form.file" @click="submit">
                                {{ importing ? 'Importing...' : 'Import Students' }}
                            </Button>
                            <a :href="templateUrl" class="text-sm font-medium text-primary underline-offset-4 hover:underline"> Download template </a>
                        </div>

                        <p v-if="!currentTerm" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            No academic term is active. Students will be imported, but no enrollment snapshot will be created until a term is
                            activated.
                        </p>
                        <p v-else class="text-xs text-gray-500">
                            Enrollment snapshots will be synced to the active term: <strong>{{ currentTerm }}</strong
                            >.
                        </p>
                    </CardContent>
                </Card>

                <Card class="lg:col-span-2">
                    <CardHeader class="border-b">
                        <CardTitle>Before you import</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4 p-5 text-sm text-gray-600">
                        <div>
                            <p class="font-medium text-gray-900">Required columns</p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <span
                                    v-for="column in columns"
                                    :key="column"
                                    class="rounded-md bg-gray-100 px-2 py-1 font-mono text-xs text-gray-700"
                                >
                                    {{ column }}
                                </span>
                            </div>
                        </div>

                        <ul class="list-disc space-y-2 pl-5">
                            <li>Institute and program accept either the code or the full name.</li>
                            <li>Student numbers are matched exactly. Format the column as text if the values have leading zeros.</li>
                            <li>Existing accounts are matched by <strong>student number</strong>; their password is never changed.</li>
                            <li>
                                New accounts get the temporary password
                                <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs">{{ tempPassword }}</code>
                                and must change it on first login.
                            </li>
                            <li>Year level can be 1–6 or 1st–6th. Sex accepts M/F or Male/Female.</li>
                            <li>Is enrolled accepts 1/0, Yes/No, or True/False.</li>
                            <li>The template's Students sheet has headers only; see the Instructions sheet for an example row.</li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="result">
                <CardHeader class="border-b">
                    <CardTitle>Import result</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4 p-5">
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-xl bg-emerald-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Created</p>
                            <p class="mt-1 text-2xl font-bold text-emerald-900">{{ result.created }}</p>
                        </div>
                        <div class="rounded-xl bg-blue-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Updated</p>
                            <p class="mt-1 text-2xl font-bold text-blue-900">{{ result.updated }}</p>
                        </div>
                        <div class="rounded-xl bg-red-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-red-700">Failed</p>
                            <p class="mt-1 text-2xl font-bold text-red-900">{{ result.failed }}</p>
                        </div>
                        <div class="rounded-xl bg-gray-100 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Rows</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900">{{ result.rows }}</p>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500">
                        Enrollment snapshots synced to:
                        <strong>{{ result.term || 'no active term' }}</strong>
                    </p>

                    <div v-if="result.errors.length" class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-2 py-2">Row</th>
                                    <th class="px-2 py-2">Student number</th>
                                    <th class="px-2 py-2">Error</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="error in result.errors" :key="`${error.row}-${error.message}`" class="border-b last:border-0">
                                    <td class="px-2 py-2 font-mono text-xs text-gray-600">{{ error.row }}</td>
                                    <td class="px-2 py-2 font-mono text-xs text-gray-600">{{ error.student_number || '—' }}</td>
                                    <td class="px-2 py-2 text-red-700">{{ error.message }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-if="result.failed > result.errors.length" class="mt-2 text-xs text-gray-500">
                            Showing the first {{ result.errors.length }} of {{ result.failed }} errors.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div
            v-if="importing || importError"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            aria-labelledby="import-progress-title"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                <div class="flex items-center gap-3">
                    <span
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                        :class="importError ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-600'"
                    >
                        <svg v-if="!importError" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647Z"
                            />
                        </svg>
                        <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"
                            />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p id="import-progress-title" class="text-base font-semibold text-gray-900">
                            {{ importError ? 'Import failed' : 'Importing students' }}
                        </p>
                        <p class="truncate text-xs text-gray-500">{{ fileName || 'Your spreadsheet' }}</p>
                    </div>
                </div>

                <template v-if="!importError">
                    <div class="mt-5 h-2.5 w-full overflow-hidden rounded-full bg-gray-200">
                        <div class="h-full rounded-full bg-emerald-500 transition-all duration-300" :style="{ width: `${progress}%` }" />
                    </div>
                    <div class="mt-2 flex items-center justify-between text-xs text-gray-600">
                        <span>{{ totalRows > 0 ? `${processedRows} of ${totalRows} rows` : 'Reading spreadsheet...' }}</span>
                        <span class="font-semibold text-gray-900">{{ totalRows > 0 ? `${progress}%` : '' }}</span>
                    </div>
                    <p class="mt-4 text-xs text-gray-500">Keep this tab open until the import finishes.</p>
                </template>

                <template v-else>
                    <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ importError }}</p>
                    <div class="mt-4 flex justify-end">
                        <Button type="button" @click="importError = ''">Close</Button>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
