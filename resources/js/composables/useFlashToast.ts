import { usePage, router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { nextTick } from 'vue'

let lastSuccess = ''
let lastError = ''

export function useFlashToast() {
    router.on('finish', () => {
        nextTick(() => {
            const flash = (usePage().props as any).flash as { success?: string | null; error?: string | null } | undefined
            if (flash?.success && flash.success !== lastSuccess) {
                lastSuccess = flash.success
                toast.success(flash.success)
            }
            if (flash?.error && flash.error !== lastError) {
                lastError = flash.error
                toast.error(flash.error)
            }
        })
    })
}
