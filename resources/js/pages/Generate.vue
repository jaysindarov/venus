<script setup>
import { ref, computed, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '@/components/layout/AppLayout.vue'
import { useGeneration } from '@/composables/useGeneration.js'
import { useCredits } from '@/composables/useCredits.js'

const props = defineProps({
    models: { type: Array, required: true },
})

// ── State ────────────────────────────────────────────────────────────────────
const selectedModel = ref(props.models[0]?.slug ?? 'dall-e-3')
const prompt        = ref('')
const negativePrompt = ref('')
const aspectRatio   = ref('1:1')

const model = computed(() => props.models.find(m => m.slug === selectedModel.value))

const creditCost = computed(() => {
    if (!model.value) return 2
    const map = model.value.credits_map
    const defaultCost = Object.values(map)[0]
    return defaultCost ?? 2
})

// ── Generation ───────────────────────────────────────────────────────────────
const { status, result, error, generate } = useGeneration()
const { balance } = useCredits()

const page = usePage()

// Sync credits from shared Inertia data on first load
if (page.props.auth?.credits != null) {
    // useCreditsStore will pick this up via the store
}

const canGenerate = computed(() =>
    prompt.value.trim().length > 0 &&
    status.value !== 'submitting' &&
    status.value !== 'queued' &&
    status.value !== 'processing',
)

const isGenerating = computed(() =>
    ['submitting', 'queued', 'processing'].includes(status.value),
)

async function submit() {
    await generate({
        model:           selectedModel.value,
        prompt:          prompt.value,
        negative_prompt: negativePrompt.value || undefined,
        aspect_ratio:    aspectRatio.value,
    })
}

// Reset aspect ratio when model changes
watch(selectedModel, () => {
    if (!model.value?.aspect_ratios.includes(aspectRatio.value)) {
        aspectRatio.value = model.value?.aspect_ratios[0] ?? '1:1'
    }
})
</script>

<template>
    <AppLayout title="Generate">
        <div class="flex h-full">

            <!-- ── Left panel: controls ─────────────────────────────────── -->
            <div class="w-80 shrink-0 border-r border-border bg-surface-alt flex flex-col overflow-y-auto">
                <div class="p-6 space-y-6 flex-1">

                    <!-- Model selector -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted mb-2">
                            Model
                        </label>
                        <div class="space-y-2">
                            <button
                                v-for="m in models"
                                :key="m.slug"
                                @click="selectedModel = m.slug"
                                :class="[
                                    'w-full text-left rounded-lg border px-3 py-2.5 transition-colors',
                                    selectedModel === m.slug
                                        ? 'border-brand bg-brand/10 text-text'
                                        : 'border-border text-text-muted hover:border-brand/50 hover:text-text'
                                ]"
                            >
                                <div class="text-sm font-medium">{{ m.name }}</div>
                                <div class="text-xs opacity-70 mt-0.5">{{ m.description }}</div>
                            </button>
                        </div>
                    </div>

                    <!-- Aspect ratio -->
                    <div v-if="model?.aspect_ratios.length">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted mb-2">
                            Aspect Ratio
                        </label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="ratio in model.aspect_ratios"
                                :key="ratio"
                                @click="aspectRatio = ratio"
                                :class="[
                                    'rounded-md border px-3 py-1 text-xs font-medium transition-colors',
                                    aspectRatio === ratio
                                        ? 'border-brand bg-brand text-white'
                                        : 'border-border text-text-muted hover:border-brand/50'
                                ]"
                            >
                                {{ ratio }}
                            </button>
                        </div>
                    </div>

                    <!-- Credit cost -->
                    <div class="rounded-lg border border-border bg-surface px-3 py-2 flex items-center justify-between text-sm">
                        <span class="text-text-muted">Cost</span>
                        <span class="font-semibold text-text">{{ creditCost }} credit{{ creditCost !== 1 ? 's' : '' }}</span>
                    </div>
                </div>

                <!-- Generate button -->
                <div class="p-6 border-t border-border">
                    <button
                        @click="submit"
                        :disabled="!canGenerate"
                        class="w-full rounded-lg bg-brand hover:bg-brand-dark text-white font-semibold
                               py-3 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span v-if="isGenerating">Generating…</span>
                        <span v-else>Generate</span>
                    </button>
                </div>
            </div>

            <!-- ── Right panel: prompt + result ─────────────────────────── -->
            <div class="flex-1 flex flex-col overflow-hidden">

                <!-- Prompt -->
                <div class="p-6 border-b border-border space-y-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted mb-2">
                            Prompt
                        </label>
                        <textarea
                            v-model="prompt"
                            rows="3"
                            placeholder="Describe the image you want to generate…"
                            maxlength="1000"
                            class="w-full rounded-lg border border-border bg-surface-alt px-4 py-3 text-text
                                   placeholder-text-muted resize-none focus:outline-none focus:ring-2
                                   focus:ring-brand focus:border-transparent transition-colors"
                        />
                        <div class="mt-1 text-right text-xs text-text-muted">{{ prompt.length }}/1000</div>
                    </div>

                    <div v-if="model?.supports_negative_prompt">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted mb-2">
                            Negative Prompt
                            <span class="ml-1 font-normal normal-case">(optional)</span>
                        </label>
                        <textarea
                            v-model="negativePrompt"
                            rows="2"
                            placeholder="What to avoid in the image…"
                            maxlength="500"
                            class="w-full rounded-lg border border-border bg-surface-alt px-4 py-3 text-text
                                   placeholder-text-muted resize-none focus:outline-none focus:ring-2
                                   focus:ring-brand focus:border-transparent transition-colors"
                        />
                    </div>
                </div>

                <!-- Result area -->
                <div class="flex-1 flex items-center justify-center p-8 overflow-y-auto">

                    <!-- Idle -->
                    <div v-if="!status && !result" class="text-center text-text-muted">
                        <div class="mx-auto mb-4 h-20 w-20 rounded-2xl border-2 border-dashed border-border
                                    flex items-center justify-center">
                            <svg class="h-10 w-10 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828
                                         0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2
                                         2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <p class="text-sm">Your generated image will appear here</p>
                    </div>

                    <!-- Generating -->
                    <div v-else-if="isGenerating" class="text-center">
                        <div class="mx-auto mb-4 h-20 w-20 rounded-2xl border-2 border-brand/30
                                    flex items-center justify-center animate-pulse">
                            <svg class="h-10 w-10 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828
                                         0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2
                                         2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <p class="text-sm text-text-muted capitalize">{{ status }}…</p>
                    </div>

                    <!-- Error -->
                    <div v-else-if="error" class="text-center">
                        <p class="text-sm text-red-400">{{ error }}</p>
                        <button @click="submit" class="mt-3 text-sm text-brand hover:underline">
                            Try again
                        </button>
                    </div>

                    <!-- Success -->
                    <div v-else-if="result?.image_url" class="max-w-2xl w-full">
                        <img
                            :src="result.image_url"
                            :alt="prompt"
                            class="w-full rounded-2xl shadow-2xl"
                        />
                        <div class="mt-4 flex gap-3 justify-center">
                            <a
                                :href="result.image_url"
                                download
                                class="rounded-lg border border-border px-4 py-2 text-sm text-text-muted
                                       hover:text-text transition-colors"
                            >
                                Download
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
