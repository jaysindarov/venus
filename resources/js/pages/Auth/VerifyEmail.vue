<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AuthLayout from '@/components/layout/AuthLayout.vue'

const props = defineProps({
    status: { type: String, default: null },
})

const form = useForm({})

const justSent = computed(() => props.status === 'verification-link-sent')

function resend() {
    form.post('/email/verification-notification')
}
</script>

<template>
    <AuthLayout title="Verify email">
        <div class="text-center">
            <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-brand/10">
                <svg class="h-8 w-8 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>

            <h1 class="text-2xl font-semibold text-text">Check your email</h1>
            <p class="mt-3 text-sm text-text-muted leading-relaxed">
                We sent a verification link to your email address.
                Click the link to activate your account.
            </p>

            <p v-if="justSent" class="mt-4 rounded-lg bg-green-500/10 px-4 py-3 text-sm text-green-400">
                A new verification link has been sent.
            </p>

            <div class="mt-6 space-y-3">
                <form @submit.prevent="resend">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full rounded-lg bg-brand hover:bg-brand-dark text-white font-medium py-2.5 px-4
                               transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <span v-if="form.processing">Sending…</span>
                        <span v-else>Resend verification email</span>
                    </button>
                </form>

                <form method="POST" action="/logout">
                    <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                    <button
                        type="submit"
                        class="w-full rounded-lg border border-border bg-transparent text-text-muted
                               hover:text-text font-medium py-2.5 px-4 transition-colors text-sm"
                    >
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </AuthLayout>
</template>
