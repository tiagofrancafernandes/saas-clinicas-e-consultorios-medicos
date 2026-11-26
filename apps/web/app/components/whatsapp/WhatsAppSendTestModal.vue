<script setup lang="ts">
import { toast } from 'vue3-toastify';
import { useAuthStore } from '~/stores/auth';
import type { WhatsAppInstanceSummary } from '~/types/clinic';
import { apiRequest } from '~/utils/api';
import { normalizePhoneDigits, validateWhatsAppNumber } from '~/utils/phone';

interface Props {
    open: boolean;
    instance: WhatsAppInstanceSummary | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{ (e: 'close'): void }>();

const auth = useAuthStore();
const sending = ref(false);
const phoneError = ref('');
const form = reactive({
    phone: '',
    message: 'Mensagem de teste enviada pelo Portal da Clínica.',
});

function onPhoneInput(event: Event): void {
    const target = event.target as HTMLInputElement;
    const digits = normalizePhoneDigits(target.value);
    form.phone = digits;
    target.value = digits;
    if (phoneError.value) {
        phoneError.value = '';
    }
}

async function handleSend(): Promise<void> {
    if (!props.instance || !auth.tenantId) {
        return;
    }

    const validation = validateWhatsAppNumber(form.phone);
    form.phone = validation.digits;

    if (!validation.valid) {
        phoneError.value = validation.error || 'Número de WhatsApp inválido.';
        toast.error(phoneError.value);
        return;
    }

    phoneError.value = '';
    sending.value = true;
    try {
        await apiRequest(`/api/tenant/whatsapp/instances/${props.instance.id}/send-test`, {
            method: 'POST',
            tenantId: auth.tenantId,
            body: {
                number: validation.digits,
                text: form.message,
            },
        });
        toast.success('Mensagem de teste enviada com sucesso.');
        emit('close');
    } catch (err: any) {
        toast.error(err?.data?.message || 'Falha ao enviar mensagem de teste.');
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <UiModal
        :open="props.open"
        title="Enviar mensagem de teste"
        :description="props.instance ? `Instância: ${props.instance.label}` : ''"
        max-width="sm"
        @close="emit('close')"
    >
        <form id="clinic-wa-send-test-form" class="space-y-3 text-[12px]" @submit.prevent="handleSend">
            <div>
                <label class="mb-1 block text-[11px] text-gray-600">Telefone destino (DDI + DDD + número)</label>
                <input
                    id="input-wa-test-phone"
                    :value="form.phone"
                    type="text"
                    inputmode="numeric"
                    placeholder="5541984402684"
                    required
                    class="h-8 w-full rounded border px-2.5 text-[12px] text-gray-800 placeholder:text-gray-400"
                    :class="phoneError ? 'border-rose-400' : 'border-gray-200'"
                    @input="onPhoneInput"
                />
                <p v-if="phoneError" class="mt-1 text-[10px] text-rose-600">
                    {{ phoneError }}
                </p>
                <p v-else class="mt-1 text-[10px] text-gray-400">
                    Apenas dígitos (ex: 55 + 41 + 984402684). Caracteres não numéricos são removidos automaticamente.
                </p>
            </div>

            <div>
                <label class="mb-1 block text-[11px] text-gray-600">Mensagem</label>
                <textarea
                    id="input-wa-test-message"
                    v-model="form.message"
                    rows="3"
                    required
                    class="w-full rounded border border-gray-200 p-2 text-[12px] text-gray-800"
                />
            </div>

            <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                <button
                    type="button"
                    class="h-7 rounded border border-gray-200 px-2.5 text-[11px] text-gray-600 hover:bg-gray-50"
                    @click="emit('close')"
                >
                    Cancelar
                </button>
                <button
                    id="btn-submit-wa-send-test"
                    type="submit"
                    :disabled="sending"
                    class="flex h-7 items-center gap-1.5 rounded bg-teal-600 px-3 text-[11px] text-white hover:bg-teal-700 disabled:opacity-50"
                >
                    <Icon v-if="sending" name="lucide:loader-2" class="size-3 animate-spin" />
                    <span>{{ sending ? 'Enviando...' : 'Enviar teste' }}</span>
                </button>
            </div>
        </form>
    </UiModal>
</template>
