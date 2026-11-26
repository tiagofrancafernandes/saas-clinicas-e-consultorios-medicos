<script setup lang="ts">
import { userService } from '~/services/userService';
import type { CentralUser } from '~/types/user';

interface Props {
    tenantId: string;
}

const props = defineProps<Props>();
const notify = useNotify();

const loading = ref(true);
const users = ref<CentralUser[]>([]);
const createModalOpen = ref(false);
const permissionsModalOpen = ref(false);
const selectedUser = ref<CentralUser | null>(null);

async function fetchUsers(): Promise<void> {
    loading.value = true;
    try {
        users.value = await userService.list(props.tenantId);
    } catch (error: unknown) {
        notify.error((error as Error).message || 'Erro ao carregar usuários da clínica.');
    } finally {
        loading.value = false;
    }
}

function openPermissions(user: CentralUser): void {
    selectedUser.value = user;
    permissionsModalOpen.value = true;
}

onMounted(fetchUsers);
</script>

<template>
    <UiCard :padding="false">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
            <div>
                <h3 class="text-[13px] font-medium text-gray-900">Usuários da Clínica</h3>
                <p class="mt-0.5 text-[11px] font-light text-gray-400">
                    Administradores, profissionais e recepcionistas vinculados a este tenant.
                </p>
            </div>
            <UiButton variant="primary" size="xs" icon="lucide:user-plus" @click="createModalOpen = true">
                Novo usuário
            </UiButton>
        </div>

        <div v-if="loading" class="py-12 text-center">
            <Icon name="lucide:loader-2" class="mx-auto size-5 animate-spin text-blue-600" />
        </div>

        <div v-else-if="users.length === 0" class="py-12 text-center">
            <Icon name="lucide:users" class="mx-auto mb-2 size-6 text-gray-300" />
            <div class="text-[12px] font-normal text-gray-700">Nenhum usuário vinculado</div>
        </div>

        <div v-else class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-4 py-2.5 text-[11px] font-medium">Nome</th>
                        <th class="px-4 py-2.5 text-[11px] font-medium">Email</th>
                        <th class="px-4 py-2.5 text-[11px] font-medium">Papel</th>
                        <th class="px-4 py-2.5 text-[11px] font-medium">Permissões</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-medium">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-[12px] font-light">
                    <tr v-for="u in users" :key="u.id" class="hover:bg-gray-50/70">
                        <td class="px-4 py-2.5 font-normal text-gray-900">{{ u.name }}</td>
                        <td class="px-4 py-2.5 text-gray-600">{{ u.email }}</td>
                        <td class="px-4 py-2.5">
                            <UiBadge color="blue" :dot="false">{{ u.roles[0] ?? 'N/A' }}</UiBadge>
                        </td>
                        <td class="px-4 py-2.5 text-gray-500">{{ u.all_permissions.length }} permissões</td>
                        <td class="px-4 py-2.5 text-right">
                            <UiButton variant="ghost" size="xs" icon="lucide:shield" @click="openPermissions(u)">
                                Permissões
                            </UiButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <UserCreateModal
            :open="createModalOpen"
            :default-tenant-id="props.tenantId"
            @close="createModalOpen = false"
            @created="fetchUsers"
        />

        <UserPermissionsModal
            :open="permissionsModalOpen"
            :user="selectedUser"
            @close="permissionsModalOpen = false"
            @updated="fetchUsers"
        />
    </UiCard>
</template>
