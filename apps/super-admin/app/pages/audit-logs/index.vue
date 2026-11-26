<script setup lang="ts">
import type { TableColumn } from '~/types/table';

interface AuditLogRow {
    id: string;
    event: string;
    tenant_id: string;
    ip_address: string;
    created_at: string;
    [key: string]: unknown;
}

const records = ref<AuditLogRow[]>([]);

const columns: TableColumn[] = [
    { key: 'event', label: 'Evento' },
    { key: 'tenant_id', label: 'Tenant UUID' },
    { key: 'ip_address', label: 'IP' },
    { key: 'created_at', label: 'Data (UTC)' },
];

const table = useDataTable<AuditLogRow>({
    records,
    searchKeys: ['event', 'tenant_id', 'ip_address'],
    filterFields: [],
    defaultSortBy: 'created_at',
    defaultSortDirection: 'desc',
});
</script>

<template>
    <ListPageTemplate
        v-model:search="table.search.value"
        v-model:filter-panel-open="table.filterPanelOpen.value"
        v-model:draft-filters="table.draftFilters.value"
        v-model:current-page="table.currentPage.value"
        v-model:per-page="table.perPage.value"
        title="Auditoria Central"
        subtitle="Histórico de eventos administrativos (provisionamento, WhatsApp, alterações de plano)."
        section-title="Logs de auditoria"
        search-placeholder="Buscar evento..."
        :columns="columns"
        :filter-fields="[]"
        :records="table.paginatedRecords.value"
        :total-count="table.filteredRecords.value.length"
        :total-pages="table.totalPages.value"
        :start-index="table.startIndex.value"
        :page-numbers="table.pageNumbers.value"
        :sort-by="table.sortBy.value"
        :sort-direction="table.sortDirection.value"
        :applied-filters="table.appliedFilters.value"
        @reset-all="table.resetAll"
        @sort="table.toggleSort"
    />
</template>
