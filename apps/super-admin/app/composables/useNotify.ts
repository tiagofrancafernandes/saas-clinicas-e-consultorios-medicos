import { toast } from 'vue3-toastify';

export function useNotify() {
    return {
        success: (message: string) => toast.success(message),
        error: (message: string) => toast.error(message),
        info: (message: string) => toast.info(message),
        warning: (message: string) => toast.warning(message),
    };
}
