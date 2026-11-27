<script setup lang="ts">
import { toast } from 'vue3-toastify';
import { useAuthStore } from '~/stores/auth';
import { apiRequest } from '~/utils/api';
import { formatUtcInTimezone, getDatePartsInTimezone } from '~/utils/timezone';

interface Props {
    open: boolean;
    appointment: any | null;
    defaultTimezone: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'updated'): void;
    (e: 'reschedule', apt: any): void;
    (e: 'notify', apt: any): void;
    (e: 'cancel', apt: any): void;
}>();

const auth = useAuthStore();
const updatingStatus = ref(false);

const TIMELINE_STEPS = [
    { key: 'SCHEDULED', label: 'Agendado', icon: 'lucide:calendar' },
    { key: 'CONFIRMED', label: 'Confirmado', icon: 'lucide:check-circle-2' },
    { key: 'ARRIVED', label: 'Chegou', icon: 'lucide:map-pin' },
    { key: 'IN_CONSULTATION', label: 'Em consulta', icon: 'lucide:stethoscope' },
    { key: 'COMPLETED', label: 'Concluído', icon: 'lucide:flag' },
];

const STATUS_LABELS: Record<string, string> = {
    SCHEDULED: 'Agendado',
    CONFIRMED: 'Confirmado',
    ARRIVED: 'Chegou',
    IN_CONSULTATION: 'Em consulta',
    COMPLETED: 'Concluído',
    CANCELLED: 'Cancelado',
    NO_SHOW: 'Falta',
};

const currentStepIndex = computed(() => {
    const status = props.appointment?.status;
    return TIMELINE_STEPS.findIndex((s) => s.key === status);
});

const isFinalized = computed(() =>
    ['COMPLETED', 'CANCELLED', 'NO_SHOW'].includes(String(props.appointment?.status ?? '')),
);

const isFuture = computed(() => {
    if (!props.appointment?.start_time_utc) return false;
    return new Date(props.appointment.start_time_utc).getTime() > Date.now();
});

const formattedDateTime = computed(() => {
    if (!props.appointment?.start_time_utc) return '—';
    const d = getDatePartsInTimezone(new Date(props.appointment.start_time_utc), props.defaultTimezone).date
        .split('-')
        .reverse()
        .join('/');
    const start = formatUtcInTimezone(props.appointment.start_time_utc, props.defaultTimezone);
    const end = formatUtcInTimezone(props.appointment.end_time_utc, props.defaultTimezone);
    return `${d} · ${start} – ${end}`;
});

const availableTransitions = computed(() => {
    const status = props.appointment?.status;
    if (!status || isFinalized.value) return [];

    const options: Array<{ status: string; label: string; icon: string; tone: string }> = [];
    if (status === 'SCHEDULED') {
        options.push({ status: 'CONFIRMED', label: 'Confirmar', icon: 'lucide:check', tone: 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' });
    }
    if (status === 'SCHEDULED' || status === 'CONFIRMED') {
        options.push({ status: 'ARRIVED', label: 'Marcar chegada', icon: 'lucide:map-pin', tone: 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100' });
    }
    if (status !== 'IN_CONSULTATION') {
        options.push({ status: 'IN_CONSULTATION', label: 'Iniciar consulta', icon: 'lucide:stethoscope', tone: 'border-teal-200 bg-teal-50 text-teal-700 hover:bg-teal-100' });
    }
    options.push({ status: 'COMPLETED', label: 'Concluir', icon: 'lucide:flag', tone: 'border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100' });
    options.push({ status: 'NO_SHOW', label: 'Marcar falta', icon: 'lucide:user-x', tone: 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100' });
    return options;
});

async function changeStatus(nextStatus: string): Promise<void> {
    if (!auth.tenantId || !props.appointment?.id) return;
    updatingStatus.value = true;
    try {
        await apiRequest(`/api/tenant/appointments/${props.appointment.id}/status`, {
            method: 'PATCH',
            tenantId: auth.tenantId,
            body: { status: nextStatus },
        });
        toast.success(`Status atualizado para ${STATUS_LABELS[nextStatus] || nextStatus}.`);
        emit('updated');
        emit('close');
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao atualizar status.');
    } finally {
        updatingStatus.value = false;
    }
}
</script>

<template>
    <UiModal
        :open="props.open"
        title="Detalhes do Agendamento"
        :description="formattedDateTime"
        max-width="lg"
        @close="emit('close')"
    >
        <div v-if="props.appointment" class="space-y-4 text-[12px]">
            <!-- Header info -->
            <div class="grid grid-cols-1 gap-2 rounded border border-gray-200 bg-gray-50 p-3 sm:grid-cols-3">
                <div>
                    <div class="text-[10px] text-gray-400">Paciente</div>
                    <NuxtLink
                        v-if="props.appointment.patient_id"
                        :to="`/patients/${props.appointment.patient_id}`"
                        class="font-medium text-blue-700 hover:underline"
                    >
                        {{ props.appointment.patient?.name || 'Paciente' }}
                    </NuxtLink>
                    <span v-else class="text-gray-800">—</span>
                </div>
                <div>
                    <div class="text-[10px] text-gray-400">Profissional</div>
                    <div class="text-gray-800">{{ props.appointment.professional?.name || 'Profissional' }}</div>
                </div>
                <div>
                    <div class="text-[10px] text-gray-400">Sala / Status</div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-gray-700">{{ props.appointment.clinic_room?.name || 'Sem sala' }}</span>
                        <span class="rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[10px] text-gray-700">
                            {{ STATUS_LABELS[props.appointment.status] || props.appointment.status }}
                        </span>
                    </div>
                </div>
            </div>

            <div v-if="props.appointment.notes" class="rounded border border-gray-200 bg-white px-3 py-2 text-[11px] text-gray-600">
                <span class="font-medium text-gray-700">Observações:</span> {{ props.appointment.notes }}
            </div>

            <!-- Timeline -->
            <div class="space-y-2">
                <div class="text-[11px] font-medium text-gray-700">Linha do tempo</div>
                <div v-if="props.appointment.status === 'CANCELLED' || props.appointment.status === 'NO_SHOW'" class="rounded border border-rose-200 bg-rose-50 px-3 py-2 text-[11px] text-rose-700">
                    Agendamento encerrado como: <strong>{{ STATUS_LABELS[props.appointment.status] }}</strong>
                </div>
                <div v-else class="grid grid-cols-5 gap-1.5">
                    <div
                        v-for="(step, idx) in TIMELINE_STEPS"
                        :key="step.key"
                        class="flex flex-col items-center rounded border p-2 text-center"
                        :class="
                            idx < currentStepIndex
                                ? 'border-emerald-200 bg-emerald-50/70 text-emerald-700'
                                : idx === currentStepIndex
                                    ? 'border-teal-500 bg-teal-50 text-teal-800 ring-1 ring-teal-500/20'
                                    : 'border-gray-200 bg-gray-50/60 text-gray-400'
                        "
                    >
                        <Icon :name="step.icon" class="mb-1 size-3.5" />
                        <span class="text-[10px] leading-tight">{{ step.label }}</span>
                    </div>
                </div>
            </div>

            <!-- Status transitions -->
            <div v-if="availableTransitions.length > 0 && auth.can('appointments.create')" class="space-y-2">
                <div class="text-[11px] font-medium text-gray-700">Alterar status</div>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="opt in availableTransitions"
                        :key="opt.status"
                        type="button"
                        :disabled="updatingStatus"
                        class="inline-flex h-7 items-center gap-1 rounded border px-2.5 text-[11px] transition-colors disabled:opacity-50"
                        :class="opt.tone"
                        @click="changeStatus(opt.status)"
                    >
                        <Icon :name="opt.icon" class="size-3" />
                        <span>{{ opt.label }}</span>
                    </button>
                </div>
            </div>

            <!-- Quick Actions Footer -->
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3">
                <div class="flex flex-wrap items-center gap-1.5">
                    <button
                        v-if="!isFinalized && isFuture"
                        type="button"
                        class="inline-flex h-7 items-center gap-1 rounded border border-blue-200 bg-blue-50 px-2.5 text-[11px] text-blue-700 hover:bg-blue-100"
                        @click="emit('notify', props.appointment); emit('close')"
                    >
                        <Icon name="lucide:bell" class="size-3" />
                        <span>Notificar</span>
                    </button>
                    <button
                        v-if="!isFinalized && auth.can('appointments.create')"
                        type="button"
                        class="inline-flex h-7 items-center gap-1 rounded border border-gray-200 bg-white px-2.5 text-[11px] text-gray-700 hover:bg-gray-50"
                        @click="emit('reschedule', props.appointment); emit('close')"
                    >
                        <Icon name="lucide:calendar-clock" class="size-3" />
                        <span>Reagendar</span>
                    </button>
                    <button
                        v-if="!isFinalized && auth.can('appointments.cancel')"
                        type="button"
                        class="inline-flex h-7 items-center gap-1 rounded border border-rose-200 bg-rose-50 px-2.5 text-[11px] text-rose-700 hover:bg-rose-100"
                        @click="emit('cancel', props.appointment); emit('close')"
                    >
                        <Icon name="lucide:x-circle" class="size-3" />
                        <span>Cancelar</span>
                    </button>
                </div>

                <button type="button" class="h-7 rounded border border-gray-200 px-2.5 text-[11px] text-gray-600" @click="emit('close')">
                    Fechar
                </button>
            </div>
        </div>
    </UiModal>
</template>
