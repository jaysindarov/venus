import { ref, onUnmounted } from 'vue'
import axios from '@/lib/axios.js'

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
            } catch {
                error.value = 'Network error'
                clearInterval(pollTimer)
            }
        }, 2500)
    }

    onUnmounted(() => clearInterval(pollTimer))

    return { status, result, error, generate }
}
