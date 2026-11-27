<script setup lang="ts">
import { toast } from 'vue3-toastify';
import type { TypeaheadOption } from '~/components/ui/UiTypeaheadSelect.vue';
import { useAuthStore } from '~/stores/auth';
import { apiRequest } from '~/utils/api';
import {
    buildTimezoneOptions,
    formatUtcInTimezone,
    getDatePartsInTimezone,
    zonedDateTimeToUtcMs,
} from '~/utils/timezone';

export interface AppointmentModalInitialData {
    mode: 'create' | 'reschedule';
    appointmentId?: string;
    patientId?: string;
    patientName?: string;
    professionalId?: string;
    clinicRoomId?: string | null;
    date?: string;
    startTime?: string;
    endTime?: string;
    notes?: string;
}

interface Props {
    open: boolean;
    professionals: Array<{ id: string; name: string; specialty?: string }>;
    rooms: Array<{ id: string; name: string }>;
    defaultTimezone: string;
    initialData?: AppointmentModalInitialData | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{ (e: 'close'): void; (e: 'saved'): void }>();

const auth = useAuthStore();
const saving = ref(false);
const loadingSlots = ref(false);
const availableSlots = ref<Array<{ start_time_utc: string; end_time_utc: string }>>([]);
const dateError = ref('');

const timezoneOptions = computed(() => buildTimezoneOptions(props.defaultTimezone));

const form = reactive({
    patient_id: null as string | number | null,
    patient_name: '',
    professional_id: '',
    clinic_room_id: '',
    date: '',
    start_time: '09:00',
    duration_minutes: 30,
    timezone: 'America/Sao_Paulo',
    notes: '',
    notify_patient: false,
    customize_message: false,
    notification_message: '',
});

const todayInSelectedTz = computed(() => getDatePartsInTimezone(new Date(), form.timezone).date);

const defaultNotificationPreview = computed(() => {
    const patientName = form.patient_name || 'Paciente';
    const profName =
        props.professionals.find((p) => p.id === form.professional_id)?.name || 'o profissional';
    const [y, m, d] = (form.date || '').split('-');
    const datePt = y && m && d ? `${d}/${m}/${y}` : form.date;
    const actionWord = props.initialData?.mode === 'reschedule' ? 'remarcada' : 'agendada';
    return `Olá ${patientName}, sua consulta com ${profName} foi ${actionWord} para ${datePt} às ${form.start_time}.`;
});

watch(
    () => form.customize_message,
    (isCustom) => {
        if (isCustom && !form.notification_message.trim()) {
            form.notification_message = defaultNotificationPreview.value;
        }
    },
);

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) return;
        const tz = props.defaultTimezone || 'America/Sao_Paulo';
        const nowParts = getDatePartsInTimezone(new Date(), tz);
        const init = props.initialData;

        form.timezone = tz;
        form.patient_id = init?.patientId ?? null;
        form.patient_name = init?.patientName ?? '';
        form.professional_id = init?.professionalId || props.professionals[0]?.id || '';
        form.clinic_room_id = init?.clinicRoomId ?? '';
        form.date = init?.date || nowParts.date;
        form.start_time = init?.startTime || '09:00';
        form.duration_minutes = 30;
        form.notes = init?.notes ?? '';
        form.notify_patient = false;
        form.customize_message = false;
        form.notification_message = '';
        dateError.value = '';
    },
);

async function fetchAvailableSlots(): Promise<void> {
    if (!props.open || !auth.tenantId || !form.professional_id || !form.date) {
        availableSlots.value = [];
        return;
    }
    if (!/^\d{4}-\d{2}-\d{2}$/.test(form.date)) {
        availableSlots.value = [];
        return;
    }

    loadingSlots.value = true;
    try {
        const params = new URLSearchParams({
            professional_id: form.professional_id,
            date: form.date,
            slot_duration: String(form.duration_minutes),
        });
        const res = await apiRequest<{ data: Array<{ start_time_utc: string; end_time_utc: string }> }>(
            `/api/tenant/availability/slots?${params.toString()}`,
            { tenantId: auth.tenantId },
        );
        availableSlots.value = res.data ?? [];
    } catch {
        availableSlots.value = [];
    } finally {
        loadingSlots.value = false;
    }
}

watch(
    () => [props.open, form.professional_id, form.date, form.duration_minutes] as const,
    () => {
        if (props.open) {
            fetchAvailableSlots();
        }
    },
);

function selectSlot(slot: { start_time_utc: string; end_time_utc: string }): void {
    const startParts = getDatePartsInTimezone(new Date(slot.start_time_utc), form.timezone);
    form.date = startParts.date;
    form.start_time = startParts.time;
    dateError.value = '';
}

async function searchPatients(searchQuery: string): Promise<TypeaheadOption[]> {
    if (!auth.tenantId) return [];
    const params = new URLSearchParams({ limit: '20' });
    if (searchQuery) params.set('search', searchQuery);
    const res = await apiRequest<{ data: Array<{ id: string; name: string; phone?: string; email?: string }> }>(
        `/api/tenant/patients?${params.toString()}`,
        { tenantId: auth.tenantId },
    );
    return (res.data ?? []).map((p) => ({
        value: p.id,
        label: p.name,
        description: p.phone || p.email || undefined,
        raw: p,
    }));
}

function handlePatientSelect(option: TypeaheadOption | null): void {
    form.patient_name = option?.label ?? '';
}

function computeEndTimeStr(startTime: string, durationMin: number): string {
    const [h, m] = startTime.split(':').map(Number);
    const total = (h || 0) * 60 + (m || 0) + durationMin;
    const endH = Math.floor(total / 60) % 24;
    const endM = total % 60;
    return `${String(endH).padStart(2, '0')}:${String(endM).padStart(2, '0')}`;
}

async function handleSubmit(): Promise<void> {
    if (!auth.tenantId) return;
    dateError.value = '';

    if (props.initialData?.mode !== 'reschedule' && !form.patient_id) {
        toast.error('Selecione um paciente.');
        return;
    }
    if (!form.professional_id) {
        toast.error('Selecione um profissional.');
        return;
    }
    if (!/^\d{4}-\d{2}-\d{2}$/.test(form.date) || !/^\d{2}:\d{2}$/.test(form.start_time)) {
        dateError.value = 'Data ou horário em formato inválido.';
        toast.error(dateError.value);
        return;
    }

    const startUtcMs = zonedDateTimeToUtcMs(form.date, form.start_time, form.timezone);
    if (Number.isNaN(startUtcMs) || startUtcMs <= Date.now()) {
        dateError.value = 'A data e hora do agendamento devem estar no futuro.';
        toast.error(dateError.value);
        return;
    }

    const endUtcMs = startUtcMs + form.duration_minutes * 60_000;
    const startUtcIso = new Date(startUtcMs).toISOString();
    const endUtcIso = new Date(endUtcMs).toISOString();

    saving.value = true;
    try {
        if (props.initialData?.mode === 'reschedule' && props.initialData.appointmentId) {
            await apiRequest(`/api/tenant/appointments/${props.initialData.appointmentId}/reschedule`, {
                method: 'POST',
                tenantId: auth.tenantId,
                body: {
                    new_start_time_utc: startUtcIso,
                    new_end_time_utc: endUtcIso,
                    origin_timezone: form.timezone,
                    clinic_room_id: form.clinic_room_id || null,
                    notify_patient: form.notify_patient,
                    notification_message: form.notify_patient && form.customize_message ? form.notification_message : null,
                },
            });
            toast.success('Agendamento remarcado com sucesso.');
        } else {
            await apiRequest('/api/tenant/appointments', {
                method: 'POST',
                tenantId: auth.tenantId,
                body: {
                    patient_id: form.patient_id,
                    professional_id: form.professional_id,
                    clinic_room_id: form.clinic_room_id || null,
                    start_time_utc: startUtcIso,
                    end_time_utc: endUtcIso,
                    origin_timezone: form.timezone,
                    notes: form.notes || null,
                    notify_patient: form.notify_patient,
                    notification_message: form.notify_patient && form.customize_message ? form.notification_message : null,
                },
            });
            toast.success('Agendamento criado com sucesso.');
        }

        emit('saved');
        emit('close');
    } catch (err: any) {
        const message = err?.data?.message || 'Erro ao salvar agendamento.';
        dateError.value = message;
        toast.error(message);
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <UiModal
        :open="props.open"
        :title="props.initialData?.mode === 'reschedule' ? 'Remarcar Agendamento' : 'Novo Agendamento'"
        max-width="2xl"
        @close="!saving && emit('close')"
    >
        <form class="space-y-3 text-[12px]" @submit.prevent="handleSubmit">
            <div v-if="props.initialData?.mode !== 'reschedule'">
                <label class="mb-1 block text-[11px] text-gray-600">Paciente</label>
                <UiTypeaheadSelect
                    v-model="form.patient_id"
                    input-id="select-appointment-patient"
                    :preload="Boolean(auth.tenantId)"
                    :fetch-options="searchPatients"
                    :initial-option="
                        props.initialData?.patientId && props.initialData?.patientName
                            ? { value: props.initialData.patientId, label: props.initialData.patientName }
                            : null
                    "
                    placeholder="Selecione ou busque o paciente..."
                    empty-text="Nenhum paciente encontrado."
                    required
                    @select="handlePatientSelect"
                />
            </div>

            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Profissional</label>
                    <UiTypeaheadSelect
                        :model-value="form.professional_id || null"
                        :options="props.professionals.map((prof) => ({ value: prof.id, label: prof.name, description: prof.specialty || undefined }))"
                        :disabled="props.initialData?.mode === 'reschedule'"
                        placeholder="Selecione o profissional..."
                        empty-text="Nenhum profissional encontrado."
                        required
                        @update:model-value="form.professional_id = $event ? String($event) : ''"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Consultório / Sala (opcional)</label>
                    <select
                        v-model="form.clinic_room_id"
                        class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px]"
                    >
                        <option value="">Sem sala definida</option>
                        <option v-for="room in props.rooms" :key="room.id" :value="room.id">
                            {{ room.name }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-2 sm:grid-cols-4">
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Data</label>
                    <input
                        v-model="form.date"
                        type="date"
                        :min="todayInSelectedTz"
                        required
                        class="h-8 w-full rounded border border-gray-200 px-2 text-[12px]"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Início</label>
                    <input
                        v-model="form.start_time"
                        type="time"
                        required
                        class="h-8 w-full rounded border border-gray-200 px-2 text-[12px]"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Duração</label>
                    <select
                        v-model.number="form.duration_minutes"
                        class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px]"
                    >
                        <option :value="15">15 min (até {{ computeEndTimeStr(form.start_time, 15) }})</option>
                        <option :value="20">20 min (até {{ computeEndTimeStr(form.start_time, 20) }})</option>
                        <option :value="30">30 min (até {{ computeEndTimeStr(form.start_time, 30) }})</option>
                        <option :value="45">45 min (até {{ computeEndTimeStr(form.start_time, 45) }})</option>
                        <option :value="60">60 min (até {{ computeEndTimeStr(form.start_time, 60) }})</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">
                        Fuso horário
                        <span v-if="form.timezone === props.defaultTimezone" class="text-gray-400">(clínica)</span>
                    </label>
                    <select
                        v-model="form.timezone"
                        class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[11px]"
                    >
                        <option v-for="tz in timezoneOptions" :key="tz.value" :value="tz.value">
                            {{ tz.label }}
                        </option>
                    </select>
                </div>
            </div>

            <p v-if="dateError" class="text-[11px] text-rose-600">{{ dateError }}</p>

            <!-- Live Available Slots -->
            <div class="rounded border border-gray-200 bg-gray-50/70 p-2.5">
                <div class="mb-1.5 flex items-center justify-between text-[11px] text-gray-600">
                    <span>Horários disponíveis ({{ form.timezone }})</span>
                    <Icon v-if="loadingSlots" name="lucide:loader-2" class="size-3 animate-spin text-gray-400" />
                </div>
                <div v-if="!loadingSlots && availableSlots.length === 0" class="text-[11px] text-gray-400">
                    Nenhum slot disponível para este profissional nesta data.
                </div>
                <div v-else class="flex flex-wrap gap-1.5">
                    <button
                        v-for="slot in availableSlots"
                        :key="slot.start_time_utc"
                        type="button"
                        class="rounded border px-2 py-1 font-mono text-[11px] transition-colors"
                        :class="
                            getDatePartsInTimezone(new Date(slot.start_time_utc), form.timezone).time === form.start_time
                                ? 'border-teal-600 bg-teal-600 text-white'
                                : 'border-gray-200 bg-white text-gray-700 hover:border-teal-300 hover:bg-teal-50'
                        "
                        @click="selectSlot(slot)"
                    >
                        {{ formatUtcInTimezone(slot.start_time_utc, form.timezone) }}
                    </button>
                </div>
            </div>

            <div v-if="props.initialData?.mode !== 'reschedule'">
                <label class="mb-1 block text-[11px] text-gray-600">Observações (opcional)</label>
                <input
                    v-model="form.notes"
                    type="text"
                    placeholder="Ex: Primeira consulta, retorno..."
                    class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]"
                />
                <div class="mt-1.5 flex flex-wrap gap-1.5">
                    <button
                        v-for="suggestion in ['Primeira consulta', 'Retorno', 'Avaliação', 'Dúvidas', 'Entrega de exames']"
                        :key="suggestion"
                        type="button"
                        class="rounded-full border px-2.5 py-0.5 text-[10px] transition-colors"
                        :class="
                            form.notes === suggestion
                                ? 'border-teal-300 bg-teal-50 text-teal-700'
                                : 'border-gray-200 bg-gray-50 text-gray-600 hover:bg-gray-100'
                        "
                        @click="form.notes = suggestion"
                    >
                        {{ suggestion }}
                    </button>
                </div>
            </div>

            <!-- Notify Patient -->
            <div class="space-y-2 rounded border border-gray-200 bg-gray-50/70 p-2.5">
                <label class="flex cursor-pointer items-center gap-2 text-[12px] text-gray-700">
                    <input v-model="form.notify_patient" type="checkbox" class="size-3.5 accent-teal-600" />
                    <span>Notificar cliente via WhatsApp</span>
                </label>

                <template v-if="form.notify_patient">
                    <label class="flex cursor-pointer items-center gap-2 text-[11px] text-gray-600">
                        <input v-model="form.customize_message" type="checkbox" class="size-3.5 accent-teal-600" />
                        <span>Customizar mensagem</span>
                    </label>

                    <textarea
                        v-if="form.customize_message"
                        v-model="form.notification_message"
                        rows="3"
                        required
                        class="w-full rounded border border-gray-200 bg-white p-2 text-[12px]"
                    />
                    <p v-else class="rounded border border-gray-200 bg-white px-2.5 py-1.5 text-[11px] text-gray-500">
                        Mensagem automática: "{{ defaultNotificationPreview }}"
                    </p>
                </template>
            </div>

            <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                <button
                    type="button"
                    :disabled="saving"
                    class="h-7 rounded border border-gray-200 px-2.5 text-[11px] text-gray-600"
                    @click="emit('close')"
                >
                    Cancelar
                </button>
                <button
                    type="submit"
                    :disabled="saving"
                    class="inline-flex h-7 items-center gap-1.5 rounded bg-teal-600 px-3 text-[11px] text-white hover:bg-teal-700 disabled:opacity-50"
                >
                    <Icon v-if="saving" name="lucide:loader-2" class="size-3 animate-spin" />
                    <span>{{ props.initialData?.mode === 'reschedule' ? 'Salvar remarcação' : 'Agendar' }}</span>
                </button>
            </div>
        </form>
    </UiModal>
</template>
