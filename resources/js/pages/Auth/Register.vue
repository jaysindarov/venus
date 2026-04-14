<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import AuthLayout from '@/components/layout/AuthLayout.vue'

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

function submit() {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <AuthLayout title="Create account">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-semibold text-text">Create your account</h1>
            <p class="mt-2 text-sm text-text-muted">
                Already have an account?
                <Link href="/login" class="text-brand hover:text-brand-dark transition-colors">
                    Sign in
                </Link>
            </p>
        </div>

        <form @submit.prevent="submit" novalidate class="space-y-5">
            <!-- Name -->
            <div>
                <label for="name" class="block text-sm font-medium text-text mb-1">
                    Full name
                </label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    autocomplete="name"
                    required
                    class="w-full rounded-lg border border-border bg-surface-alt px-4 py-2.5 text-text placeholder-text-muted
                           focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent
                           disabled:opacity-50 transition-colors"
                    :class="{ 'border-red-500': form.errors.name }"
                    placeholder="Jane Doe"
                />
                <p v-if="form.errors.name" class="mt-1 text-xs text-red-400">
                    {{ form.errors.name }}
                </p>
            </div>

            <!-- Email -->
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

            <!-- Password -->
            <div>
                <label for="password" class="block text-sm font-medium text-text mb-1">
                    Password
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

            <!-- Submit -->
            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-lg bg-brand hover:bg-brand-dark text-white font-medium py-2.5 px-4
                       transition-colors disabled:opacity-60 disabled:cursor-not-allowed focus:outline-none
                       focus:ring-2 focus:ring-brand focus:ring-offset-2 focus:ring-offset-surface"
            >
                <span v-if="form.processing">Creating account…</span>
                <span v-else>Create account</span>
            </button>
        </form>
    </AuthLayout>
</template>
