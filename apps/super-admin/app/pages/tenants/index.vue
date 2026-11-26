<script setup lang="ts">
import { SUBSCRIPTION_STATUS_META } from '~/constants/enums';
import { tenantService } from '~/services/tenantService';
import type { FilterField } from '~/types/filter';
import type { StatItem, TableColumn } from '~/types/table';
import type { Tenant } from '~/types/tenant';
import { formatDateBR, getInitials } from '~/utils/formatters';

interface TenantRow extends Tenant {
    subscription_status: string;
    [key: string]: unknown;
}

const route = useRoute();
const notify = useNotify();

const loading = ref(true);
const rawTenants = ref<TenantRow[]>([]);
const createModalOpen = ref(false);

const columns: TableColumn[] = [
    { key: 'name', label: 'Clínica' },
    { key: 'tax_id', label: 'CNPJ / Documento' },
    { key: 'subscription_status', label: 'Assinatura' },
    { key: 'timezone', label: 'Fuso horário' },
    { key: 'created_at', label: 'Criada em' },
];

const filterFields: FilterField[] = [
    {
        key: 'name',
        label: 'Nome',
        type: 'text',
        operators: [
            { value: 'contains', label: 'contém' },
            { value: 'not_contains', label: 'não contém' },
            { value: 'equals', label: 'é igual a' },
            { value: 'starts_with', label: 'começa com' },
        ],
    },
    {
        key: 'slug',
        label: 'Slug',
        type: 'text',
        operators: [
            { value: 'contains', label: 'contém' },
            { value: 'equals', label: 'é igual a' },
        ],
    },
    {
        key: 'subscription_status',
        label: 'Status Assinatura',
        type: 'enum',
        options: [
            { value: 'ACTIVE', label: 'Ativo' },
            { value: 'PAST_DUE', label: 'Em atraso' },
            { value: 'SUSPENDED', label: 'Suspenso' },
            { value: 'CANCELLED', label: 'Cancelado' },
            { value: 'COURTESY', label: 'Cortesia' },
        ],
        operators: [
            { value: 'equals', label: 'é' },
            { value: 'not_equals', label: 'não é' },
        ],
    },
    {
        key: 'created_at',
        label: 'Data de criação',
        type: 'date',
        operators: [
            { value: 'gte', label: 'a partir de' },
            { value: 'lte', label: 'até' },
        ],
    },
];

const table = useDataTable<TenantRow>({
    records: rawTenants,
    searchKeys: ['name', 'slug', 'tax_id', 'timezone'],
    filterFields,
    defaultSortBy: 'created_at',
    defaultSortDirection: 'desc',
    initialPerPage: 10,
});

const stats = computed<StatItem[]>(() => [
    { label: 'Total de Clínicas', value: rawTenants.value.length },
    {
        label: 'Ativas',
        value: rawTenants.value.filter((t) => t.subscription_status === 'ACTIVE').length,
        positive: true,
    },
    {
        label: 'Cortesia',
        value: rawTenants.value.filter((t) => t.subscription_status === 'COURTESY').length,
    },
    {
        label: 'Suspensas / Atraso',
        value: rawTenants.value.filter((t) => ['SUSPENDED', 'PAST_DUE'].includes(t.subscription_status)).length,
    },
]);

async function fetchTenants(): Promise<void> {
    loading.value = true;
    try {
        const list = await tenantService.list();
        rawTenants.value = list.map((item) => ({
            ...item,
            subscription_status: item.subscriptions?.[0]?.status ?? 'NONE',
        }));
    } catch (error: unknown) {
        notify.error((error as Error).message || 'Erro ao carregar clínicas.');
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    await fetchTenants();
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
            title="Clínicas (Tenants)"
            subtitle="Gerencie clínicas cadastradas, planos ativos, usuários e instâncias WhatsApp."
            section-title="Todas as clínicas"
            section-subtitle="Lista de estabelecimentos provisionados com schema isolado."
            search-placeholder="Buscar por nome, slug ou CNPJ..."
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
            selectable
            @apply-filters="table.applyFilters"
            @remove-applied-filter="table.removeAppliedFilter"
            @reset-filters="table.resetFilters"
            @reset-all="table.resetAll"
            @sort="table.toggleSort"
        >
            <template #actions>
                <UiButton variant="outline" size="sm" icon="lucide:refresh-cw" @click="fetchTenants">
                    Atualizar
                </UiButton>
                <UiButton variant="primary" size="sm" icon="lucide:plus" @click="createModalOpen = true">
                    Cadastrar clínica
                </UiButton>
            </template>

            <template #cell-name="{ record }">
                <NuxtLink :to="`/tenants/${record.id}`" class="group flex items-center gap-2.5">
                    <div
                        class="flex size-7 shrink-0 items-center justify-center rounded bg-blue-50 text-[11px] font-medium text-blue-700"
                    >
                        {{ getInitials(String(record.name)) }}
                    </div>
                    <div>
                        <div class="text-[12px] font-normal text-gray-900 group-hover:text-blue-600">
                            {{ record.name }}
                        </div>
                        <div class="font-mono text-[10px] font-light text-gray-400">
                            {{ record.slug }}
                        </div>
                    </div>
                </NuxtLink>
            </template>

            <template #cell-subscription_status="{ value }">
                <UiBadge
                    v-if="SUBSCRIPTION_STATUS_META[value as keyof typeof SUBSCRIPTION_STATUS_META]"
                    :color="SUBSCRIPTION_STATUS_META[value as keyof typeof SUBSCRIPTION_STATUS_META].color"
                >
                    {{ SUBSCRIPTION_STATUS_META[value as keyof typeof SUBSCRIPTION_STATUS_META].label }}
                </UiBadge>
                <span v-else class="text-gray-400">-</span>
            </template>

            <template #cell-created_at="{ value }">
                <span class="tabular-nums text-gray-500">{{ formatDateBR(String(value ?? '')) }}</span>
            </template>

            <template #row-actions="{ record }">
                <UiButton
                    variant="ghost"
                    size="xs"
                    icon="lucide:settings-2"
                    title="Gerenciar clínica"
                    @click="navigateTo(`/tenants/${record.id}`)"
                >
                    Gerenciar
                </UiButton>
            </template>
        </ListPageTemplate>

        <TenantCreateModal :open="createModalOpen" @close="createModalOpen = false" @created="fetchTenants" />
    </div>
</template>
