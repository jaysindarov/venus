<script setup>
import { ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/components/layout/AppLayout.vue'

const props = defineProps({
    plans:       { type: Array,   required: true },
    currentPlan: { type: String,  default: null },
    onTrial:     { type: Boolean, default: false },
    subscribed:  { type: Boolean, default: false },
})

const billing = ref('monthly') // 'monthly' | 'yearly'

const portalForm = useForm({})
const cancelForm = useForm({})

function openPortal() {
    portalForm.post('/billing/portal')
}

function cancelSubscription() {
    if (confirm('Cancel your subscription? You keep access until the end of the billing period.')) {
        cancelForm.post('/billing/cancel')
    }
}

function yearlyPrice(plan) {
    if (plan.yearly_price == 0) return null
    return (plan.yearly_price / 12).toFixed(2)
}

function savings(plan) {
    if (!plan.monthly_price || !plan.yearly_price) return null
    const annualMonthly = plan.monthly_price * 12
    const saved = annualMonthly - plan.yearly_price
    return Math.round((saved / annualMonthly) * 100)
}
</script>

<template>
    <AppLayout title="Plans & Pricing">
        <div class="mx-auto max-w-6xl px-6 py-12">
            <div class="text-center mb-10">
                <h1 class="text-3xl font-bold text-text">Plans & Pricing</h1>
                <p class="mt-3 text-text-muted">Start free. Upgrade when you need more.</p>

                <!-- Billing toggle -->
                <div class="mt-6 inline-flex items-center gap-3 rounded-full border border-border bg-surface-alt p-1">
                    <button
                        @click="billing = 'monthly'"
                        :class="[
                            'rounded-full px-5 py-1.5 text-sm font-medium transition-colors',
                            billing === 'monthly'
                                ? 'bg-brand text-white'
                                : 'text-text-muted hover:text-text'
                        ]"
                    >
                        Monthly
                    </button>
                    <button
                        @click="billing = 'yearly'"
                        :class="[
                            'rounded-full px-5 py-1.5 text-sm font-medium transition-colors',
                            billing === 'yearly'
                                ? 'bg-brand text-white'
                                : 'text-text-muted hover:text-text'
                        ]"
                    >
                        Yearly
                        <span class="ml-1 text-xs text-green-400">Save up to 30%</span>
                    </button>
                </div>
            </div>

            <!-- Plan grid -->
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="plan in plans"
                    :key="plan.slug"
                    :class="[
                        'rounded-2xl border p-6 flex flex-col',
                        plan.slug === 'pro'
                            ? 'border-brand bg-brand/5 relative'
                            : 'border-border bg-surface-alt'
                    ]"
                >
                    <!-- Popular badge -->
                    <div
                        v-if="plan.slug === 'pro'"
                        class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-brand px-4 py-1 text-xs font-semibold text-white"
                    >
                        Most Popular
                    </div>

                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-text">{{ plan.name }}</h2>

                        <div class="mt-3">
                            <template v-if="plan.monthly_price == 0">
                                <span class="text-4xl font-bold text-text">Free</span>
                            </template>
                            <template v-else-if="billing === 'yearly' && plan.yearly_price > 0">
                                <span class="text-4xl font-bold text-text">${{ yearlyPrice(plan) }}</span>
                                <span class="text-text-muted text-sm">/mo</span>
                                <div class="mt-1 text-xs text-green-400">
                                    ${{ plan.yearly_price }}/year · Save {{ savings(plan) }}%
                                </div>
                            </template>
                            <template v-else>
                                <span class="text-4xl font-bold text-text">${{ plan.monthly_price }}</span>
                                <span class="text-text-muted text-sm">/mo</span>
                            </template>
                        </div>

                        <p class="mt-3 text-sm text-text-muted">
                            <span class="font-medium text-text">{{ plan.monthly_credits.toLocaleString() }}</span>
                            credits/month
                        </p>
                    </div>

                    <!-- Features -->
                    <ul class="mb-6 space-y-2 flex-1 text-sm text-text-muted">
                        <li class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-green-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            {{ plan.features.models.length }} AI model{{ plan.features.models.length !== 1 ? 's' : '' }}
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-green-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Up to {{ plan.max_resolution }}px
                        </li>
                        <li v-if="plan.features.priority_queue" class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-green-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Priority queue
                        </li>
                        <li v-if="plan.features.api_access" class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-green-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            API access
                        </li>
                        <li v-if="plan.features.collections" class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-green-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Collections
                        </li>
                    </ul>

                    <!-- CTA -->
                    <template v-if="plan.isFree || plan.monthly_price == 0">
                        <div class="rounded-lg border border-border py-2.5 text-center text-sm text-text-muted">
                            Free forever
                        </div>
                    </template>
                    <template v-else-if="subscribed">
                        <button
                            @click="openPortal"
                            :disabled="portalForm.processing"
                            class="w-full rounded-lg border border-brand py-2.5 text-sm font-medium text-brand
                                   hover:bg-brand hover:text-white transition-colors disabled:opacity-60"
                        >
                            Manage subscription
                        </button>
                    </template>
                    <template v-else>
                        <a
                            :href="`/billing/checkout?plan=${plan.slug}&billing=${billing}`"
                            class="block w-full rounded-lg bg-brand py-2.5 text-center text-sm font-medium text-white
                                   hover:bg-brand-dark transition-colors"
                            :class="{ 'bg-brand': plan.slug === 'pro', 'bg-surface': plan.slug !== 'pro' }"
                        >
                            Get started
                        </a>
                    </template>
                </div>
            </div>

            <!-- Cancel link -->
            <div v-if="subscribed" class="mt-10 text-center">
                <button
                    @click="cancelSubscription"
                    :disabled="cancelForm.processing"
                    class="text-sm text-text-muted hover:text-red-400 transition-colors"
                >
                    Cancel subscription
                </button>
            </div>
        </div>
    </AppLayout>
</template>
