<script setup lang="ts">
import { toast } from 'vue3-toastify';
import { useAuthStore } from '~/stores/auth';
import { apiRequest } from '~/utils/api';
import { formatUtcInTimezone } from '~/utils/timezone';

interface Props {
    open: boolean;
    appointment: any | null;
    defaultTimezone: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{ (e: 'close'): void; (e: 'cancelled'): void }>();

const auth = useAuthStore();
const cancelling = ref(false);
const reason = ref('');
const notifyPatient = ref(false);
const customizeMessage = ref(false);
const customMessage = ref('');

const defaultCancelMessage = computed(() => {
    if (!props.appointment) return '';
    const patientName = props.appointment.patient?.name || 'Paciente';
    const profName = props.appointment.professional?.name || 'o profissional';
    const tz = props.appointment.origin_timezone || props.defaultTimezone || 'America/Sao_Paulo';
    const when = formatUtcInTimezone(props.appointment.start_time_utc, tz, true);
    return `Olá ${patientName}, sua consulta com ${profName} prevista para ${when} foi cancelada.`;
});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            reason.value = '';
            notifyPatient.value = false;
            customizeMessage.value = false;
            customMessage.value = '';
        }
    },
);

watch(customizeMessage, (isCustom) => {
    if (isCustom && !customMessage.value.trim()) {
        customMessage.value = defaultCancelMessage.value;
    }
});

async function handleCancel(): Promise<void> {
    if (!auth.tenantId || !props.appointment) return;
    cancelling.value = true;
    try {
        await apiRequest(`/api/tenant/appointments/${props.appointment.id}/cancel`, {
            method: 'POST',
            tenantId: auth.tenantId,
            body: {
                reason: reason.value.trim() || 'Cancelado pela clínica',
                notify_patient: notifyPatient.value,
                notification_message: notifyPatient.value && customizeMessage.value ? customMessage.value : null,
            },
        });
        toast.success('Agendamento cancelado.');
        emit('cancelled');
        emit('close');
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao cancelar agendamento.');
    } finally {
        cancelling.value = false;
    }
}
</script>

<template>
    <UiModal
        :open="props.open"
        title="Confirmar cancelamento"
        :description="
            props.appointment
                ? `Cancelar consulta de ${props.appointment.patient?.name || 'Paciente'}?`
                : ''
        "
        max-width="lg"
        @close="!cancelling && emit('close')"
    >
        <form class="space-y-3 text-[12px]" @submit.prevent="handleCancel">
            <div>
                <label class="mb-1 block text-[11px] text-gray-600">Motivo (opcional)</label>
                <input
                    v-model="reason"
                    type="text"
                    placeholder="Ex: Solicitação do paciente"
                    class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]"
                />
            </div>

            <div class="space-y-2 rounded border border-gray-200 bg-gray-50/70 p-2.5">
                <label class="flex cursor-pointer items-center gap-2 text-[12px] text-gray-700">
                    <input v-model="notifyPatient" type="checkbox" class="size-3.5 accent-teal-600" />
                    <span>Notificar cliente via WhatsApp</span>
                </label>

                <template v-if="notifyPatient">
                    <label class="flex cursor-pointer items-center gap-2 text-[11px] text-gray-600">
                        <input v-model="customizeMessage" type="checkbox" class="size-3.5 accent-teal-600" />
                        <span>Customizar mensagem</span>
                    </label>

                    <textarea
                        v-if="customizeMessage"
                        v-model="customMessage"
                        rows="3"
                        required
                        class="w-full rounded border border-gray-200 bg-white p-2 text-[12px]"
                    />
                    <p v-else class="rounded border border-gray-200 bg-white px-2.5 py-1.5 text-[11px] text-gray-500">
                        Mensagem automática: "{{ defaultCancelMessage }}"
                    </p>
                </template>
            </div>

            <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                <button
                    type="button"
                    :disabled="cancelling"
                    class="h-7 rounded border border-gray-200 px-2.5 text-[11px] text-gray-600"
                    @click="emit('close')"
                >
                    Voltar
                </button>
                <button
                    type="submit"
                    :disabled="cancelling"
                    class="inline-flex h-7 items-center gap-1.5 rounded bg-rose-600 px-3 text-[11px] text-white hover:bg-rose-700 disabled:opacity-50"
                >
                    <Icon v-if="cancelling" name="lucide:loader-2" class="size-3 animate-spin" />
                    <span>Confirmar cancelamento</span>
                </button>
            </div>
        </form>
    </UiModal>
</template>
