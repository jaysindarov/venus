import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from '@/lib/axios.js'

export const useGenerationStore = defineStore('generation', () => {
    // State
    const activeJob = ref(null) // { id, status, pollInterval }
    const generations = ref([])

    // Getters
    const isGenerating = computed(() => activeJob.value?.status === 'processing')

    // Actions
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
