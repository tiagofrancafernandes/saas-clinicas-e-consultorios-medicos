<script setup lang="ts">
import { useAppointmentsStore } from '~/stores/useAppointmentsStore';
import { usePortalSummaryStore } from '~/stores/usePortalSummaryStore';
import type { PatientAppointment } from '~/types/appointment';

const { user } = usePatientAuth();
const summaryStore = usePortalSummaryStore();
const appointmentsStore = useAppointmentsStore();

const cancelModalOpen = ref(false);
const appointmentToCancel = ref<PatientAppointment | null>(null);

const firstName = computed(() => {
    const name = summaryStore.summary?.patient?.name || user.value?.name || 'Paciente';
    return name.split(' ')[0];
});

const clinicTimezone = computed(() => summaryStore.summary?.clinic?.timezone || 'America/Sao_Paulo');
const clinicName = computed(() => summaryStore.summary?.clinic?.name || 'Clínica');
const cancellationWindowHours = computed(() => summaryStore.summary?.clinic?.cancellation_window_hours ?? 4);

onMounted(() => {
    summaryStore.fetchSummary();
});

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
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">Olá, {{ firstName }}</h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Aqui está o resumo dos seus atendimentos e documentos na {{ clinicName }}.
                </p>
            </div>
        </div>

        <div
            v-if="!summaryStore.loading && summaryStore.summary && !summaryStore.summary.patient"
            class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-xs text-amber-800 flex items-start gap-2.5"
        >
            <Icon name="heroicons:information-circle" class="w-4 h-4 shrink-0 mt-0.5" />
            <div>
                <p class="font-medium">Ficha de paciente não vinculada</p>
                <p class="mt-0.5 text-amber-700">
                    Sua conta ainda não está vinculada a um prontuário nesta clínica. Caso precise de ajuda, entre em
                    contato com a recepção.
                </p>
            </div>
        </div>

        <div v-if="summaryStore.loading" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <UiCard v-for="i in 2" :key="i">
                <div class="flex items-center justify-between">
                    <div class="space-y-2">
                        <UiSkeleton width-class="w-28" height-class="h-3" />
                        <UiSkeleton width-class="w-12" height-class="h-7" />
                    </div>
                    <UiSkeleton width-class="w-10" height-class="h-10" rounded-class="rounded-xl" />
                </div>
            </UiCard>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <NuxtLink to="/appointments" class="block group">
                <UiCard class="transition-colors group-hover:border-sky-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-slate-500">Consultas agendadas</p>
                            <p class="text-2xl font-semibold text-slate-900 mt-1">
                                {{ summaryStore.summary?.upcoming_count ?? 0 }}
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center">
                            <Icon name="heroicons:calendar-days" class="w-5 h-5" />
                        </div>
                    </div>
                </UiCard>
            </NuxtLink>

            <NuxtLink to="/documents" class="block group">
                <UiCard class="transition-colors group-hover:border-sky-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-slate-500">Documentos disponíveis</p>
                            <p class="text-2xl font-semibold text-slate-900 mt-1">
                                {{ summaryStore.summary?.documents_count ?? 0 }}
                            </p>
                        </div>
                        <div
                            class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center"
                        >
                            <Icon name="heroicons:document-text" class="w-5 h-5" />
                        </div>
                    </div>
                </UiCard>
            </NuxtLink>
        </div>

        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">Próxima consulta</h2>
                <NuxtLink to="/appointments" class="text-xs font-medium text-sky-600 hover:text-sky-700">
                    Ver agenda completa →
                </NuxtLink>
            </div>

            <AppointmentCardSkeleton v-if="summaryStore.loading" />

            <AppointmentCard
                v-else-if="summaryStore.summary?.next_appointment"
                :appointment="summaryStore.summary.next_appointment"
                :timezone="clinicTimezone"
                :clinic-name="clinicName"
                :cancellation-window-hours="cancellationWindowHours"
                :pending="Boolean(appointmentsStore.pendingIds[summaryStore.summary.next_appointment.id])"
                @confirm="appointmentsStore.confirmAppointmentOptimistic"
                @cancel="openCancelModal"
            />

            <UiEmptyState
                v-else
                icon="heroicons:calendar"
                title="Nenhuma consulta futura"
                description="Você não possui agendamentos futuros no momento."
            >
                <template #action>
                    <NuxtLink to="/appointments">
                        <UiButton variant="secondary" size="sm">Ver histórico de consultas</UiButton>
                    </NuxtLink>
                </template>
            </UiEmptyState>
        </section>

        <AppointmentCancelModal
            v-model="cancelModalOpen"
            :appointment="appointmentToCancel"
            :timezone="clinicTimezone"
            @confirm="handleCancelConfirm"
        />
    </div>
</template>
