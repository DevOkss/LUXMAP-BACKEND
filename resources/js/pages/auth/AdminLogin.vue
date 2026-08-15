<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import TcgcAuthLayout from '@/layouts/auth/TcgcAuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('admin.login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <TcgcAuthLayout title="Welcome Back!" subtitle="Sign in to the LuxMap administration panel.">
        <Head title="Admin Log in" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-2">
                <Label for="email" class="text-sm font-semibold text-gray-700">Email Address</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    autofocus
                    tabindex="1"
                    autocomplete="email"
                    v-model="form.email"
                    placeholder="Enter your email"
                    class="w-full rounded-xl border border-gray-300 px-5 py-4 outline-none transition focus:ring-2 focus:ring-[#20673A]"
                />
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password" class="text-sm font-semibold text-gray-700">Password</Label>
                <div class="relative">
                    <Input
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        required
                        tabindex="2"
                        autocomplete="current-password"
                        v-model="form.password"
                        placeholder="Enter your password"
                        class="w-full rounded-xl border border-gray-300 py-4 pl-5 pr-12 outline-none transition focus:ring-2 focus:ring-[#20673A]"
                    />
                    <button
                        type="button"
                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                        @click="showPassword = !showPassword"
                        class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-gray-400 transition hover:text-gray-600 focus:outline-none"
                    >
                        <svg v-if="showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24M1 1l22 22" />
                        </svg>
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
                <InputError :message="form.errors.password" />
            </div>

            <Button type="submit" tabindex="4" :disabled="form.processing" class="w-full rounded-xl bg-[#20673A] py-4 font-semibold text-white transition duration-300 hover:bg-[#027F3B]">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                {{ form.processing ? 'Signing in...' : 'Sign In' }}
            </Button>

            <p class="text-center text-sm text-gray-500">
                Signing in as an officer?
                <Link :href="route('login')" class="font-semibold text-[#20673A] hover:underline">Use your ID number</Link>
            </p>
        </form>
    </TcgcAuthLayout>
</template>
