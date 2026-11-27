<script setup lang="ts">
import { useAppointmentsStore } from '~/stores/useAppointmentsStore';
import { usePortalSummaryStore } from '~/stores/usePortalSummaryStore';
import type { AppointmentTab, PatientAppointment } from '~/types/appointment';

const appointmentsStore = useAppointmentsStore();
const summaryStore = usePortalSummaryStore();

const cancelModalOpen = ref(false);
const appointmentToCancel = ref<PatientAppointment | null>(null);

const tabs: { key: AppointmentTab; label: string }[] = [
    { key: 'upcoming', label: 'Próximas' },
    { key: 'history', label: 'Realizadas' },
    { key: 'cancelled', label: 'Canceladas' },
];

const clinicTimezone = computed(() => summaryStore.summary?.clinic?.timezone || 'America/Sao_Paulo');
const clinicName = computed(() => summaryStore.summary?.clinic?.name || 'Clínica');
const cancellationWindowHours = computed(() => summaryStore.summary?.clinic?.cancellation_window_hours ?? 4);

onMounted(() => {
    summaryStore.fetchSummary();
    appointmentsStore.fetchAppointments('upcoming');
});

function switchTab(tab: AppointmentTab): void {
    appointmentsStore.fetchAppointments(tab);
}

function openCancelModal(appointment: PatientAppointment): void {
    appointmentToCancel.value = appointment;
    cancelModalOpen.value = true;
}

async function handleCancelConfirm(reason: string): Promise<void> {
    if (!appointmentToCancel.value) {
        return;
    }
    await appointmentsStore.cancelAppointmentOptimistic(appointmentToCancel.value.id, reason);
}
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">Minha Agenda</h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Confirme sua presença, adicione ao calendário ou cancele dentro do prazo da clínica.
                </p>
            </div>

            <UiButton
                variant="secondary"
                size="sm"
                icon="heroicons:arrow-path"
                :loading="appointmentsStore.loading"
                @click="appointmentsStore.fetchAppointments(appointmentsStore.activeTab, true)"
            >
                Atualizar
            </UiButton>
        </div>

        <div class="inline-flex rounded-lg bg-slate-100 p-1 border border-slate-200">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="px-3.5 py-1.5 rounded-md text-xs font-medium transition-colors cursor-pointer"
                :class="
                    appointmentsStore.activeTab === tab.key
                        ? 'bg-white text-slate-900 shadow-xs'
                        : 'text-slate-600 hover:text-slate-900'
                "
                @click="switchTab(tab.key)"
            >
                {{ tab.label }}
            </button>
        </div>

        <div v-if="appointmentsStore.loading" class="space-y-3">
            <AppointmentCardSkeleton v-for="i in 3" :key="i" />
        </div>

        <div v-else-if="appointmentsStore.currentItems.length > 0" class="space-y-3">
            <AppointmentCard
                v-for="appointment in appointmentsStore.currentItems"
                :key="appointment.id"
                :appointment="appointment"
                :timezone="clinicTimezone"
                :clinic-name="clinicName"
                :cancellation-window-hours="cancellationWindowHours"
                :pending="Boolean(appointmentsStore.pendingIds[appointment.id])"
                @confirm="appointmentsStore.confirmAppointmentOptimistic"
                @cancel="openCancelModal"
            />
        </div>

        <UiEmptyState
            v-else
            icon="heroicons:calendar-days"
            title="Nenhuma consulta nesta aba"
            description="Quando houver agendamentos nesta categoria, eles aparecerão aqui."
        />

        <AppointmentCancelModal
            v-model="cancelModalOpen"
            :appointment="appointmentToCancel"
            :timezone="clinicTimezone"
            @confirm="handleCancelConfirm"
        />
    </div>
</template>
