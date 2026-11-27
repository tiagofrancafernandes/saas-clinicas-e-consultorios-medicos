<script setup lang="ts">
import { toast } from 'vue3-toastify';
import { useAuthStore } from '~/stores/auth';
import { apiRequest } from '~/utils/api';
import { normalizePhoneDigits, validateWhatsAppNumber } from '~/utils/phone';
import {
    buildTimezoneOptions,
    formatUtcInTimezone,
    getDatePartsInTimezone,
    zonedDateTimeToUtcMs,
} from '~/utils/timezone';

interface Props {
    open: boolean;
    appointment: any | null;
    defaultTimezone: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{ (e: 'close'): void; (e: 'sent'): void }>();

const auth = useAuthStore();
const sending = ref(false);
const mode = ref<'reminder' | 'confirmation'>('reminder');
const customizeMessage = ref(false);
const customMessage = ref('');

const schedule = ref(false);
const scheduleDate = ref('');
const scheduleHour = ref('09');
const scheduleMinute = ref('00');
const scheduleTimezone = ref('America/Sao_Paulo');

const timezoneOptions = computed(() => buildTimezoneOptions(props.defaultTimezone));
const todayStr = computed(() => getDatePartsInTimezone(new Date(), scheduleTimezone.value).date);
const hours = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));
const minutes = Array.from({ length: 12 }, (_, i) => String(i * 5).padStart(2, '0'));

const defaultMessage = computed(() => {
    const apt = props.appointment;
    if (!apt) return '';
    const patientName = apt.patient?.name || 'Paciente';
    const profName = apt.professional?.name || 'Profissional';
    const datePart = getDatePartsInTimezone(new Date(apt.start_time_utc), props.defaultTimezone).date.split('-').reverse().join('/');
    const timePart = formatUtcInTimezone(apt.start_time_utc, props.defaultTimezone);

    if (mode.value === 'confirmation') {
        return `Olá ${patientName}, lembramos da sua consulta com ${profName} no dia ${datePart} às ${timePart}. Por favor, confirme seu comparecimento respondendo esta mensagem.`;
    }
    return `Olá ${patientName}, lembramos da sua consulta com ${profName} no dia ${datePart} às ${timePart}.`;
});

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) return;
        const tz = props.defaultTimezone || 'America/Sao_Paulo';
        const nowParts = getDatePartsInTimezone(new Date(), tz);
        mode.value = 'reminder';
        customizeMessage.value = false;
        customMessage.value = defaultMessage.value;
        schedule.value = false;
        scheduleTimezone.value = tz;
        scheduleDate.value = nowParts.date;
        scheduleHour.value = nowParts.hour;
        scheduleMinute.value = '00';
    },
);

watch(mode, () => {
    if (!customizeMessage.value) {
        customMessage.value = defaultMessage.value;
    }
});

async function handleSubmit(): Promise<void> {
    if (!auth.tenantId || !props.appointment?.patient_id) return;

    const rawPhone = props.appointment.patient?.phone || '';
    const validation = validateWhatsAppNumber(normalizePhoneDigits(rawPhone));
    if (!validation.valid) {
        toast.error(validation.error || 'Telefone do paciente inválido para WhatsApp.');
        return;
    }

    const messageText = customizeMessage.value ? customMessage.value.trim() : defaultMessage.value;
    if (!messageText) {
        toast.error('Informe a mensagem.');
        return;
    }

    let scheduledAt: string | null = null;
    if (schedule.value) {
        if (!scheduleDate.value) {
            toast.error('Selecione a data do agendamento.');
            return;
        }
        const targetUtcMs = zonedDateTimeToUtcMs(
            scheduleDate.value,
            `${scheduleHour.value}:${scheduleMinute.value}`,
            scheduleTimezone.value,
        );
        if (Number.isNaN(targetUtcMs) || targetUtcMs <= Date.now()) {
            toast.error('A data e hora do agendamento devem estar no futuro.');
            return;
        }
        scheduledAt = `${scheduleDate.value} ${scheduleHour.value}:${scheduleMinute.value}:00`;
    }

    sending.value = true;
    try {
        await apiRequest(`/api/tenant/patients/${props.appointment.patient_id}/messages`, {
            method: 'POST',
            tenantId: auth.tenantId,
            body: {
                channel: 'WHATSAPP',
                number: validation.digits,
                message: messageText,
                schedule: schedule.value,
                scheduled_at: scheduledAt,
                timezone: schedule.value ? scheduleTimezone.value : undefined,
                appointment_id: props.appointment.id,
                type: mode.value === 'confirmation' ? 'CONFIRMATION_REQUEST' : 'REMINDER',
            },
        });
        toast.success(schedule.value ? 'Notificação agendada com sucesso.' : 'Notificação enviada via WhatsApp.');
        emit('sent');
        emit('close');
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao enviar notificação.');
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <UiModal
        :open="props.open"
        title="Notificar / Lembrar paciente"
        :description="props.appointment?.patient?.name ? `Paciente: ${props.appointment.patient.name}` : undefined"
        max-width="md"
        @close="emit('close')"
    >
        <form class="space-y-3 text-[12px]" @submit.prevent="handleSubmit">
            <div class="grid grid-cols-2 gap-2">
                <button
                    type="button"
                    class="flex items-center justify-center gap-1.5 rounded border px-3 py-2 text-[12px] transition-colors"
                    :class="
                        mode === 'reminder'
                            ? 'border-blue-500 bg-blue-50 text-blue-700'
                            : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'
                    "
                    @click="mode = 'reminder'"
                >
                    <Icon name="lucide:bell" class="size-3.5" />
                    <span>Lembrar</span>
                </button>
                <button
                    type="button"
                    class="flex items-center justify-center gap-1.5 rounded border px-3 py-2 text-[12px] transition-colors"
                    :class="
                        mode === 'confirmation'
                            ? 'border-blue-500 bg-blue-50 text-blue-700'
                            : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'
                    "
                    @click="mode = 'confirmation'"
                >
                    <Icon name="lucide:check-square" class="size-3.5" />
                    <span>Pedir confirmação</span>
                </button>
            </div>

            <div class="space-y-2 rounded border border-gray-200 bg-gray-50/70 p-2.5">
                <label class="flex cursor-pointer items-center gap-2 text-[11px] text-gray-700">
                    <input v-model="customizeMessage" type="checkbox" class="size-3.5 accent-blue-600" />
                    <span>Customizar mensagem</span>
                </label>

                <textarea
                    v-if="customizeMessage"
                    v-model="customMessage"
                    rows="3"
                    required
                    class="w-full rounded border border-gray-200 bg-white p-2 text-[12px]"
                />
                <div v-else class="rounded border border-gray-200 bg-white p-2 text-[11px] text-gray-600">
                    {{ defaultMessage }}
                </div>
            </div>

            <div class="space-y-2 rounded border border-gray-200 bg-gray-50/70 p-2.5">
                <label class="flex cursor-pointer items-center gap-2 text-[12px] text-gray-700">
                    <input v-model="schedule" type="checkbox" class="size-3.5 accent-blue-600" />
                    <span>Agendar envio</span>
                </label>

                <div v-if="schedule" class="space-y-2 pt-1">
                    <div class="grid grid-cols-3 gap-2">
                        <div class="col-span-3 sm:col-span-1">
                            <label class="mb-1 block text-[10px] text-gray-500">Data</label>
                            <input
                                v-model="scheduleDate"
                                type="date"
                                :min="todayStr"
                                required
                                class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[11px]"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] text-gray-500">Hora</label>
                            <select v-model="scheduleHour" class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[11px]">
                                <option v-for="h in hours" :key="h" :value="h">{{ h }}h</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] text-gray-500">Minuto</label>
                            <select v-model="scheduleMinute" class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[11px]">
                                <option v-for="m in minutes" :key="m" :value="m">{{ m }}m</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-[10px] text-gray-500">Fuso horário</label>
                        <select v-model="scheduleTimezone" class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[11px]">
                            <option v-for="tz in timezoneOptions" :key="tz.value" :value="tz.value">
                                {{ tz.label }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                <button type="button" class="h-7 rounded border border-gray-200 px-2.5 text-[11px] text-gray-600" @click="emit('close')">
                    Cancelar
                </button>
                <button
                    type="submit"
                    :disabled="sending"
                    class="inline-flex h-7 items-center gap-1.5 rounded bg-blue-600 px-3 text-[11px] text-white hover:bg-blue-700 disabled:opacity-50"
                >
                    <Icon v-if="sending" name="lucide:loader-2" class="size-3 animate-spin" />
                    <span>{{ schedule ? 'Agendar notificação' : 'Enviar agora' }}</span>
                </button>
            </div>
        </form>
    </UiModal>
</template>
