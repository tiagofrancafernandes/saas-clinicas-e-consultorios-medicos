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
    { key: 'is_active', label: 'Status' },
    { key: 'extra_permissions', label: 'Permissões Extras' },
];

const filterFields: FilterField[] = [
    { key: 'name', label: 'Nome', type: 'text' },
    { key: 'email', label: 'E-mail', type: 'text' },
    {
        key: 'is_active',
        label: 'Status',
        type: 'boolean',
        options: [
            { label: 'Ativo', value: true },
            { label: 'Inativo', value: false },
        ],
    },
];

const table = useDataTable(users, filterFields, 'name');

const createModalOpen = ref(false);
const detailsModalOpen = ref(false);
const editModalOpen = ref(false);
const permsModalOpen = ref(false);
const resetConfirmTarget = ref<ClinicUserRecord | null>(null);
const toggleConfirmTarget = ref<ClinicUserRecord | null>(null);

const selectedUser = ref<ClinicUserRecord | null>(null);
const editingRole = ref<string>('RECEPTIONIST');
const editingExtras = ref<string[]>([]);

const newUser = ref({
    name: '',
    email: '',
    password: '',
    role: 'RECEPTIONIST',
    specialty: '',
    license_number: '',
    extra_permissions: [] as string[],
});

const editForm = ref({
    name: '',
    email: '',
    role: 'RECEPTIONIST',
    specialty: '',
    license_number: '',
});

const selectedRoleDefaultPerms = computed<string[]>(() => {
    const found = rolesCatalog.value.find((r) => r.name === editingRole.value);
    return found?.default_permissions ?? selectedUser.value?.role_permissions ?? [];
});

async function loadUsersAndCatalog(): Promise<void> {
    if (!auth.tenantId) return;
    try {
        const [usersRes, catalogRes] = await Promise.all([
            apiRequest<{ data: ClinicUserRecord[] }>('/api/tenant/users', { tenantId: auth.tenantId }),
            apiRequest<{ permissions: string[]; roles: Array<{ name: string; default_permissions: string[] }> }>(
                '/api/tenant/permissions',
                { tenantId: auth.tenantId },
            ),
        ]);
        users.value = usersRes.data;
        allPermissions.value = catalogRes.permissions;
        rolesCatalog.value = catalogRes.roles;
    } catch {
        toast.error('Não foi possível carregar usuários e permissões.');
    }
}

watch(
    () => auth.tenantId,
    (id) => {
        if (id) loadUsersAndCatalog();
    },
    { immediate: true },
);

async function handleCreateUser(): Promise<void> {
    if (!auth.tenantId) return;
    try {
        await apiRequest('/api/tenant/users', {
            method: 'POST',
            tenantId: auth.tenantId,
            body: {
                name: newUser.value.name,
                email: newUser.value.email,
                password: newUser.value.password,
                role: newUser.value.role,
                specialty: newUser.value.role === 'PROFESSIONAL' ? (newUser.value.specialty || undefined) : undefined,
                license_number: newUser.value.role === 'PROFESSIONAL' ? (newUser.value.license_number || undefined) : undefined,
                extra_permissions: newUser.value.extra_permissions,
            },
        });
        toast.success('Usuário criado com papel e permissões definidos.');
        createModalOpen.value = false;
        newUser.value = { name: '', email: '', password: '', role: 'RECEPTIONIST', specialty: '', license_number: '', extra_permissions: [] };
        await loadUsersAndCatalog();
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao criar usuário.');
    }
}

async function openDetailsModal(record: ClinicUserRecord): Promise<void> {
    selectedUser.value = record;
    detailsModalOpen.value = true;
    if (!auth.tenantId) return;
    try {
        const res = await apiRequest<{ data: ClinicUserRecord }>(`/api/tenant/users/${record.id}`, {
            tenantId: auth.tenantId,
        });
        selectedUser.value = res.data;
    } catch {
        // Keep cached row data
    }
}

function openEditModal(record: ClinicUserRecord): void {
    selectedUser.value = record;
    editForm.value = {
        name: record.name,
        email: record.email,
        role: record.roles?.[0] ?? 'RECEPTIONIST',
        specialty: record.professional?.specialty ?? '',
        license_number: record.professional?.license_number ?? '',
    };
    editModalOpen.value = true;
}

async function handleUpdateUser(): Promise<void> {
    if (!auth.tenantId || !selectedUser.value) return;
    try {
        await apiRequest(`/api/tenant/users/${selectedUser.value.id}`, {
            method: 'PUT',
            tenantId: auth.tenantId,
            body: {
                name: editForm.value.name,
                email: editForm.value.email,
                role: editForm.value.role,
                specialty: editForm.value.role === 'PROFESSIONAL' ? (editForm.value.specialty || undefined) : undefined,
                license_number: editForm.value.role === 'PROFESSIONAL' ? (editForm.value.license_number || undefined) : undefined,
            },
        });
        toast.success('Dados do usuário atualizados com sucesso.');
        editModalOpen.value = false;
        await loadUsersAndCatalog();
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao atualizar usuário.');
    }
}

function openPermissionsModal(record: ClinicUserRecord): void {
    selectedUser.value = record;
    editingRole.value = record.roles?.[0] ?? 'RECEPTIONIST';
    editingExtras.value = [...record.extra_permissions];
    permsModalOpen.value = true;
}

function toggleExtra(perm: string): void {
    const idx = editingExtras.value.indexOf(perm);
    if (idx === -1) editingExtras.value.push(perm);
    else editingExtras.value.splice(idx, 1);
}

async function saveUserPermissions(): Promise<void> {
    if (!auth.tenantId || !selectedUser.value) return;
    try {
        await apiRequest(`/api/tenant/users/${selectedUser.value.id}/permissions`, {
            method: 'PUT',
            tenantId: auth.tenantId,
            body: {
                role: editingRole.value,
                extra_permissions: editingExtras.value,
            },
        });
        toast.success(`Papel e permissões de ${selectedUser.value.name} atualizados!`);
        permsModalOpen.value = false;
        await loadUsersAndCatalog();
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao atualizar permissões.');
    }
}

async function confirmSendPasswordReset(): Promise<void> {
    if (!auth.tenantId || !resetConfirmTarget.value) return;
    try {
        await apiRequest(`/api/tenant/users/${resetConfirmTarget.value.id}/send-password-reset`, {
            method: 'POST',
            tenantId: auth.tenantId,
        });
        toast.success(`E-mail de redefinição de senha enviado para ${resetConfirmTarget.value.email}.`);
        resetConfirmTarget.value = null;
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao enviar e-mail de redefinição.');
    }
}

async function confirmToggleActive(): Promise<void> {
    if (!auth.tenantId || !toggleConfirmTarget.value) return;
    const target = toggleConfirmTarget.value;
    try {
        await apiRequest(`/api/tenant/users/${target.id}/toggle-active`, {
            method: 'POST',
            tenantId: auth.tenantId,
            body: { is_active: !target.is_active },
        });
        toast.success(target.is_active ? 'Usuário inativado com sucesso.' : 'Usuário reativado com sucesso.');
        toggleConfirmTarget.value = null;
        await loadUsersAndCatalog();
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao alterar status do usuário.');
    }
}
</script>

<template>
    <TemplatesCrudListTemplate
        title="Usuários e Permissões (ACL)"
        description="Gerencie a equipe da clínica, papéis base, permissões extras, status e redefinição de senha."
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
                    <span
                        class="rounded border px-2 py-0.5 text-[10px]"
                        :class="
                            record.is_active
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                : 'border-rose-200 bg-rose-50 text-rose-700'
                        "
                    >
                        {{ record.is_active ? 'Ativo' : 'Inativo' }}
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
                <div class="inline-flex items-center gap-1">
                    <button
                        type="button"
                        title="Detalhes do usuário"
                        class="inline-flex size-7 items-center justify-center rounded border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-gray-800"
                        @click="openDetailsModal(record)"
                    >
                        <Icon name="lucide:eye" class="size-3.5" />
                    </button>
                    <button
                        v-if="auth.can('users.update')"
                        type="button"
                        title="Editar dados e papel"
                        class="inline-flex size-7 items-center justify-center rounded border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-gray-800"
                        @click="openEditModal(record)"
                    >
                        <Icon name="lucide:pencil" class="size-3.5" />
                    </button>
                    <button
                        v-if="auth.can('users.permissions.assign')"
                        type="button"
                        title="Gerenciar papel e permissões"
                        class="inline-flex size-7 items-center justify-center rounded border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-gray-800"
                        @click="openPermissionsModal(record)"
                    >
                        <Icon name="lucide:key-round" class="size-3.5" />
                    </button>
                    <button
                        v-if="auth.can('users.update')"
                        type="button"
                        title="Enviar e-mail de reset de senha"
                        class="inline-flex size-7 items-center justify-center rounded border border-gray-200 text-gray-500 hover:bg-blue-50 hover:text-blue-600"
                        @click="resetConfirmTarget = record"
                    >
                        <Icon name="lucide:mail" class="size-3.5" />
                    </button>
                    <button
                        v-if="auth.can('users.update') && record.id !== auth.user?.id"
                        type="button"
                        :title="record.is_active ? 'Inativar usuário' : 'Reativar usuário'"
                        class="inline-flex size-7 items-center justify-center rounded border border-gray-200 text-gray-500"
                        :class="record.is_active ? 'hover:bg-rose-50 hover:text-rose-600' : 'hover:bg-emerald-50 hover:text-emerald-600'"
                        @click="toggleConfirmTarget = record"
                    >
                        <Icon :name="record.is_active ? 'lucide:user-x' : 'lucide:user-check'" class="size-3.5" />
                    </button>
                </div>
            </template>
        </TableDataTable>
    </TemplatesCrudListTemplate>

    <!-- User Details Modal -->
    <UiModal
        :open="detailsModalOpen"
        :title="`Detalhes: ${selectedUser?.name ?? ''}`"
        max-width="md"
        @close="detailsModalOpen = false"
    >
        <div v-if="selectedUser" class="space-y-3 text-[12px]">
            <div class="grid grid-cols-2 gap-2 rounded border border-gray-200 bg-gray-50 p-3">
                <div>
                    <div class="text-[10px] text-gray-400">Nome</div>
                    <div class="text-gray-900">{{ selectedUser.name }}</div>
                </div>
                <div>
                    <div class="text-[10px] text-gray-400">E-mail</div>
                    <div class="text-gray-900">{{ selectedUser.email }}</div>
                </div>
                <div>
                    <div class="text-[10px] text-gray-400">Papel Base</div>
                    <div class="text-gray-900">{{ selectedUser.roles?.[0] ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-[10px] text-gray-400">Status</div>
                    <div :class="selectedUser.is_active ? 'text-emerald-700' : 'text-rose-700'">
                        {{ selectedUser.is_active ? 'Ativo' : 'Inativo' }}
                    </div>
                </div>
            </div>

            <div v-if="selectedUser.professional" class="rounded border border-blue-100 bg-blue-50/50 p-3">
                <div class="text-[11px] font-medium text-gray-700">Perfil Profissional Vinculado</div>
                <div class="mt-1 text-[11px] text-gray-600">
                    Especialidade: {{ selectedUser.professional.specialty }} · Registro: {{ selectedUser.professional.license_number }}
                </div>
            </div>

            <div>
                <div class="mb-1 text-[11px] font-medium text-gray-700">
                    Permissões Efetivas ({{ selectedUser.all_permissions.length }})
                </div>
                <div class="flex max-h-40 flex-wrap gap-1 overflow-y-auto rounded border border-gray-200 p-2">
                    <span
                        v-for="perm in selectedUser.all_permissions"
                        :key="perm"
                        class="rounded border px-1.5 py-0.5 font-mono text-[10px]"
                        :class="
                            selectedUser.extra_permissions.includes(perm)
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                : 'border-gray-200 bg-gray-50 text-gray-600'
                        "
                    >
                        {{ perm }}
                    </span>
                </div>
            </div>

            <div class="flex justify-end border-t border-gray-100 pt-3">
                <button type="button" class="h-7 rounded border border-gray-200 px-2.5 text-[11px] text-gray-600" @click="detailsModalOpen = false">
                    Fechar
                </button>
            </div>
        </div>
    </UiModal>

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
            <div v-if="newUser.role === 'PROFESSIONAL'" class="grid grid-cols-2 gap-2 rounded border border-gray-200 bg-gray-50 p-2.5">
                <div>
                    <label class="mb-1 block text-[10px] text-gray-500">Especialidade</label>
                    <input v-model="newUser.specialty" type="text" placeholder="Ex: Psicologia" class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px]" />
                </div>
                <div>
                    <label class="mb-1 block text-[10px] text-gray-500">Registro (CRM/CRP)</label>
                    <input v-model="newUser.license_number" type="text" placeholder="Ex: CRM/SP 12345" class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px]" />
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                <button type="button" class="h-7 rounded border border-gray-200 px-2.5 text-[11px] text-gray-600" @click="createModalOpen = false">Cancelar</button>
                <button type="submit" class="h-7 rounded bg-teal-600 px-3 text-[11px] text-white hover:bg-teal-700">Criar usuário</button>
            </div>
        </form>
    </UiModal>

    <!-- Edit User Modal -->
    <UiModal :open="editModalOpen" :title="`Editar Usuário: ${selectedUser?.name ?? ''}`" @close="editModalOpen = false">
        <form class="space-y-3 text-[12px]" @submit.prevent="handleUpdateUser">
            <div>
                <label class="mb-1 block text-[11px] text-gray-600">Nome</label>
                <input v-model="editForm.name" type="text" required class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
            </div>
            <div>
                <label class="mb-1 block text-[11px] text-gray-600">E-mail</label>
                <input v-model="editForm.email" type="email" required class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
            </div>
            <div>
                <label class="mb-1 block text-[11px] text-gray-600">Papel Base</label>
                <select v-model="editForm.role" class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px]">
                    <option v-for="r in rolesCatalog" :key="r.name" :value="r.name">{{ r.name }}</option>
                </select>
            </div>
            <div v-if="editForm.role === 'PROFESSIONAL'" class="grid grid-cols-2 gap-2 rounded border border-gray-200 bg-gray-50 p-2.5">
                <div>
                    <label class="mb-1 block text-[10px] text-gray-500">Especialidade</label>
                    <input v-model="editForm.specialty" type="text" class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px]" />
                </div>
                <div>
                    <label class="mb-1 block text-[10px] text-gray-500">Registro (CRM/CRP)</label>
                    <input v-model="editForm.license_number" type="text" class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px]" />
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                <button type="button" class="h-7 rounded border border-gray-200 px-2.5 text-[11px] text-gray-600" @click="editModalOpen = false">Cancelar</button>
                <button type="submit" class="h-7 rounded bg-teal-600 px-3 text-[11px] text-white hover:bg-teal-700">Salvar alterações</button>
            </div>
        </form>
    </UiModal>

    <!-- Manage User Role e Permissions Modal -->
    <UiModal
        :open="permsModalOpen"
        :title="`Papel e Permissões: ${selectedUser?.name ?? ''}`"
        description="Altere o papel base ou marque permissões extras individuais."
        @close="permsModalOpen = false"
    >
        <div v-if="selectedUser" class="space-y-3 text-[12px]">
            <div>
                <label class="mb-1 block text-[11px] text-gray-600">Papel Base</label>
                <select v-model="editingRole" class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px]">
                    <option v-for="r in rolesCatalog" :key="r.name" :value="r.name">{{ r.name }}</option>
                </select>
            </div>

            <div class="max-h-64 space-y-1.5 overflow-y-auto pr-1">
                <label
                    v-for="perm in allPermissions"
                    :key="perm"
                    class="flex items-center justify-between rounded border border-gray-200 bg-gray-50/60 px-2.5 py-1.5"
                >
                    <div class="flex items-center gap-2">
                        <input
                            type="checkbox"
                            :checked="selectedRoleDefaultPerms.includes(perm) || editingExtras.includes(perm)"
                            :disabled="selectedRoleDefaultPerms.includes(perm)"
                            class="size-3.5 accent-teal-600"
                            @change="toggleExtra(perm)"
                        />
                        <span class="font-mono text-[11px] text-gray-800">{{ perm }}</span>
                    </div>
                    <span v-if="selectedRoleDefaultPerms.includes(perm)" class="text-[10px] text-gray-400">Do papel</span>
                    <span v-else-if="editingExtras.includes(perm)" class="text-[10px] text-emerald-600">Extra individual</span>
                </label>
            </div>

            <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                <button type="button" class="h-7 rounded border border-gray-200 px-2.5 text-[11px] text-gray-600" @click="permsModalOpen = false">Cancelar</button>
                <button type="button" class="h-7 rounded bg-teal-600 px-3 text-[11px] text-white hover:bg-teal-700" @click="saveUserPermissions">Salvar permissões</button>
            </div>
        </div>
    </UiModal>

    <UiConfirmModal
        :open="Boolean(resetConfirmTarget)"
        title="Enviar redefinição de senha?"
        :message="`Enviar link de redefinição de senha para ${resetConfirmTarget?.email}?`"
        confirm-label="Enviar e-mail"
        @close="resetConfirmTarget = null"
        @confirm="confirmSendPasswordReset"
    />

    <UiConfirmModal
        :open="Boolean(toggleConfirmTarget)"
        :title="toggleConfirmTarget?.is_active ? 'Inativar usuário?' : 'Reativar usuário?'"
        :message="
            toggleConfirmTarget?.is_active
                ? `Inativar ${toggleConfirmTarget?.name} revogará o acesso imediato ao sistema.`
                : `Reativar ${toggleConfirmTarget?.name} permitirá novo acesso ao sistema.`
        "
        :confirm-label="toggleConfirmTarget?.is_active ? 'Inativar' : 'Reativar'"
        @close="toggleConfirmTarget = null"
        @confirm="confirmToggleActive"
    />
</template>
