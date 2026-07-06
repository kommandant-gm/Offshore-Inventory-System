<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-[#234222] sm:text-3xl">{{ title }}</h1>
            <p v-if="description" class="mt-1 max-w-3xl text-sm leading-6 text-[#5f7b5e]">{{ description }}</p>
        </div>

        <div class="flex w-full flex-wrap gap-3 lg:w-auto lg:justify-end">
            <slot />
        </div>
    </div>
</template>
