<script setup lang="ts">
import { USER_ROLE_OPTIONS } from '~/constants/enums';
import { userService } from '~/services/userService';
import type { UserRole } from '~/types/user';

interface Props {
    open: boolean;
    defaultTenantId?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    defaultTenantId: null,
});

const emit = defineEmits<{
    close: [];
    created: [];
}>();

const notify = useNotify();
const submitting = ref(false);

const form = reactive({
    name: '',
    email: '',
    password: '',
    role: 'CLINIC_ADMIN' as UserRole,
    tenant_id: '',
});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            form.name = '';
            form.email = '';
            form.password = '';
            form.role = props.defaultTenantId ? 'CLINIC_ADMIN' : 'SUPER_ADMIN';
            form.tenant_id = props.defaultTenantId ?? '';
        }
    }
);

async function handleSubmit(): Promise<void> {
    submitting.value = true;
    try {
        await userService.create({
            name: form.name,
            email: form.email,
            password: form.password,
            role: form.role,
            tenant_id: form.tenant_id || null,
        });
        notify.success('Usuário cadastrado com sucesso.');
        emit('created');
        emit('close');
    } catch (error: unknown) {
        notify.error((error as Error).message || 'Erro ao cadastrar usuário.');
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <UiModal
        :open="props.open"
        title="Cadastrar novo usuário"
        subtitle="Defina o papel de acesso e vínculo com clínica (opcional para Super Admin)."
        @close="emit('close')"
    >
        <form id="user-create-form" class="space-y-3" @submit.prevent="handleSubmit">
            <UiInput v-model="form.name" label="Nome completo" placeholder="Ex: Dra. Ana Costa" required />
            <UiInput v-model="form.email" label="E-mail" type="email" placeholder="ana@clinica.com" required />
            <UiInput
                v-model="form.password"
                label="Senha inicial"
                type="password"
                placeholder="Mínimo 6 caracteres"
                required
            />
            <UiSelect v-model="form.role" label="Papel (Role)" :options="USER_ROLE_OPTIONS" required />
            <UiInput
                v-if="!props.defaultTenantId"
                v-model="form.tenant_id"
                label="UUID da Clínica (opcional)"
                placeholder="Deixe vazio para usuário central"
            />
        </form>

        <template #footer>
            <UiButton variant="outline" size="xs" @click="emit('close')">Cancelar</UiButton>
            <UiButton type="submit" form="user-create-form" variant="primary" size="xs" :loading="submitting">
                Criar usuário
            </UiButton>
        </template>
    </UiModal>
</template>
