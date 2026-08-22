<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Home } from 'lucide-vue-next';

const props = defineProps<{
    status?: number;
}>();

const errorMap: Record<number, { title: string; message: string }> = {
    404: {
        title: 'Page not found',
        message: "Sorry, we couldn't find the page you're looking for. It may have been moved or deleted.",
    },
    403: {
        title: 'Forbidden',
        message: 'Sorry, you are not authorized to access this page.',
    },
    419: {
        title: 'Session expired',
        message: 'Sorry, your session has expired. Please refresh and try again.',
    },
    429: {
        title: 'Too many requests',
        message: 'Sorry, you are making too many requests to our servers.',
    },
    500: {
        title: 'Server error',
        message: 'Whoops, something went wrong on our servers.',
    },
    503: {
        title: 'Service unavailable',
        message: 'Sorry, we are doing some maintenance. Please check back soon.',
    },
};

const current = props.status ? errorMap[props.status] : errorMap[404];
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center bg-muted p-6 md:p-10">
        <div class="flex w-full max-w-md flex-col items-center gap-8 text-center">
            <Link :href="route('home')" class="flex items-center gap-2 font-medium">
                <img src="/branding/luxmap.png" alt="" class="h-9 w-9 rounded-lg object-cover" />
                <span class="text-xl font-bold text-foreground">SOMS</span>
            </Link>

            <div class="flex flex-col items-center gap-3">
                <p class="text-7xl font-bold tracking-tight text-primary">{{ status ?? 404 }}</p>
                <h1 class="text-2xl font-semibold">{{ current.title }}</h1>
                <p class="max-w-sm text-sm text-muted-foreground">{{ current.message }}</p>
            </div>

            <div class="flex items-center gap-3">
                <Button as-child variant="outline">
                    <Link href="javascript:history.back()">
                        <ArrowLeft class="size-4" />
                        Go back
                    </Link>
                </Button>
                <Button as-child>
                    <Link :href="route('home')">
                        <Home class="size-4" />
                        Back to home
                    </Link>
                </Button>
            </div>
        </div>
    </div>
</template>
