import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useCreditsStore = defineStore('credits', () => {
    // State
    const balance = ref(0)
    const limit = ref(0)

    // Getters
    const percentage = computed(() =>
        limit.value > 0 ? Math.round((balance.value / limit.value) * 100) : 0,
    )
    const isLow = computed(() => percentage.value < 20)

    // Actions
    function setBalance(amount) {
        balance.value = amount
    }

    function setLimit(amount) {
        limit.value = amount
    }

    return { balance, limit, percentage, isLow, setBalance, setLimit }
})
