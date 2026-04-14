<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/components/layout/AppLayout.vue'

defineProps({
    user: {
        type: Object,
        required: true,
    },
})

const logoutForm = useForm({})

function logout() {
    logoutForm.post('/logout')
}
</script>

<template>
    <AppLayout title="Dashboard">
        <div class="p-8">
            <div class="mb-8">
                <h1 class="text-2xl font-semibold text-text">
                    Welcome back, {{ user.name }}
                </h1>
                <p class="mt-1 text-sm text-text-muted">{{ user.email }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-border bg-surface-alt p-6">
                    <p class="text-xs font-medium uppercase tracking-wider text-text-muted">Credits</p>
                    <p class="mt-2 text-3xl font-bold text-text">—</p>
                    <p class="mt-1 text-xs text-text-muted">Available this month</p>
                </div>
                <div class="rounded-xl border border-border bg-surface-alt p-6">
                    <p class="text-xs font-medium uppercase tracking-wider text-text-muted">Generations</p>
                    <p class="mt-2 text-3xl font-bold text-text">—</p>
                    <p class="mt-1 text-xs text-text-muted">All time</p>
                </div>
                <div class="rounded-xl border border-border bg-surface-alt p-6">
                    <p class="text-xs font-medium uppercase tracking-wider text-text-muted">Plan</p>
                    <p class="mt-2 text-xl font-bold text-text capitalize">Free</p>
                    <p class="mt-1 text-xs text-text-muted">Current subscription</p>
                </div>
            </div>

            <div class="mt-8">
                <button
                    @click="logout"
                    :disabled="logoutForm.processing"
                    class="rounded-lg border border-border px-4 py-2 text-sm text-text-muted
                           hover:text-text hover:border-text transition-colors"
                >
                    Sign out
                </button>
            </div>
        </div>
    </AppLayout>
</template>
