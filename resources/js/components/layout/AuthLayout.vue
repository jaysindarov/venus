<script setup>
import { Head } from '@inertiajs/vue3'

defineProps({
    title: {
        type: String,
        default: '',
    },
})
</script>

<template>
    <div class="min-h-full flex items-center justify-center bg-surface text-text">
        <Head :title="title" />

        <div class="w-full max-w-md px-4 py-8">
            <slot />
        </div>
    </div>
</template>
