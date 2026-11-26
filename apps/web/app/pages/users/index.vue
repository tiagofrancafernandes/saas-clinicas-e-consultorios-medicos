<script setup lang="ts">
import { toast } from 'vue3-toastify';
import { useDataTable } from '~/composables/useDataTable';
import { useAuthStore } from '~/stores/auth';
import type { ClinicUserRecord } from '~/types/clinic';
import type { FilterField, TableColumn } from '~/types/table';
import { apiRequest } from '~/utils/api';

definePageMeta({ permission: 'users.view' });

const auth = useAuthStore();

const users = ref<ClinicUserRecord[]>([]);
const allPermissions = ref<string[]>([]);
const rolesCatalog = ref<Array<{ name: string; default_permissions: string[] }>>([]);

const columns: TableColumn[] = [
    { key: 'name', label: 'Usuário' },
    { key: 'roles', label: 'Papel Base' },
    { key: 'extra_permissions', label: 'Permissões Extras' },
];

const filterFields: FilterField[] = [
    { key: 'name', label: 'Nome', type: 'text' },
    { key: 'email', label: 'E-mail', type: 'text' },
];

const table = useDataTable(users, filterFields, 'name');

const createModalOpen = ref(false);
const permsModalOpen = ref(false);
const selectedUser = ref<ClinicUserRecord | null>(null);
const editingExtras = ref<string[]>([]);

const newUser = ref({
    name: '',
    email: '',
    password: '',
    role: 'RECEPTIONIST',
    extra_permissions: [] as string[],
});

async function loadUsersAndCatalog(): Promise<void> {
    if (!auth.token || !auth.tenantId) return;
    try {
        const [usersRes, catalogRes] = await Promise.all([
            apiRequest<{ data: ClinicUserRecord[] }>('/api/tenant/users', { token: auth.token, tenantId: auth.tenantId }),
            apiRequest<{ permissions: string[]; roles: Array<{ name: string; default_permissions: string[] }> }>('/api/tenant/permissions', {
                token: auth.token,
                tenantId: auth.tenantId,
            }),
        ]);
        users.value = usersRes.data;
        allPermissions.value = catalogRes.permissions;
        rolesCatalog.value = catalogRes.roles;
    } catch {
        toast.error('Não foi possível carregar usuários e permissões.');
    }
}

onMounted(loadUsersAndCatalog);

async function handleCreateUser(): Promise<void> {
    try {
        await apiRequest('/api/tenant/users', {
            method: 'POST',
            token: auth.token,
            tenantId: auth.tenantId,
            body: newUser.value,
        });
        toast.success('Usuário criado com papel e permissões definidos.');
        createModalOpen.value = false;
        newUser.value = { name: '', email: '', password: '', role: 'RECEPTIONIST', extra_permissions: [] };
        await loadUsersAndCatalog();
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao criar usuário.');
    }
}

function openPermissionsModal(record: ClinicUserRecord): void {
    selectedUser.value = record;
    editingExtras.value = [...record.extra_permissions];
    permsModalOpen.value = true;
}

function toggleExtra(perm: string): void {
    const idx = editingExtras.value.indexOf(perm);
    if (idx === -1) editingExtras.value.push(perm);
    else editingExtras.value.splice(idx, 1);
}

async function saveUserPermissions(): Promise<void> {
    if (!selectedUser.value) return;
    try {
        await apiRequest(`/api/tenant/users/${selectedUser.value.id}/permissions`, {
            method: 'PUT',
            token: auth.token,
            tenantId: auth.tenantId,
            body: { extra_permissions: editingExtras.value },
        });
        toast.success(`Permissões extras de ${selectedUser.value.name} atualizadas!`);
        permsModalOpen.value = false;
        await loadUsersAndCatalog();
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao atualizar permissões.');
    }
}
</script>

<template>
    <TemplatesCrudListTemplate
        title="Usuários & Permissões (ACL)"
        description="Gerencie a equipe da clínica, papéis base e permissões extras individuais."
        table-title="Usuários da Clínica"
        table-description="Permissões extras afetam apenas o usuário selecionado, sem alterar o papel padrão."
        search-placeholder="Buscar por nome ou e-mail..."
        :table="table"
        :fields="filterFields"
    >
        <template #header-actions>
            <button
                v-if="auth.can('users.create')"
                id="btn-new-clinic-user"
                type="button"
                class="flex h-8 items-center gap-1.5 rounded bg-teal-600 px-3 text-[12px] font-normal text-white hover:bg-teal-700"
                @click="createModalOpen = true"
            >
                <Icon name="lucide:user-plus" class="size-3.5" />
                <span>Novo usuário</span>
            </button>
        </template>

        <TableDataTable
            :columns="columns"
            :records="table.paginatedRecords.value"
            :total-count="table.filteredRecords.value.length"
            :sort-by="table.sortBy.value"
            :sort-direction="table.sortDirection.value"
            :current-page="table.currentPage.value"
            :per-page="table.perPage.value"
            :total-pages="table.totalPages.value"
            :start-index="table.startIndex.value"
            :page-numbers="table.pageNumbers.value"
            :selected-ids="table.selectedIds.value"
            @sort="table.sort"
            @toggle-all="table.toggleAllOnPage"
            @toggle-row="table.toggleSelection"
            @update:current-page="table.currentPage.value = $event"
            @update:per-page="table.perPage.value = $event"
        >
            <template #row="{ record }">
                <td class="px-4 py-2.5">
                    <div class="text-[12px] text-gray-900">{{ record.name }}</div>
                    <div class="text-[10px] text-gray-400">{{ record.email }}</div>
                </td>
                <td class="px-4 py-2.5">
                    <span class="rounded border border-gray-200 bg-gray-50 px-2 py-0.5 text-[10px] text-gray-700">
                        {{ record.roles?.[0] ?? '—' }}
                    </span>
                </td>
                <td class="px-4 py-2.5">
                    <span v-if="record.extra_permissions.length > 0" class="rounded bg-emerald-50 px-2 py-0.5 text-[10px] text-emerald-700">
                        +{{ record.extra_permissions.length }} extra(s)
                    </span>
                    <span v-else class="text-[11px] text-gray-400">Somente papel base</span>
                </td>
            </template>

            <template #actions="{ record }">
                <button
                    v-if="auth.can('users.permissions.assign')"
                    type="button"
                    title="Gerenciar permissões"
                    class="inline-flex h-7 items-center gap-1 rounded border border-gray-200 px-2 text-[11px] text-gray-600 hover:bg-gray-50"
                    @click="openPermissionsModal(record)"
                >
                    <Icon name="lucide:key-round" class="size-3" />
                    <span>Permissões</span>
                </button>
            </template>
        </TableDataTable>
    </TemplatesCrudListTemplate>

    <!-- Create User Modal -->
    <UiModal :open="createModalOpen" title="Novo Usuário da Clínica" @close="createModalOpen = false">
        <form class="space-y-3 text-[12px]" @submit.prevent="handleCreateUser">
            <div>
                <label class="mb-1 block text-[11px] text-gray-600">Nome</label>
                <input v-model="newUser.name" type="text" required class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
            </div>
            <div>
                <label class="mb-1 block text-[11px] text-gray-600">E-mail</label>
                <input v-model="newUser.email" type="email" required class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Senha</label>
                    <input v-model="newUser.password" type="password" required class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
                </div>
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Papel Base</label>
                    <select v-model="newUser.role" class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px]">
                        <option v-for="r in rolesCatalog" :key="r.name" :value="r.name">{{ r.name }}</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                <button type="button" class="h-7 rounded border border-gray-200 px-2.5 text-[11px] text-gray-600" @click="createModalOpen = false">Cancelar</button>
                <button type="submit" class="h-7 rounded bg-teal-600 px-3 text-[11px] text-white hover:bg-teal-700">Criar usuário</button>
            </div>
        </form>
    </UiModal>

    <!-- Manage User Permissions Modal -->
    <UiModal
        :open="permsModalOpen"
        :title="`Permissões: ${selectedUser?.name ?? ''}`"
        :description="`Papel base: ${selectedUser?.roles?.[0] ?? ''} (herdadas bloqueadas; marque extras individuais).`"
        @close="permsModalOpen = false"
    >
        <div v-if="selectedUser" class="space-y-3 text-[12px]">
            <div class="max-h-64 space-y-1.5 overflow-y-auto pr-1">
                <label
                    v-for="perm in allPermissions"
                    :key="perm"
                    class="flex items-center justify-between rounded border border-gray-200 bg-gray-50/60 px-2.5 py-1.5"
                >
                    <div class="flex items-center gap-2">
                        <input
                            type="checkbox"
                            :checked="selectedUser.role_permissions.includes(perm) || editingExtras.includes(perm)"
                            :disabled="selectedUser.role_permissions.includes(perm)"
                            class="size-3.5 accent-teal-600"
                            @change="toggleExtra(perm)"
                        />
                        <span class="font-mono text-[11px] text-gray-800">{{ perm }}</span>
                    </div>
                    <span v-if="selectedUser.role_permissions.includes(perm)" class="text-[10px] text-gray-400">Do papel</span>
                    <span v-else-if="editingExtras.includes(perm)" class="text-[10px] text-emerald-600">Extra individual</span>
                </label>
            </div>

            <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                <button type="button" class="h-7 rounded border border-gray-200 px-2.5 text-[11px] text-gray-600" @click="permsModalOpen = false">Cancelar</button>
                <button type="button" class="h-7 rounded bg-teal-600 px-3 text-[11px] text-white hover:bg-teal-700" @click="saveUserPermissions">Salvar permissões</button>
            </div>
        </div>
    </UiModal>
</template>
