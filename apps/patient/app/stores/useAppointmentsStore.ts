import { defineStore } from 'pinia';
import { usePortalService } from '~/services/portalService';
import type { AppointmentTab, PatientAppointment } from '~/types/appointment';
import { usePortalSummaryStore } from './usePortalSummaryStore';

export const useAppointmentsStore = defineStore('patientAppointments', () => {
    const portalService = usePortalService();
    const summaryStore = usePortalSummaryStore();
    const notify = useNotify();

    const activeTab = ref<AppointmentTab>('upcoming');
    const itemsByTab = ref<Record<AppointmentTab, PatientAppointment[]>>({
        upcoming: [],
        history: [],
        cancelled: [],
    });
    const loadedTabs = ref<Record<AppointmentTab, boolean>>({
        upcoming: false,
        history: false,
        cancelled: false,
    });
    const loading = ref(false);
    const pendingIds = ref<Record<string, boolean>>({});

    const currentItems = computed(() => itemsByTab.value[activeTab.value]);

    async function fetchAppointments(tab: AppointmentTab = activeTab.value, force = false): Promise<void> {
        activeTab.value = tab;

        if (loadedTabs.value[tab] && !force) {
            return;
        }

        loading.value = true;
        try {
            const response = await portalService.getAppointments(tab);
            itemsByTab.value[tab] = response.data;
            loadedTabs.value[tab] = true;
        } catch {
            notify.error('Não foi possível carregar suas consultas.');
        } finally {
            loading.value = false;
        }
    }

    async function confirmAppointmentOptimistic(id: string): Promise<boolean> {
        const upcomingList = itemsByTab.value.upcoming;
        const index = upcomingList.findIndex((item) => item.id === id);
        const snapshotUpcoming = index !== -1 ? { ...upcomingList[index] } : null;
        const snapshotNext = summaryStore.summary?.next_appointment
            ? { ...summaryStore.summary.next_appointment }
            : null;

        if (index !== -1) {
            upcomingList[index] = {
                ...upcomingList[index]!,
                status: 'CONFIRMED',
                can_confirm: false,
            };
        }

        if (summaryStore.summary?.next_appointment?.id === id) {
            summaryStore.summary = {
                ...summaryStore.summary,
                next_appointment: {
                    ...summaryStore.summary.next_appointment,
                    status: 'CONFIRMED',
                    can_confirm: false,
                },
            };
        }

        pendingIds.value = { ...pendingIds.value, [id]: true };

        try {
            const response = await portalService.confirmAppointment(id);
            if (index !== -1) {
                upcomingList[index] = response.data;
            }
            if (summaryStore.summary?.next_appointment?.id === id) {
                summaryStore.summary = {
                    ...summaryStore.summary,
                    next_appointment: response.data,
                };
            }
            notify.success('Presença confirmada!');
            return true;
        } catch (error: unknown) {
            if (index !== -1 && snapshotUpcoming) {
                upcomingList[index] = snapshotUpcoming as PatientAppointment;
            }
            if (summaryStore.summary && snapshotNext?.id === id) {
                summaryStore.summary = {
                    ...summaryStore.summary,
                    next_appointment: snapshotNext,
                };
            }
            const err = error as { data?: { message?: string } };
            notify.error(err.data?.message || 'Não foi possível confirmar a consulta.');
            return false;
        } finally {
            const { [id]: _, ...rest } = pendingIds.value;
            pendingIds.value = rest;
        }
    }

    async function cancelAppointmentOptimistic(id: string, reason?: string): Promise<boolean> {
        const upcomingSnapshot = [...itemsByTab.value.upcoming];
        const cancelledSnapshot = [...itemsByTab.value.cancelled];
        const summarySnapshot = summaryStore.summary ? { ...summaryStore.summary } : null;

        const target =
            itemsByTab.value.upcoming.find((item) => item.id === id) ||
            (summaryStore.summary?.next_appointment?.id === id ? summaryStore.summary.next_appointment : null);

        itemsByTab.value.upcoming = itemsByTab.value.upcoming.filter((item) => item.id !== id);

        if (target) {
            const optimisticCancelled: PatientAppointment = {
                ...target,
                status: 'CANCELLED',
                can_cancel: false,
                can_confirm: false,
                cancellation_reason: reason || 'Cancelado pelo paciente no portal',
            };
            if (loadedTabs.value.cancelled) {
                itemsByTab.value.cancelled = [optimisticCancelled, ...itemsByTab.value.cancelled];
            }
        }

        if (summaryStore.summary) {
            const nextUpcoming = itemsByTab.value.upcoming[0] ?? null;
            summaryStore.summary = {
                ...summaryStore.summary,
                upcoming_count: Math.max(0, summaryStore.summary.upcoming_count - 1),
                next_appointment:
                    summaryStore.summary.next_appointment?.id === id
                        ? nextUpcoming
                        : summaryStore.summary.next_appointment,
            };
        }

        pendingIds.value = { ...pendingIds.value, [id]: true };

        try {
            const response = await portalService.cancelAppointment(id, reason);
            if (loadedTabs.value.cancelled) {
                itemsByTab.value.cancelled = itemsByTab.value.cancelled.map((item) =>
                    item.id === id ? response.data : item
                );
            }
            notify.success('Consulta cancelada com sucesso.');
            return true;
        } catch (error: unknown) {
            itemsByTab.value.upcoming = upcomingSnapshot;
            itemsByTab.value.cancelled = cancelledSnapshot;
            if (summarySnapshot) {
                summaryStore.summary = summarySnapshot;
            }
            const err = error as { data?: { message?: string } };
            notify.error(err.data?.message || 'Não foi possível cancelar a consulta.');
            return false;
        } finally {
            const { [id]: _, ...rest } = pendingIds.value;
            pendingIds.value = rest;
        }
    }

    return {
        activeTab,
        itemsByTab,
        currentItems,
        loading,
        pendingIds,
        fetchAppointments,
        confirmAppointmentOptimistic,
        cancelAppointmentOptimistic,
    };
});
