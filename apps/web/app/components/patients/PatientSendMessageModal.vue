<script setup lang="ts">
import { toast } from 'vue3-toastify';
import { useAuthStore } from '~/stores/auth';
import { apiRequest } from '~/utils/api';
import { normalizePhoneDigits, validateWhatsAppNumber } from '~/utils/phone';

interface Props {
    open: boolean;
    patientId: string;
    defaultPhone: string;
    patientName: string;
    defaultTimezone?: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{ (e: 'close'): void; (e: 'sent'): void }>();

const auth = useAuthStore();
const sending = ref(false);
const phoneError = ref('');

const BASE_TIMEZONES = [
    { value: 'America/Sao_Paulo', label: 'Brasília / São Paulo (America/Sao_Paulo)' },
    { value: 'America/Manaus', label: 'Manaus (America/Manaus)' },
    { value: 'America/Fortaleza', label: 'Fortaleza (America/Fortaleza)' },
    { value: 'America/Cuiaba', label: 'Cuiabá (America/Cuiaba)' },
    { value: 'America/Belem', label: 'Belém (America/Belem)' },
    { value: 'America/Rio_Branco', label: 'Rio Branco (America/Rio_Branco)' },
    { value: 'America/Noronha', label: 'Fernando de Noronha (America/Noronha)' },
    { value: 'America/New_York', label: 'Nova York (America/New_York)' },
    { value: 'Europe/Lisbon', label: 'Lisboa (Europe/Lisbon)' },
    { value: 'UTC', label: 'UTC' },
];

const timezoneOptions = computed(() => {
    const clinicTz = props.defaultTimezone || 'America/Sao_Paulo';
    if (BASE_TIMEZONES.some((tz) => tz.value === clinicTz)) {
        return BASE_TIMEZONES;
    }
    return [{ value: clinicTz, label: `${clinicTz} (Clínica)` }, ...BASE_TIMEZONES];
});

function getDatePartsInTimezone(date: Date, timeZone: string): { date: string; hour: string } {
    try {
        const parts = new Intl.DateTimeFormat('en-CA', {
            timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            hour12: false,
        }).formatToParts(date);

        const map = Object.fromEntries(parts.map((p) => [p.type, p.value]));
        const hour = map.hour === '24' ? '00' : map.hour;
        return {
            date: `${map.year}-${map.month}-${map.day}`,
            hour: hour || '09',
        };
    } catch {
        return {
            date: date.toISOString().slice(0, 10),
            hour: String(date.getHours()).padStart(2, '0'),
        };
    }
}

function zonedDateTimeToUtcMs(dateStr: string, hour: string, minute: string, timeZone: string): number {
    const [year, month, day] = dateStr.split('-').map(Number);
    const utcGuess = Date.UTC(year, month - 1, day, Number(hour), Number(minute), 0);

    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    }).formatToParts(new Date(utcGuess));

    const map = Object.fromEntries(parts.map((p) => [p.type, p.value]));
    const zonedHour = map.hour === '24' ? 0 : Number(map.hour);
    const asUtc = Date.UTC(
        Number(map.year),
        Number(map.month) - 1,
        Number(map.day),
        zonedHour,
        Number(map.minute),
        Number(map.second),
    );
    const offsetMs = asUtc - utcGuess;
    return utcGuess - offsetMs;
}

const form = reactive({
    phone: '',
    message: '',
    schedule: false,
    date: '',
    hour: '09',
    minute: '00',
    timezone: 'America/Sao_Paulo',
});

const todayStr = computed(() => getDatePartsInTimezone(new Date(), form.timezone).date);
const hours = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));
const minutes = Array.from({ length: 12 }, (_, i) => String(i * 5).padStart(2, '0'));

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            const tz = props.defaultTimezone || 'America/Sao_Paulo';
            const nowParts = getDatePartsInTimezone(new Date(), tz);
            form.phone = normalizePhoneDigits(props.defaultPhone || '');
            form.message = '';
            form.schedule = false;
            form.timezone = tz;
            form.date = nowParts.date;
            form.hour = nowParts.hour;
            form.minute = '00';
            phoneError.value = '';
        }
    },
);

function onPhoneInput(event: Event): void {
    const target = event.target as HTMLInputElement;
    const digits = normalizePhoneDigits(target.value);
    form.phone = digits;
    target.value = digits;
    if (phoneError.value) phoneError.value = '';
}

async function handleSubmit(): Promise<void> {
    if (!auth.tenantId) return;

    const validation = validateWhatsAppNumber(form.phone);
    form.phone = validation.digits;
    if (!validation.valid) {
        phoneError.value = validation.error || 'Número inválido.';
        toast.error(phoneError.value);
        return;
    }

    let scheduledAt: string | null = null;
    if (form.schedule) {
        if (!form.date) {
            toast.error('Selecione a data do agendamento.');
            return;
        }
        const targetUtcMs = zonedDateTimeToUtcMs(form.date, form.hour, form.minute, form.timezone);
        if (Number.isNaN(targetUtcMs) || targetUtcMs <= Date.now()) {
            toast.error('A data e hora do agendamento devem estar no futuro.');
            return;
        }
        scheduledAt = `${form.date} ${form.hour}:${form.minute}:00`;
    }

    sending.value = true;
    try {
        await apiRequest(`/api/tenant/patients/${props.patientId}/messages`, {
            method: 'POST',
            tenantId: auth.tenantId,
            body: {
                channel: 'WHATSAPP',
                number: validation.digits,
                message: form.message,
                schedule: form.schedule,
                scheduled_at: scheduledAt,
                timezone: form.schedule ? form.timezone : undefined,
            },
        });
        toast.success(form.schedule ? 'Envio agendado com sucesso.' : 'Mensagem WhatsApp enviada com sucesso.');
        emit('sent');
        emit('close');
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao enviar mensagem.');
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <UiModal
        :open="props.open"
        title="Enviar mensagem (WhatsApp)"
        :description="`Paciente: ${props.patientName}`"
        max-width="sm"
        @close="emit('close')"
    >
        <form class="space-y-3 text-[12px]" @submit.prevent="handleSubmit">
            <div>
                <label class="mb-1 block text-[11px] text-gray-600">WhatsApp do paciente (DDI + DDD + número)</label>
                <input
                    id="input-patient-msg-phone"
                    :value="form.phone"
                    type="text"
                    inputmode="numeric"
                    required
                    class="h-8 w-full rounded border px-2.5 text-[12px]"
                    :class="phoneError ? 'border-rose-400' : 'border-gray-200'"
                    @input="onPhoneInput"
                />
                <p v-if="phoneError" class="mt-1 text-[10px] text-rose-600">{{ phoneError }}</p>
            </div>

            <div>
                <label class="mb-1 block text-[11px] text-gray-600">Mensagem</label>
                <textarea
                    id="input-patient-msg-text"
                    v-model="form.message"
                    rows="3"
                    required
                    placeholder="Digite sua mensagem para o paciente..."
                    class="w-full rounded border border-gray-200 p-2 text-[12px]"
                />
            </div>

            <div class="space-y-2 rounded border border-gray-200 bg-gray-50/70 p-2.5">
                <label class="flex cursor-pointer items-center gap-2 text-[12px] text-gray-700">
                    <input
                        id="checkbox-schedule-message"
                        v-model="form.schedule"
                        type="checkbox"
                        class="size-3.5 accent-blue-600"
                    />
                    <span>Agendar envio</span>
                </label>

                <div v-if="form.schedule" class="space-y-2 pt-1">
                    <div class="grid grid-cols-3 gap-2">
                        <div class="col-span-3 sm:col-span-1">
                            <label class="mb-1 block text-[10px] text-gray-500">Data</label>
                            <input
                                id="input-schedule-date"
                                v-model="form.date"
                                type="date"
                                :min="todayStr"
                                required
                                class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[11px]"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] text-gray-500">Hora</label>
                            <select
                                id="select-schedule-hour"
                                v-model="form.hour"
                                class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[11px]"
                            >
                                <option v-for="h in hours" :key="h" :value="h">{{ h }}h</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] text-gray-500">Minuto</label>
                            <select
                                id="select-schedule-minute"
                                v-model="form.minute"
                                class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[11px]"
                            >
                                <option v-for="m in minutes" :key="m" :value="m">{{ m }}m</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-[10px] text-gray-500">
                            Fuso horário
                            <span v-if="props.defaultTimezone && form.timezone === props.defaultTimezone" class="text-gray-400">
                                (padrão da clínica)
                            </span>
                        </label>
                        <select
                            id="select-schedule-timezone"
                            v-model="form.timezone"
                            class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[11px]"
                        >
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
                    id="btn-submit-patient-message"
                    type="submit"
                    :disabled="sending"
                    class="flex h-7 items-center gap-1.5 rounded bg-blue-600 px-3 text-[11px] text-white hover:bg-blue-700 disabled:opacity-50"
                >
                    <Icon v-if="sending" name="lucide:loader-2" class="size-3 animate-spin" />
                    <span>{{ form.schedule ? 'Agendar mensagem' : 'Enviar agora' }}</span>
                </button>
            </div>
        </form>
    </UiModal>
</template>
