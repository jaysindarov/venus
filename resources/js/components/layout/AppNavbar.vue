<script setup>
import { computed } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'

const page    = usePage()
const user    = computed(() => page.props.auth?.user)
const credits = computed(() => page.props.auth?.credits ?? 0)

const logoutForm = useForm({})

function logout() {
    logoutForm.post('/logout')
}
</script>

<template>
    <header class="h-16 shrink-0 border-b border-border bg-surface-alt flex items-center px-6 gap-4">
        <Link href="/dashboard" class="font-bold text-lg text-text tracking-tight">
            Venus<span class="text-brand">AI</span>
        </Link>

        <div class="flex-1" />

        <!-- Credits -->
        <div class="flex items-center gap-1.5 rounded-full border border-border bg-surface px-3 py-1 text-sm">
            <span class="font-semibold text-text">{{ credits.toLocaleString() }}</span>
            <span class="text-text-muted">credits</span>
        </div>

        <!-- User -->
        <div v-if="user" class="flex items-center gap-3">
            <span class="hidden sm:block text-sm text-text-muted">{{ user.name }}</span>
            <button
                @click="logout"
                :disabled="logoutForm.processing"
                class="text-sm text-text-muted hover:text-text transition-colors"
            >
                Sign out
            </button>
        </div>
    </header>
</template>
