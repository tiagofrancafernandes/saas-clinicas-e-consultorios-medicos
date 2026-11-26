<script setup lang="ts">
import { toast } from 'vue3-toastify';
import { useAuthStore } from '~/stores/auth';
import { apiRequest } from '~/utils/api';
import { buildTimezoneOptions, getDatePartsInTimezone } from '~/utils/timezone';

interface Props {
    open: boolean;
    professionals: Array<{ id: string; name: string }>;
    defaultTimezone: string;
    defaultProfessionalId?: string;
    defaultDate?: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{ (e: 'close'): void; (e: 'created'): void }>();

const auth = useAuthStore();
const saving = ref(false);

const timezoneOptions = computed(() => buildTimezoneOptions(props.defaultTimezone));

const form = reactive({
    professional_id: '',
    reason_preset: 'Férias',
    custom_reason: '',
    full_day: true,
    start_date: '',
    end_date: '',
    start_time: '08:00',
    end_time: '18:00',
    timezone: 'America/Sao_Paulo',
});

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) return;
        const tz = props.defaultTimezone || 'America/Sao_Paulo';
        const today = props.defaultDate || getDatePartsInTimezone(new Date(), tz).date;
        form.professional_id = props.defaultProfessionalId || props.professionals[0]?.id || '';
        form.reason_preset = 'Férias';
        form.custom_reason = '';
        form.full_day = true;
        form.start_date = today;
        form.end_date = today;
        form.start_time = '08:00';
        form.end_time = '18:00';
        form.timezone = tz;
    },
);

async function handleSubmit(): Promise<void> {
    if (!auth.tenantId || !form.professional_id) return;

    const reasonText =
        form.reason_preset === 'Outro' ? form.custom_reason.trim() : form.reason_preset;

    if (!reasonText) {
        toast.error('Informe o motivo do bloqueio.');
        return;
    }

    const startAt = form.full_day ? `${form.start_date} 00:00:00` : `${form.start_date} ${form.start_time}:00`;
    const endAt = form.full_day ? `${form.end_date} 23:59:59` : `${form.end_date} ${form.end_time}:00`;

    saving.value = true;
    try {
        await apiRequest('/api/tenant/unavailabilities', {
            method: 'POST',
            tenantId: auth.tenantId,
            body: {
                professional_id: form.professional_id,
                start_at: startAt,
                end_at: endAt,
                timezone: form.timezone,
                reason: reasonText,
            },
        });
        toast.success('Bloqueio de agenda registrado.');
        emit('created');
        emit('close');
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao registrar bloqueio.');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <UiModal
        :open="props.open"
        title="Bloquear Agenda"
        description="Registre férias, faltas, licenças ou indisponibilidades para impedir agendamentos."
        max-width="sm"
        @close="!saving && emit('close')"
    >
        <form class="space-y-3 text-[12px]" @submit.prevent="handleSubmit">
            <div>
                <label class="mb-1 block text-[11px] text-gray-600">Profissional</label>
                <select
                    v-model="form.professional_id"
                    required
                    class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px]"
                >
                    <option value="" disabled>Selecione...</option>
                    <option v-for="prof in props.professionals" :key="prof.id" :value="prof.id">
                        {{ prof.name }}
                    </option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Motivo</label>
                    <select
                        v-model="form.reason_preset"
                        class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px]"
                    >
                        <option value="Férias">Férias</option>
                        <option value="Falta">Falta</option>
                        <option value="Licença médica">Licença médica</option>
                        <option value="Congresso / Evento">Congresso / Evento</option>
                        <option value="Outro">Outro</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Fuso horário</label>
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

            <div v-if="form.reason_preset === 'Outro'">
                <label class="mb-1 block text-[11px] text-gray-600">Descrição do motivo</label>
                <input
                    v-model="form.custom_reason"
                    type="text"
                    required
                    placeholder="Descreva o motivo do bloqueio"
                    class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]"
                />
            </div>

            <label class="flex cursor-pointer items-center gap-2 text-[12px] text-gray-700">
                <input v-model="form.full_day" type="checkbox" class="size-3.5 accent-blue-600" />
                <span>Dia(s) inteiro(s)</span>
            </label>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Data início</label>
                    <input v-model="form.start_date" type="date" required class="h-8 w-full rounded border border-gray-200 px-2 text-[12px]" />
                </div>
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Data fim</label>
                    <input v-model="form.end_date" type="date" :min="form.start_date" required class="h-8 w-full rounded border border-gray-200 px-2 text-[12px]" />
                </div>
            </div>

            <div v-if="!form.full_day" class="grid grid-cols-2 gap-2">
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Hora início</label>
                    <input v-model="form.start_time" type="time" required class="h-8 w-full rounded border border-gray-200 px-2 text-[12px]" />
                </div>
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Hora fim</label>
                    <input v-model="form.end_time" type="time" required class="h-8 w-full rounded border border-gray-200 px-2 text-[12px]" />
                </div>
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
                    class="inline-flex h-7 items-center gap-1.5 rounded bg-amber-600 px-3 text-[11px] text-white hover:bg-amber-700 disabled:opacity-50"
                >
                    <Icon v-if="saving" name="lucide:loader-2" class="size-3 animate-spin" />
                    <span>Salvar bloqueio</span>
                </button>
            </div>
        </form>
    </UiModal>
</template>
