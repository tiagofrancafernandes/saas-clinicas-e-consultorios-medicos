<script setup lang="ts">
import { TIMEZONE_OPTIONS } from '~/constants/enums';
import { tenantService } from '~/services/tenantService';
import { slugifyText } from '~/utils/formatters';

interface Props {
    open: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    close: [];
    created: [];
}>();

const notify = useNotify();
const submitting = ref(false);
const slugTouched = ref(false);

const form = reactive({
    name: '',
    slug: '',
    tax_id: '',
    timezone: 'America/Sao_Paulo',
});

watch(
    () => form.name,
    (value) => {
        if (!slugTouched.value) {
            form.slug = slugifyText(value);
        }
    }
);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            form.name = '';
            form.slug = '';
            form.tax_id = '';
            form.timezone = 'America/Sao_Paulo';
            slugTouched.value = false;
        }
    }
);

async function handleSubmit(): Promise<void> {
    submitting.value = true;
    try {
        await tenantService.create({
            name: form.name,
            slug: form.slug,
            tax_id: form.tax_id || null,
            timezone: form.timezone,
        });
        notify.success('Clínica cadastrada e provisionada com sucesso.');
        emit('created');
        emit('close');
    } catch (error: unknown) {
        notify.error((error as Error).message || 'Erro ao cadastrar clínica.');
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <UiModal
        :open="props.open"
        title="Cadastrar nova clínica"
        subtitle="Um schema isolado e uma assinatura inicial de 30 dias serão criados."
        @close="emit('close')"
    >
        <form id="tenant-create-form" class="space-y-3.5" @submit.prevent="handleSubmit">
            <UiInput v-model="form.name" label="Nome da clínica" placeholder="Ex: Clínica Saúde Integrada" required />
            <UiInput
                v-model="form.slug"
                label="Slug identificador"
                placeholder="clinica-saude-integrada"
                helper="Apenas letras minúsculas, números e hífens."
                required
                @input="slugTouched = true"
            />
            <UiInput v-model="form.tax_id" label="CNPJ / Documento" placeholder="00.000.000/0001-00" />
            <UiSelect v-model="form.timezone" label="Fuso horário da clínica" :options="TIMEZONE_OPTIONS" required />
        </form>

        <template #footer>
            <UiButton variant="outline" size="xs" @click="emit('close')">Cancelar</UiButton>
            <UiButton type="submit" form="tenant-create-form" variant="primary" size="xs" :loading="submitting">
                Provisionar clínica
            </UiButton>
        </template>
    </UiModal>
</template>
