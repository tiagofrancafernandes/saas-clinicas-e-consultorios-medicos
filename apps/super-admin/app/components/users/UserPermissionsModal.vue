<script setup lang="ts">
import { AVAILABLE_PERMISSIONS, USER_ROLE_OPTIONS } from '~/constants/enums';
import { userService } from '~/services/userService';
import type { CentralUser, UserRole } from '~/types/user';

interface Props {
    open: boolean;
    user: CentralUser | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    close: [];
    updated: [];
}>();

const notify = useNotify();
const submitting = ref(false);
const selectedRole = ref<UserRole>('CLINIC_ADMIN');
const extraPermissions = ref<string[]>([]);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen && props.user) {
            selectedRole.value = (props.user.roles[0] as UserRole) ?? 'CLINIC_ADMIN';
            extraPermissions.value = [...props.user.extra_permissions];
        }
    }
);

function togglePermission(perm: string): void {
    if (extraPermissions.value.includes(perm)) {
        extraPermissions.value = extraPermissions.value.filter((item) => item !== perm);
        return;
    }
    extraPermissions.value.push(perm);
}

async function handleSave(): Promise<void> {
    if (!props.user) {
        return;
    }
    submitting.value = true;
    try {
        await userService.updatePermissions(props.user.id, {
            role: selectedRole.value,
            extra_permissions: extraPermissions.value,
        });
        notify.success('Permissões atualizadas com sucesso.');
        emit('updated');
        emit('close');
    } catch (error: unknown) {
        notify.error((error as Error).message || 'Erro ao atualizar permissões.');
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <UiModal
        :open="props.open"
        title="Gerenciar Papel e Permissões"
        :subtitle="props.user ? `${props.user.name} (${props.user.email})` : ''"
        max-width="max-w-[560px]"
        @close="emit('close')"
    >
        <div class="space-y-4">
            <UiSelect v-model="selectedRole" label="Papel principal" :options="USER_ROLE_OPTIONS" />

            <div>
                <div class="mb-2 text-[12px] font-normal text-gray-700">Permissões extras diretas</div>
                <div
                    class="grid max-h-[240px] grid-cols-1 gap-1.5 overflow-y-auto rounded border border-gray-100 p-2.5 sm:grid-cols-2"
                >
                    <label
                        v-for="perm in AVAILABLE_PERMISSIONS"
                        :key="perm"
                        class="flex cursor-pointer items-center gap-2 rounded px-1.5 py-1 text-[11px] font-light text-gray-700 hover:bg-gray-50"
                    >
                        <input
                            type="checkbox"
                            :checked="extraPermissions.includes(perm)"
                            class="size-3.5 accent-blue-600"
                            @change="togglePermission(perm)"
                        />
                        <span class="truncate font-mono">{{ perm }}</span>
                    </label>
                </div>
            </div>
        </div>

        <template #footer>
            <UiButton variant="outline" size="xs" @click="emit('close')">Cancelar</UiButton>
            <UiButton variant="primary" size="xs" :loading="submitting" @click="handleSave">Salvar permissões</UiButton>
        </template>
    </UiModal>
</template>
