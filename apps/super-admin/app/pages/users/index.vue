<script setup lang="ts">
import { USER_ROLE_OPTIONS } from '~/constants/enums';
import { userService } from '~/services/userService';
import type { FilterField } from '~/types/filter';
import type { StatItem, TableColumn } from '~/types/table';
import type { CentralUser } from '~/types/user';

interface UserRow extends CentralUser {
    primary_role: string;
    [key: string]: unknown;
}

const route = useRoute();
const notify = useNotify();

const loading = ref(true);
const users = ref<UserRow[]>([]);
const createModalOpen = ref(false);
const permissionsModalOpen = ref(false);
const selectedUser = ref<CentralUser | null>(null);

const columns: TableColumn[] = [
    { key: 'name', label: 'Nome' },
    { key: 'email', label: 'E-mail' },
    { key: 'primary_role', label: 'Papel' },
    { key: 'tenant_id', label: 'Clínica (UUID)' },
];

const filterFields: FilterField[] = [
    {
        key: 'name',
        label: 'Nome',
        type: 'text',
        operators: [
            { value: 'contains', label: 'contém' },
            { value: 'equals', label: 'é igual a' },
        ],
    },
    {
        key: 'email',
        label: 'E-mail',
        type: 'text',
        operators: [
            { value: 'contains', label: 'contém' },
            { value: 'equals', label: 'é igual a' },
        ],
    },
    {
        key: 'primary_role',
        label: 'Papel',
        type: 'enum',
        options: USER_ROLE_OPTIONS.map((item) => ({ value: item.value, label: item.label })),
        operators: [
            { value: 'equals', label: 'é' },
            { value: 'not_equals', label: 'não é' },
        ],
    },
];

const table = useDataTable<UserRow>({
    records: users,
    searchKeys: ['name', 'email', 'primary_role', 'tenant_id'],
    filterFields,
    defaultSortBy: 'name',
    defaultSortDirection: 'asc',
    initialPerPage: 10,
});

const stats = computed<StatItem[]>(() => [
    { label: 'Total de Usuários', value: users.value.length },
    {
        label: 'Super Admins',
        value: users.value.filter((u) => u.primary_role === 'SUPER_ADMIN').length,
    },
    {
        label: 'Admins de Clínica',
        value: users.value.filter((u) => u.primary_role === 'CLINIC_ADMIN').length,
    },
    {
        label: 'Profissionais',
        value: users.value.filter((u) => u.primary_role === 'PROFESSIONAL').length,
    },
]);

async function fetchUsers(): Promise<void> {
    loading.value = true;
    try {
        const list = await userService.list();
        users.value = list.map((item) => ({
            ...item,
            primary_role: item.roles[0] ?? 'N/A',
        }));
    } catch (error: unknown) {
        notify.error((error as Error).message || 'Erro ao carregar usuários.');
    } finally {
        loading.value = false;
    }
}

function openPermissions(user: CentralUser): void {
    selectedUser.value = user;
    permissionsModalOpen.value = true;
}

onMounted(async () => {
    await fetchUsers();
    if (route.query.create === '1') {
        createModalOpen.value = true;
    }
});
</script>

<template>
    <div>
        <ListPageTemplate
            v-model:search="table.search.value"
            v-model:filter-panel-open="table.filterPanelOpen.value"
            v-model:draft-filters="table.draftFilters.value"
            v-model:current-page="table.currentPage.value"
            v-model:per-page="table.perPage.value"
            v-model:selected="table.selected.value"
            title="Usuários e ACL"
            subtitle="Gerencie usuários centrais, administradores de clínica e permissões diretas."
            section-title="Todos os usuários"
            search-placeholder="Buscar por nome, e-mail ou papel..."
            :stats="stats"
            :columns="columns"
            :filter-fields="filterFields"
            :records="table.paginatedRecords.value"
            :total-count="table.filteredRecords.value.length"
            :total-pages="table.totalPages.value"
            :start-index="table.startIndex.value"
            :page-numbers="table.pageNumbers.value"
            :sort-by="table.sortBy.value"
            :sort-direction="table.sortDirection.value"
            :applied-filters="table.appliedFilters.value"
            :loading="loading"
            @apply-filters="table.applyFilters"
            @remove-applied-filter="table.removeAppliedFilter"
            @reset-filters="table.resetFilters"
            @reset-all="table.resetAll"
            @sort="table.toggleSort"
        >
            <template #actions>
                <UiButton variant="outline" size="sm" icon="lucide:refresh-cw" @click="fetchUsers">Atualizar</UiButton>
                <UiButton variant="primary" size="sm" icon="lucide:user-plus" @click="createModalOpen = true">
                    Cadastrar usuário
                </UiButton>
            </template>

            <template #cell-primary_role="{ value }">
                <UiBadge color="blue" :dot="false">{{ value }}</UiBadge>
            </template>

            <template #cell-tenant_id="{ value }">
                <NuxtLink
                    v-if="value"
                    :to="`/tenants/${value}`"
                    class="font-mono text-[11px] text-blue-600 hover:underline"
                >
                    {{ String(value).slice(0, 8) }}...
                </NuxtLink>
                <span v-else class="text-[11px] text-gray-400">Central</span>
            </template>

            <template #row-actions="{ record }">
                <UiButton
                    variant="ghost"
                    size="xs"
                    icon="lucide:shield"
                    @click="openPermissions(record as unknown as CentralUser)"
                >
                    Permissões
                </UiButton>
            </template>
        </ListPageTemplate>

        <UserCreateModal :open="createModalOpen" @close="createModalOpen = false" @created="fetchUsers" />

        <UserPermissionsModal
            :open="permissionsModalOpen"
            :user="selectedUser"
            @close="permissionsModalOpen = false"
            @updated="fetchUsers"
        />
    </div>
</template>
