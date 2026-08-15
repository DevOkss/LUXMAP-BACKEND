<script setup lang="ts">
defineProps<{
    title?: string;
    subtitle?: string;
}>();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-[#f4f7f5] lg:grid lg:grid-cols-2">
        <div class="relative h-48 sm:h-56 lg:h-auto">
            <img
                src="/bg.webp"
                alt="Tangub City Global College"
                class="absolute inset-0 h-full w-full object-cover"
            />
            <div class="absolute inset-0 bg-black/25"></div>

            <div class="absolute bottom-4 left-4 lg:bottom-10 lg:left-10">
                <h1 class="text-xl font-bold leading-tight text-white sm:text-3xl lg:text-5xl">
                    Tangub City<br />
                    Global College
                </h1>
                <p class="mt-1.5 hidden text-sm text-yellow-300 sm:mt-3 sm:block sm:text-base lg:mt-5 lg:text-lg">
                    Light of the World.<br />
                    <span class="font-semibold">
                        Your Access to Success.
                    </span>
                    <br />
                    <span class="font-semibold">
                        Integrity | Compassion | Excellence
                    </span>
                </p>
            </div>
        </div>

        <div class="flex flex-1 items-center justify-center px-6 py-8 sm:px-8 lg:py-16">
            <div class="w-full max-w-md">
                <h2 v-if="title" class="text-4xl font-bold text-[#20673A] sm:text-5xl">
                    {{ title }}
                </h2>

                <div v-if="title" class="mb-6 mt-4 h-1 w-20 rounded-full bg-[#FFA808]"></div>

                <p v-if="subtitle" class="mb-6 text-gray-500 sm:mb-10">
                    {{ subtitle }}
                </p>

                <slot />
            </div>
        </div>
    </div>
</template>
