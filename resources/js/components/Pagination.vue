<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps<{
    meta: {
        current_page: number
        last_page: number
        total: number
        per_page: number
    }
    filters?: Record<string, string | undefined>
}>()

const page = usePage()

const currentPath = computed(() => {
    const url = new URL(page.url, window.location.origin)
    return url.pathname
})

const perPageOptions = [10, 30, 50, 100]

function buildQuery(pageNum: number, perPage?: number): string {
    const params = new URLSearchParams()
    const p = perPage ?? props.meta.per_page

    if (pageNum > 1) params.set('page', String(pageNum))
    if (p !== 10) params.set('per_page', String(p))

    if (props.filters) {
        Object.entries(props.filters).forEach(([key, value]) => {
            if (value) params.set(key, value)
        })
    }

    const qs = params.toString()
    return qs ? `${currentPath.value}?${qs}` : currentPath.value
}

function setPerPage(perPage: number) {
    router.get(buildQuery(1, perPage), {}, { preserveState: true, preserveScroll: true })
}
</script>

<template>
    <div v-if="meta.last_page > 1 || meta.total > 0" class="flex flex-col gap-3 border-t px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <label for="per-page" class="text-sm text-muted-foreground">Rows:</label>
            <select
                id="per-page"
                :value="meta.per_page"
                @change="setPerPage(Number(($event.target as HTMLSelectElement).value))"
                class="h-8 rounded-md border border-input bg-background px-2 py-1 text-xs"
            >
                <option v-for="n in perPageOptions" :key="n" :value="n">{{ n }}</option>
            </select>
        </div>

        <div class="flex items-center gap-4">
            <p class="text-sm text-muted-foreground">
                Page {{ meta.current_page }} of {{ meta.last_page }}
                <span class="hidden sm:inline">({{ meta.total }} total)</span>
            </p>

            <div class="flex gap-2">
                <Link
                    v-if="meta.current_page > 1"
                    :href="buildQuery(meta.current_page - 1)"
                    class="rounded-lg border px-3 py-1.5 text-sm transition-colors hover:bg-accent"
                    preserve-scroll
                >
                    Previous
                </Link>
                <Link
                    v-if="meta.current_page < meta.last_page"
                    :href="buildQuery(meta.current_page + 1)"
                    class="rounded-lg border px-3 py-1.5 text-sm transition-colors hover:bg-accent"
                    preserve-scroll
                >
                    Next
                </Link>
            </div>
        </div>
    </div>
</template>
