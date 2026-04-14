# Frontend Guide

## Stack
- **Vue 3** (Composition API + `<script setup>`)
- **Inertia.js** — server-driven routing, no separate API for page loads
- **Pinia** — global state management
- **Tailwind CSS** — utility-first styling
- **Vite** — build tool

---

## Project Structure

```
resources/js/
├── app.js                     # Inertia bootstrap
├── bootstrap.js               # Axios, Echo setup
├── components/
│   ├── ui/                    # Generic, reusable primitives
│   │   ├── AppButton.vue
│   │   ├── AppInput.vue
│   │   ├── AppModal.vue
│   │   ├── AppBadge.vue
│   │   ├── AppSpinner.vue
│   │   └── AppDropdown.vue
│   ├── generation/
│   │   ├── GeneratorForm.vue  # Main prompt form
│   │   ├── ModelSelector.vue
│   │   ├── StylePresetPicker.vue
│   │   ├── GenerationCard.vue # Gallery thumbnail card
│   │   ├── GenerationModal.vue # Full-size image modal
│   │   └── StatusBadge.vue
│   ├── gallery/
│   │   ├── GalleryGrid.vue    # Masonry/grid layout
│   │   └── InfiniteScroll.vue
│   ├── billing/
│   │   ├── PricingCard.vue
│   │   ├── CreditMeter.vue    # Credits remaining widget
│   │   └── PlanBadge.vue
│   └── layout/
│       ├── AppLayout.vue      # Main authenticated layout
│       ├── AuthLayout.vue     # Auth pages layout
│       ├── AppSidebar.vue
│       ├── AppNavbar.vue
│       └── AppFooter.vue
├── pages/
│   ├── Auth/
│   │   ├── Login.vue
│   │   ├── Register.vue
│   │   ├── ForgotPassword.vue
│   │   └── ResetPassword.vue
│   ├── Dashboard.vue
│   ├── Generate.vue
│   ├── Gallery.vue
│   ├── GenerationDetail.vue
│   ├── Explore.vue            # Phase 2
│   ├── Profile/
│   │   ├── Settings.vue
│   │   └── ApiTokens.vue
│   ├── Billing/
│   │   ├── Plans.vue
│   │   └── History.vue
│   └── Admin/
│       ├── Dashboard.vue
│       ├── Users.vue
│       └── Generations.vue
├── stores/
│   ├── auth.js
│   ├── generation.js
│   └── credits.js
├── composables/
│   ├── useGeneration.js
│   ├── useCredits.js
│   ├── useInfiniteGallery.js
│   └── useToast.js
└── lib/
    ├── axios.js
    └── utils.js
```

---

## Coding Conventions

### Component Style
Always use `<script setup>` (Composition API). No Options API.

```vue
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useGenerationStore } from '@/stores/generation'

// Props at the top
const props = defineProps({
  generationId: { type: String, required: true }
})

// Emits
const emit = defineEmits(['downloaded', 'deleted'])

// Store
const generationStore = useGenerationStore()

// State
const isLoading = ref(false)

// Computed
const generation = computed(() => generationStore.find(props.generationId))

// Methods
async function handleDownload() {
  isLoading.value = true
  try {
    await generationStore.download(props.generationId)
    emit('downloaded')
  } finally {
    isLoading.value = false
  }
}
</script>
```

### Naming Conventions
- **Components**: PascalCase (`GenerationCard.vue`)
- **Pages**: PascalCase, matches route name
- **Composables**: camelCase, `use` prefix (`useGeneration.js`)
- **Stores**: camelCase, `use` prefix (`useGenerationStore`)
- **Props**: camelCase in JS, kebab-case in template (`generationId` / `:generation-id`)

---

## Pinia Stores

### Auth Store (`stores/auth.js`)
```js
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref(null)

  // Getters
  const isAuthenticated = computed(() => !!user.value)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const plan = computed(() => user.value?.subscription?.plan ?? 'free')

  // Actions
  function setUser(userData) { user.value = userData }
  function clearUser() { user.value = null }

  return { user, isAuthenticated, isAdmin, plan, setUser, clearUser }
})
```

### Generation Store (`stores/generation.js`)
```js
// Key responsibilities:
// - Store current generation job (id, status, result)
// - Handle polling loop
// - Store recent generations list

export const useGenerationStore = defineStore('generation', () => {
  const activeJob = ref(null)        // { id, status, pollInterval }
  const generations = ref([])
  const isGenerating = computed(() => activeJob.value?.status === 'processing')

  async function submitGeneration(params) {
    const { data } = await axios.post('/api/v1/generations', params)
    activeJob.value = { id: data.data.id, status: 'queued' }
    startPolling(data.data.id)
  }

  function startPolling(id) {
    const interval = setInterval(async () => {
      const { data } = await axios.get(`/api/v1/generations/${id}/status`)
      activeJob.value = { ...activeJob.value, ...data.data }
      if (['completed', 'failed'].includes(data.data.status)) {
        clearInterval(interval)
        if (data.data.status === 'completed') {
          generations.value.unshift(data.data)
        }
      }
    }, 2500)
  }

  return { activeJob, generations, isGenerating, submitGeneration }
})
```

---

## Inertia.js Usage

### Page Component with Inertia Props
```vue
<!-- pages/Gallery.vue -->
<script setup>
import { usePage } from '@inertiajs/vue3'

// Props passed from Laravel controller
const props = defineProps({
  generations: Object,   // Paginated generations
  filters: Object
})
</script>
```

### Navigation
```vue
<script setup>
import { router, Link } from '@inertiajs/vue3'

// Programmatic navigation
function goToDashboard() {
  router.visit('/dashboard')
}

// With Inertia progress bar
router.visit('/generate', { preserveScroll: true })
</script>

<template>
  <!-- Inertia link (no full page reload) -->
  <Link href="/gallery">My Gallery</Link>
</template>
```

### Form Submission with Inertia
```vue
<script setup>
import { useForm } from '@inertiajs/vue3'

const form = useForm({
  name: '',
  email: '',
})

function submit() {
  form.post('/profile', {
    onSuccess: () => form.reset()
  })
}
</script>

<template>
  <form @submit.prevent="submit">
    <input v-model="form.name" />
    <span v-if="form.errors.name">{{ form.errors.name }}</span>
    <button :disabled="form.processing">Save</button>
  </form>
</template>
```

---

## Generation Polling Composable

```js
// composables/useGeneration.js
import { ref, onUnmounted } from 'vue'
import axios from '@/lib/axios'

export function useGeneration() {
  const status = ref(null)
  const result = ref(null)
  const error = ref(null)
  let pollTimer = null

  async function generate(params) {
    status.value = 'submitting'
    error.value = null

    const { data } = await axios.post('/api/v1/generations', params)
    status.value = 'queued'
    poll(data.data.id)
  }

  function poll(id) {
    pollTimer = setInterval(async () => {
      try {
        const { data } = await axios.get(`/api/v1/generations/${id}/status`)
        status.value = data.data.status

        if (data.data.status === 'completed') {
          result.value = data.data
          clearInterval(pollTimer)
        } else if (data.data.status === 'failed') {
          error.value = 'Generation failed. Credits refunded.'
          clearInterval(pollTimer)
        }
      } catch (e) {
        error.value = 'Network error'
        clearInterval(pollTimer)
      }
    }, 2500)
  }

  onUnmounted(() => clearInterval(pollTimer))

  return { status, result, error, generate }
}
```

---

## Tailwind Design System

Define custom CSS variables in `app.css`:
```css
:root {
  --color-brand: #6366f1;        /* Indigo */
  --color-brand-dark: #4f46e5;
  --color-surface: #0f0f13;      /* Dark background */
  --color-surface-alt: #1a1a24;
  --color-border: #2a2a3a;
  --color-text: #e8e8f0;
  --color-text-muted: #8888aa;
}
```

Extend `tailwind.config.js`:
```js
module.exports = {
  theme: {
    extend: {
      colors: {
        brand: 'var(--color-brand)',
        'brand-dark': 'var(--color-brand-dark)',
        surface: 'var(--color-surface)',
        'surface-alt': 'var(--color-surface-alt)',
        border: 'var(--color-border)',
      }
    }
  }
}
```

---

## Key UI Patterns

### Credit Meter Component
Always show remaining credits in the sidebar. Warn visually when < 20%.

```vue
<!-- components/billing/CreditMeter.vue -->
<template>
  <div class="credit-meter">
    <div class="flex justify-between text-sm mb-1">
      <span>Credits</span>
      <span :class="isLow ? 'text-red-400' : 'text-brand'">{{ balance }} remaining</span>
    </div>
    <div class="h-2 bg-surface-alt rounded-full overflow-hidden">
      <div
        class="h-full rounded-full transition-all"
        :class="isLow ? 'bg-red-500' : 'bg-brand'"
        :style="{ width: `${percentage}%` }"
      />
    </div>
  </div>
</template>
```

### Generation Status Flow
```
[idle] → submit → [submitting] → job accepted → [queued] → worker picks up → [processing] → done → [completed | failed]
```
Show a skeleton/spinner with estimated time during `processing`.
