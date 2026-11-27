<script setup lang="ts">
import type { PatientAppointment } from '~/types/appointment';
import { formatDateTimeInTz } from '~/utils/formatters';

interface Props {
    modelValue: boolean;
    appointment: PatientAppointment | null;
    timezone: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
    confirm: [reason: string];
}>();

const reason = ref('');

watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            reason.value = '';
        }
    }
);

function handleConfirm(): void {
    emit('confirm', reason.value.trim());
    emit('update:modelValue', false);
}
</script>

<template>
    <UiModal
        :model-value="modelValue"
        title="Cancelar consulta"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <div v-if="appointment" class="space-y-4">
            <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800 space-y-1">
                <p class="font-medium">
                    {{ appointment.appointment_type?.name || 'Consulta' }} — {{ appointment.professional?.name }}
                </p>
                <p>Horário: {{ formatDateTimeInTz(appointment.start_time_utc, timezone) }}</p>
            </div>

            <UiInput
                v-model="reason"
                label="Motivo do cancelamento (opcional)"
                placeholder="Ex.: Conflito de horário"
            />

            <div class="flex items-center justify-end gap-2 pt-2">
                <UiButton variant="secondary" size="sm" @click="$emit('update:modelValue', false)">
                    Manter consulta
                </UiButton>
                <UiButton variant="danger" size="sm" icon="heroicons:x-mark" @click="handleConfirm">
                    Confirmar cancelamento
                </UiButton>
            </div>
        </div>
    </UiModal>
</template>
