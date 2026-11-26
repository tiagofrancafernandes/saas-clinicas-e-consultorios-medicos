<script setup lang="ts">
import { WHATSAPP_CATEGORY_OPTIONS } from '~/constants/enums';
import { whatsappService } from '~/services/whatsappService';
import type { WhatsAppCategory, WhatsAppInstance } from '~/types/tenant';

interface Props {
    open: boolean;
    tenantId: string;
    instance?: WhatsAppInstance | null;
}

const props = withDefaults(defineProps<Props>(), {
    instance: null,
});

const emit = defineEmits<{
    close: [];
    saved: [];
}>();

const notify = useNotify();
const submitting = ref(false);

const form = reactive({
    label: '',
    phone_number: '',
    instance_name: '',
    token: '',
    category: 'DEFAULT' as WhatsAppCategory,
    attendant_name: '',
    is_default: false,
    active: true,
});

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }
        if (props.instance) {
            form.label = props.instance.label;
            form.phone_number = props.instance.phone_number;
            form.instance_name = props.instance.instance_name;
            form.token = '';
            form.category = props.instance.category;
            form.attendant_name = props.instance.attendant_name ?? '';
            form.is_default = props.instance.is_default;
            form.active = props.instance.active;
            return;
        }
        form.label = '';
        form.phone_number = '';
        form.instance_name = '';
        form.token = '';
        form.category = 'DEFAULT';
        form.attendant_name = '';
        form.is_default = false;
        form.active = true;
    }
);

async function handleSubmit(): Promise<void> {
    submitting.value = true;
    try {
        if (props.instance) {
            await whatsappService.update(props.tenantId, props.instance.id, {
                label: form.label,
                phone_number: form.phone_number,
                instance_name: form.instance_name,
                ...(form.token ? { token: form.token } : {}),
                category: form.category,
                attendant_name: form.attendant_name || null,
                is_default: form.is_default,
                active: form.active,
            });
            notify.success('Instância WhatsApp atualizada.');
        } else {
            await whatsappService.create(props.tenantId, {
                label: form.label,
                phone_number: form.phone_number,
                instance_name: form.instance_name,
                token: form.token,
                category: form.category,
                attendant_name: form.attendant_name || null,
                is_default: form.is_default,
                active: form.active,
            });
            notify.success('Instância WhatsApp cadastrada.');
        }
        emit('saved');
        emit('close');
    } catch (error: unknown) {
        notify.error((error as Error).message || 'Erro ao salvar instância WhatsApp.');
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <UiModal
        :open="props.open"
        :title="props.instance ? 'Editar instância WhatsApp' : 'Nova instância WhatsApp'"
        subtitle="Configuração da Evolution API para esta clínica."
        @close="emit('close')"
    >
        <form id="whatsapp-instance-form" class="space-y-3" @submit.prevent="handleSubmit">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <UiInput v-model="form.label" label="Rótulo" placeholder="Ex: Recepção Principal" required />
                <UiInput v-model="form.phone_number" label="Telefone" placeholder="5511999999999" required />
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <UiInput
                    v-model="form.instance_name"
                    label="Nome da Instância (Evolution)"
                    placeholder="clinica-01"
                    required
                />
                <UiSelect v-model="form.category" label="Categoria" :options="WHATSAPP_CATEGORY_OPTIONS" required />
            </div>
            <UiInput
                v-model="form.token"
                label="Token da Instância"
                :placeholder="props.instance ? 'Deixe em branco para manter o atual' : 'Token da Evolution API'"
                :required="!props.instance"
            />
            <UiInput v-model="form.attendant_name" label="Nome do Atendente (opcional)" placeholder="Ex: Maria" />

            <div class="flex items-center gap-5 pt-1 text-[12px] font-light text-gray-700">
                <label class="flex cursor-pointer items-center gap-2">
                    <input v-model="form.is_default" type="checkbox" class="size-3.5 accent-blue-600" />
                    <span>Instância padrão</span>
                </label>
                <label class="flex cursor-pointer items-center gap-2">
                    <input v-model="form.active" type="checkbox" class="size-3.5 accent-blue-600" />
                    <span>Ativa</span>
                </label>
            </div>
        </form>

        <template #footer>
            <UiButton variant="outline" size="xs" @click="emit('close')">Cancelar</UiButton>
            <UiButton type="submit" form="whatsapp-instance-form" variant="primary" size="xs" :loading="submitting">
                Salvar instância
            </UiButton>
        </template>
    </UiModal>
</template>
