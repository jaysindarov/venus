<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import AuthLayout from '@/components/layout/AuthLayout.vue'

defineProps({
    status: { type: String, default: null },
})

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <AuthLayout title="Sign in">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-semibold text-text">Welcome back</h1>
            <p class="mt-2 text-sm text-text-muted">
                Don't have an account?
                <Link href="/register" class="text-brand hover:text-brand-dark transition-colors">
                    Sign up
                </Link>
            </p>
        </div>

        <p v-if="status" class="mb-4 rounded-lg bg-green-500/10 px-4 py-3 text-sm text-green-400 text-center">
            {{ status }}
        </p>

        <form @submit.prevent="submit" novalidate class="space-y-5">
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
                <div class="flex items-center justify-between mb-1">
                    <label for="password" class="block text-sm font-medium text-text">
                        Password
                    </label>
                    <Link href="/forgot-password" class="text-xs text-brand hover:text-brand-dark transition-colors">
                        Forgot password?
                    </Link>
                </div>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="w-full rounded-lg border border-border bg-surface-alt px-4 py-2.5 text-text placeholder-text-muted
                           focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent
                           disabled:opacity-50 transition-colors"
                    :class="{ 'border-red-500': form.errors.password }"
                    placeholder="Your password"
                />
                <p v-if="form.errors.password" class="mt-1 text-xs text-red-400">
                    {{ form.errors.password }}
                </p>
            </div>

            <!-- Remember me -->
            <div class="flex items-center gap-2">
                <input
                    id="remember"
                    v-model="form.remember"
                    type="checkbox"
                    class="h-4 w-4 rounded border-border text-brand focus:ring-brand"
                />
                <label for="remember" class="text-sm text-text-muted">Remember me</label>
            </div>

            <!-- Submit -->
            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-lg bg-brand hover:bg-brand-dark text-white font-medium py-2.5 px-4
                       transition-colors disabled:opacity-60 disabled:cursor-not-allowed focus:outline-none
                       focus:ring-2 focus:ring-brand focus:ring-offset-2 focus:ring-offset-surface"
            >
                <span v-if="form.processing">Signing in…</span>
                <span v-else>Sign in</span>
            </button>

            <!-- Google OAuth -->
            <div class="relative my-2">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-border" />
                </div>
                <div class="relative flex justify-center text-xs">
                    <span class="bg-surface px-2 text-text-muted">or continue with</span>
                </div>
            </div>

            <a
                href="/auth/google/redirect"
                class="flex w-full items-center justify-center gap-3 rounded-lg border border-border bg-surface-alt
                       px-4 py-2.5 text-sm font-medium text-text hover:bg-surface transition-colors"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Google
            </a>
        </form>
    </AuthLayout>
</template>
