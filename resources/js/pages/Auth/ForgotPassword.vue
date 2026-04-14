<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import AuthLayout from '@/components/layout/AuthLayout.vue'

defineProps({
    status: { type: String, default: null },
})

const form = useForm({ email: '' })

function submit() {
    form.post('/forgot-password')
}
</script>

<template>
    <AuthLayout title="Forgot password">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-semibold text-text">Forgot your password?</h1>
            <p class="mt-2 text-sm text-text-muted">
                Enter your email and we'll send you a reset link.
            </p>
        </div>

        <p v-if="status" class="mb-4 rounded-lg bg-green-500/10 px-4 py-3 text-sm text-green-400 text-center">
            {{ status }}
        </p>

        <form @submit.prevent="submit" novalidate class="space-y-5">
            <div>
                <label for="email" class="block text-sm font-medium text-text mb-1">
                    Email address
                </label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    required
                    class="w-full rounded-lg border border-border bg-surface-alt px-4 py-2.5 text-text placeholder-text-muted
                           focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent
                           disabled:opacity-50 transition-colors"
                    :class="{ 'border-red-500': form.errors.email }"
                    placeholder="jane@example.com"
                />
                <p v-if="form.errors.email" class="mt-1 text-xs text-red-400">
                    {{ form.errors.email }}
                </p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-lg bg-brand hover:bg-brand-dark text-white font-medium py-2.5 px-4
                       transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
            >
                <span v-if="form.processing">Sending…</span>
                <span v-else>Send reset link</span>
            </button>

            <p class="text-center text-sm text-text-muted">
                <Link href="/login" class="text-brand hover:text-brand-dark transition-colors">
                    Back to sign in
                </Link>
            </p>
        </form>
    </AuthLayout>
</template>
