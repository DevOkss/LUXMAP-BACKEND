import { defineStore } from 'pinia'
import { router } from '@inertiajs/vue3'

export interface Event {
    id: number
    organization_id: number
    title: string
    description: string | null
    type: string
    venue: string | null
    latitude: number | null
    longitude: number | null
    geofence_radius: number | null
    attendance_start: string
    attendance_end: string
    event_date: string
    max_participants: number | null
    status: string
    created_at: string
    updated_at: string
}

export const useEventStore = defineStore('event', {
    state: () => ({
        events: [] as Event[],
        loading: false,
    }),

    actions: {
        deleteEvent(id: number) {
            router.delete(`/admin/events/${id}`, { preserveScroll: true })
        },
    },
})
