import { useToast as _useToast } from 'vue-toastification'

export function useToast() {
    const toast = _useToast()

    return {
        success: (msg) => toast.success(msg),
        error: (msg) => toast.error(msg),
        info: (msg) => toast.info(msg),
        warning: (msg) => toast.warning(msg),
    }
}
