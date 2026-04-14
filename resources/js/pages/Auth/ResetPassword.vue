<script setup>
import { useForm } from '@inertiajs/vue3'
import AuthLayout from '@/components/layout/AuthLayout.vue'

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, required: true },
})

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
})

function submit() {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <AuthLayout title="Reset password">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-semibold text-text">Set new password</h1>
            <p class="mt-2 text-sm text-text-muted">
                Choose a strong password for your account.
            </p>
        </div>

        <form @submit.prevent="submit" novalidate class="space-y-5">
            <!-- Email (hidden but displayed for clarity) -->
            <div>
                <label for="email" class="block text-sm font-medium text-text mb-1">
                    Email address
                </label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    readonly
                    class="w-full rounded-lg border border-border bg-surface-alt px-4 py-2.5 text-text-muted
                           focus:outline-none opacity-60 cursor-not-allowed"
                />
            </div>

            <!-- New password -->
            <div>
                <label for="password" class="block text-sm font-medium text-text mb-1">
                    New password
                </label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    required
                    class="w-full rounded-lg border border-border bg-surface-alt px-4 py-2.5 text-text placeholder-text-muted
                           focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent
                           disabled:opacity-50 transition-colors"
                    :class="{ 'border-red-500': form.errors.password }"
                    placeholder="Min. 8 characters"
                />
                <p v-if="form.errors.password" class="mt-1 text-xs text-red-400">
                    {{ form.errors.password }}
                </p>
            </div>

            <!-- Confirm password -->
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-text mb-1">
                    Confirm password
                </label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                    class="w-full rounded-lg border border-border bg-surface-alt px-4 py-2.5 text-text placeholder-text-muted
                           focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent
                           disabled:opacity-50 transition-colors"
                    :class="{ 'border-red-500': form.errors.password_confirmation }"
                    placeholder="Repeat your password"
                />
                <p v-if="form.errors.password_confirmation" class="mt-1 text-xs text-red-400">
                    {{ form.errors.password_confirmation }}
                </p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-lg bg-brand hover:bg-brand-dark text-white font-medium py-2.5 px-4
                       transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
            >
                <span v-if="form.processing">Resetting…</span>
                <span v-else>Reset password</span>
            </button>
        </form>
    </AuthLayout>
</template>
