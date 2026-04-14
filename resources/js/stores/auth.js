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
    function setUser(userData) {
        user.value = userData
    }

    function clearUser() {
        user.value = null
    }

    return { user, isAuthenticated, isAdmin, plan, setUser, clearUser }
})
