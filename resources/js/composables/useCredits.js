import { useCreditsStore } from '@/stores/credits.js'
import { storeToRefs } from 'pinia'

export function useCredits() {
    const creditsStore = useCreditsStore()
    const { balance, limit, percentage, isLow } = storeToRefs(creditsStore)

    return { balance, limit, percentage, isLow }
}
