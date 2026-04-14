<script setup>
import { Head } from '@inertiajs/vue3'
import AppNavbar from '@/components/layout/AppNavbar.vue'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppFooter from '@/components/layout/AppFooter.vue'

defineProps({
    title: {
        type: String,
        default: '',
    },
})
</script>

<template>
    <div class="min-h-full flex flex-col bg-surface text-text">
        <Head :title="title" />

        <AppNavbar />

        <div class="flex flex-1">
            <AppSidebar />

            <main class="flex-1 overflow-y-auto">
                <slot />
            </main>
        </div>

        <AppFooter />
    </div>
</template>
