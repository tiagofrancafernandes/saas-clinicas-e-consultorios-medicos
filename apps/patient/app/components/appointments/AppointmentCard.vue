<script setup lang="ts">
import { APPOINTMENT_STATUS_LABELS, APPOINTMENT_STATUS_TONES } from '~/constants/enums';
import type { PatientAppointment } from '~/types/appointment';
import { downloadAppointmentIcs, formatDateTimeInTz, formatDayMonthInTz } from '~/utils/formatters';

interface Props {
    appointment: PatientAppointment;
    timezone: string;
    clinicName: string;
    cancellationWindowHours: number;
    pending?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    pending: false,
});

defineEmits<{
    confirm: [id: string];
    cancel: [appointment: PatientAppointment];
}>();

const dateParts = computed(() => formatDayMonthInTz(props.appointment.start_time_utc, props.timezone));
const statusLabel = computed(() => APPOINTMENT_STATUS_LABELS[props.appointment.status]);
const statusTone = computed(() => APPOINTMENT_STATUS_TONES[props.appointment.status]);
const deadlineText = computed(() => formatDateTimeInTz(props.appointment.cancellation_deadline_utc, props.timezone));

const isUpcomingActive = computed(
    () =>
        !['CANCELLED', 'COMPLETED', 'NO_SHOW'].includes(props.appointment.status) &&
        new Date(props.appointment.start_time_utc).getTime() > Date.now()
);
</script>

<template>
    <UiCard class="transition-colors hover:border-slate-300">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start gap-3.5">
                <div
                    class="w-14 h-14 rounded-xl bg-sky-50 border border-sky-100 flex flex-col items-center justify-center shrink-0"
                >
                    <span class="text-lg font-semibold text-sky-700 leading-none">
                        {{ dateParts.day }}
                    </span>
                    <span class="text-[10px] font-medium text-sky-600 mt-1">
                        {{ dateParts.month }}
                    </span>
                </div>

                <div class="space-y-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-sm font-semibold text-slate-900">
                            {{ appointment.appointment_type?.name || 'Consulta' }}
                        </h3>
                        <UiBadge :tone="statusTone">
                            {{ statusLabel }}
                        </UiBadge>
                    </div>

                    <p class="text-xs text-slate-600 flex items-center gap-1.5">
                        <Icon name="heroicons:user" class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                        <span>
                            {{ appointment.professional?.name || 'Profissional a definir' }}
                            <template v-if="appointment.professional?.specialty">
                                · {{ appointment.professional.specialty }}
                            </template>
                        </span>
                    </p>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                        <span class="inline-flex items-center gap-1 capitalize">
                            <Icon name="heroicons:clock" class="w-3.5 h-3.5 text-slate-400" />
                            {{ dateParts.weekday }}, {{ dateParts.time }}
                        </span>
                        <span v-if="appointment.clinic_room?.name" class="inline-flex items-center gap-1">
                            <Icon name="heroicons:map-pin" class="w-3.5 h-3.5 text-slate-400" />
                            {{ appointment.clinic_room.name }}
                        </span>
                    </div>

                    <p
                        v-if="appointment.cancellation_reason && appointment.status === 'CANCELLED'"
                        class="text-xs text-rose-600 pt-0.5"
                    >
                        Motivo: {{ appointment.cancellation_reason }}
                    </p>

                    <p
                        v-else-if="isUpcomingActive && !appointment.can_cancel"
                        class="text-[11px] text-amber-700 pt-0.5"
                    >
                        Prazo de cancelamento online encerrado (mínimo {{ cancellationWindowHours }}h antes).
                    </p>
                    <p v-else-if="isUpcomingActive && appointment.can_cancel" class="text-[11px] text-slate-400 pt-0.5">
                        Cancelamento gratuito disponível até {{ deadlineText }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 sm:justify-end shrink-0">
                <UiButton
                    v-if="isUpcomingActive"
                    variant="secondary"
                    size="sm"
                    icon="heroicons:calendar"
                    @click="downloadAppointmentIcs(appointment, clinicName)"
                >
                    Calendário
                </UiButton>

                <UiButton
                    v-if="appointment.can_confirm"
                    variant="primary"
                    size="sm"
                    icon="heroicons:check"
                    :loading="pending"
                    @click="$emit('confirm', appointment.id)"
                >
                    Confirmar
                </UiButton>

                <UiButton
                    v-if="appointment.can_cancel"
                    variant="danger"
                    size="sm"
                    icon="heroicons:x-mark"
                    :disabled="pending"
                    @click="$emit('cancel', appointment)"
                >
                    Cancelar
                </UiButton>
            </div>
        </div>
    </UiCard>
</template>
