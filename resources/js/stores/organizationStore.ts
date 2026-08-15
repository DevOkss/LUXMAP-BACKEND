import { defineStore } from 'pinia'
import { router } from '@inertiajs/vue3'

export interface Organization {
    id: number
    parent_id: number | null
    name: string
    code: string
    type: 'ssc' | 'isc' | 'sro'
    description: string | null
    config: Record<string, unknown> | null
    is_active: boolean
    parent?: Organization | null
    children?: Organization[]
    created_at: string
    updated_at: string
}

export const useOrganizationStore = defineStore('organization', {
    state: () => ({
        organizations: [] as Organization[],
        loading: false,
    }),

    actions: {
        fetchOrganizations() {
            this.loading = true
            router.get('/admin/organizations', {}, {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => {
                    this.loading = false
                },
            })
        },

        async deleteOrganization(id: number) {
            router.delete(`/admin/organizations/${id}`, {
                preserveScroll: true,
            })
        },
    },
})
