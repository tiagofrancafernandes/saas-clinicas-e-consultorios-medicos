import { defineStore } from 'pinia';
import { usePortalService } from '~/services/portalService';
import type { PortalSummary } from '~/types/portal';

export const usePortalSummaryStore = defineStore('portalSummary', () => {
    const portalService = usePortalService();
    const notify = useNotify();

    const summary = ref<PortalSummary | null>(null);
    const loading = ref(false);
    const savingProfile = ref(false);

    async function fetchSummary(force = false): Promise<void> {
        if (summary.value && !force) {
            return;
        }
        loading.value = true;
        try {
            summary.value = await portalService.getSummary();
        } catch {
            notify.error('Não foi possível carregar o resumo do portal.');
        } finally {
            loading.value = false;
        }
    }

    async function updateProfileOptimistic(payload: { phone?: string; email?: string | null }): Promise<boolean> {
        if (!summary.value?.patient) {
            return false;
        }

        const previousPatient = { ...summary.value.patient };
        summary.value = {
            ...summary.value,
            patient: {
                ...summary.value.patient,
                ...payload,
            },
        };
        savingProfile.value = true;

        try {
            const response = await portalService.updateProfile(payload);
            if (summary.value) {
                summary.value = {
                    ...summary.value,
                    patient: response.patient,
                };
            }
            notify.success('Dados de contato atualizados.');
            return true;
        } catch (error: unknown) {
            if (summary.value) {
                summary.value = {
                    ...summary.value,
                    patient: previousPatient,
                };
            }
            const err = error as { data?: { message?: string } };
            notify.error(err.data?.message || 'Não foi possível atualizar seu perfil.');
            return false;
        } finally {
            savingProfile.value = false;
        }
    }

    function reset(): void {
        summary.value = null;
    }

    return {
        summary,
        loading,
        savingProfile,
        fetchSummary,
        updateProfileOptimistic,
        reset,
    };
});
