import { toast } from 'vue3-toastify';

export function useNotify() {
    return {
        success(message: string): void {
            toast.success(message);
        },
        error(message: string): void {
            toast.error(message);
        },
        info(message: string): void {
            toast.info(message);
        },
    };
}
