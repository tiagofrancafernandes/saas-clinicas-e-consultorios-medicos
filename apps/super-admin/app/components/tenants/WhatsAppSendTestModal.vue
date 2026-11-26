<script setup lang="ts">
import { whatsappService } from '~/services/whatsappService';
import type { WhatsAppInstance } from '~/types/tenant';

interface Props {
    open: boolean;
    tenantId: string;
    instance: WhatsAppInstance | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    close: [];
}>();

const notify = useNotify();
const sending = ref(false);
const form = reactive({
    phone: '',
    message: 'Mensagem de teste enviada pelo Super Admin.',
});

async function handleSend(): Promise<void> {
    if (!props.instance) {
        return;
    }
    sending.value = true;
    try {
        await whatsappService.sendTest(props.tenantId, props.instance.id, {
            number: form.phone,
            text: form.message,
        });
        notify.success('Mensagem de teste enviada com sucesso.');
        emit('close');
    } catch (error: unknown) {
        notify.error((error as Error).message || 'Falha ao enviar mensagem de teste.');
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <UiModal
        :open="props.open"
        title="Enviar mensagem de teste"
        :subtitle="props.instance ? `Instância: ${props.instance.label}` : ''"
        max-width="max-w-[420px]"
        @close="emit('close')"
    >
        <form id="whatsapp-send-test-form" class="space-y-3" @submit.prevent="handleSend">
            <UiInput v-model="form.phone" label="Telefone destino (com DDI/DDD)" placeholder="5511999999999" required />
            <div>
                <label class="mb-1 block text-[12px] font-normal text-gray-700">Mensagem</label>
                <textarea
                    v-model="form.message"
                    rows="3"
                    required
                    class="w-full rounded-md border border-gray-200 p-2.5 text-[12px] font-light text-gray-900 outline-none hover:border-gray-300 focus:border-gray-400"
                />
            </div>
        </form>

        <template #footer>
            <UiButton variant="outline" size="xs" @click="emit('close')">Cancelar</UiButton>
            <UiButton type="submit" form="whatsapp-send-test-form" variant="primary" size="xs" :loading="sending">
                Enviar teste
            </UiButton>
        </template>
    </UiModal>
</template>
