<script setup lang="ts">
import { SUBSCRIPTION_STATUS_META } from '~/constants/enums';
import { tenantService } from '~/services/tenantService';
import type { FilterField } from '~/types/filter';
import type { TableColumn } from '~/types/table';
import { formatDateBR } from '~/utils/formatters';

interface SubscriptionRow {
    id: string;
    tenant_id: string;
    tenant_name: string;
    status: string;
    billing_interval: string;
    current_period_end_utc: string;
    is_exempt: boolean;
    [key: string]: unknown;
}

const notify = useNotify();
const loading = ref(true);
const rows = ref<SubscriptionRow[]>([]);

const columns: TableColumn[] = [
    { key: 'tenant_name', label: 'Clínica' },
    { key: 'status', label: 'Status' },
    { key: 'billing_interval', label: 'Ciclo' },
    { key: 'current_period_end_utc', label: 'Vencimento' },
    { key: 'is_exempt', label: 'Isento' },
];

const filterFields: FilterField[] = [
    {
        key: 'tenant_name',
        label: 'Clínica',
        type: 'text',
        operators: [
            { value: 'contains', label: 'contém' },
            { value: 'equals', label: 'é igual a' },
        ],
    },
    {
        key: 'status',
        label: 'Status',
        type: 'enum',
        options: [
            { value: 'ACTIVE', label: 'Ativo' },
            { value: 'PAST_DUE', label: 'Em atraso' },
            { value: 'SUSPENDED', label: 'Suspenso' },
            { value: 'CANCELLED', label: 'Cancelado' },
            { value: 'COURTESY', label: 'Cortesia' },
        ],
        operators: [{ value: 'equals', label: 'é' }],
    },
    {
        key: 'is_exempt',
        label: 'Isento',
        type: 'boolean',
        options: [
            { value: 'true', label: 'Sim' },
            { value: 'false', label: 'Não' },
        ],
        operators: [{ value: 'equals', label: 'é' }],
    },
];

const table = useDataTable<SubscriptionRow>({
    records: rows,
    searchKeys: ['tenant_name', 'status', 'billing_interval'],
    filterFields,
    defaultSortBy: 'current_period_end_utc',
    defaultSortDirection: 'desc',
    initialPerPage: 10,
});

async function fetchSubscriptions(): Promise<void> {
    loading.value = true;
    try {
        const tenants = await tenantService.list();
        rows.value = tenants.flatMap((t) =>
            (t.subscriptions ?? []).map((sub) => ({
                id: sub.id,
                tenant_id: t.id,
                tenant_name: t.name,
                status: sub.status,
                billing_interval: sub.billing_interval,
                current_period_end_utc: sub.current_period_end_utc,
                is_exempt: sub.is_exempt,
            }))
        );
    } catch (error: unknown) {
        notify.error((error as Error).message || 'Erro ao carregar assinaturas.');
    } finally {
        loading.value = false;
    }
}

onMounted(fetchSubscriptions);
</script>

<template>
    <ListPageTemplate
        v-model:search="table.search.value"
        v-model:filter-panel-open="table.filterPanelOpen.value"
        v-model:draft-filters="table.draftFilters.value"
        v-model:current-page="table.currentPage.value"
        v-model:per-page="table.perPage.value"
        title="Assinaturas e Planos"
        subtitle="Acompanhamento de ciclos de cobrança, status e cortesias por clínica."
        section-title="Assinaturas registradas"
        search-placeholder="Buscar por clínica ou status..."
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
            <UiButton variant="outline" size="sm" icon="lucide:refresh-cw" @click="fetchSubscriptions">
                Atualizar
            </UiButton>
        </template>

        <template #cell-tenant_name="{ record }">
            <NuxtLink :to="`/tenants/${record.tenant_id}`" class="font-normal text-gray-900 hover:text-blue-600">
                {{ record.tenant_name }}
            </NuxtLink>
        </template>

        <template #cell-status="{ value }">
            <UiBadge
                v-if="SUBSCRIPTION_STATUS_META[value as keyof typeof SUBSCRIPTION_STATUS_META]"
                :color="SUBSCRIPTION_STATUS_META[value as keyof typeof SUBSCRIPTION_STATUS_META].color"
            >
                {{ SUBSCRIPTION_STATUS_META[value as keyof typeof SUBSCRIPTION_STATUS_META].label }}
            </UiBadge>
        </template>

        <template #cell-current_period_end_utc="{ value }">
            <span class="tabular-nums text-gray-500">{{ formatDateBR(String(value ?? '')) }}</span>
        </template>

        <template #cell-is_exempt="{ value }">
            <span :class="value ? 'text-blue-600' : 'text-gray-400'">{{ value ? 'Sim' : 'Não' }}</span>
        </template>
    </ListPageTemplate>
</template>
