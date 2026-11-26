export interface ConfirmOptions {
    title: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: 'danger' | 'primary';
}

interface ConfirmState extends ConfirmOptions {
    open: boolean;
    resolve: ((value: boolean) => void) | null;
}

export function useConfirmModal() {
    const state = useState<ConfirmState>('sa_confirm_modal', () => ({
        open: false,
        title: '',
        message: '',
        confirmLabel: 'Confirmar',
        cancelLabel: 'Cancelar',
        variant: 'danger',
        resolve: null,
    }));

    function confirm(options: ConfirmOptions): Promise<boolean> {
        return new Promise((resolve) => {
            state.value = {
                open: true,
                title: options.title,
                message: options.message,
                confirmLabel: options.confirmLabel ?? 'Confirmar',
                cancelLabel: options.cancelLabel ?? 'Cancelar',
                variant: options.variant ?? 'danger',
                resolve,
            };
        });
    }

    function handleConfirm(): void {
        state.value.resolve?.(true);
        state.value.open = false;
        state.value.resolve = null;
    }

    function handleCancel(): void {
        state.value.resolve?.(false);
        state.value.open = false;
        state.value.resolve = null;
    }

    return {
        state,
        confirm,
        handleConfirm,
        handleCancel,
    };
}
