<script setup lang="ts">
import { toast } from 'vue3-toastify';
import { useDataTable } from '~/composables/useDataTable';
import { useAuthStore } from '~/stores/auth';
import type { WaitlistItem } from '~/types/clinic';
import type { FilterField, TableColumn } from '~/types/table';

definePageMeta({ permission: 'waitlist.view' });

const auth = useAuthStore();

const entries = ref<WaitlistItem[]>([
    { id: 'w1', patient_name: 'Fernanda Alves', phone: '5541988223344', preferred_shift: 'MORNING', specialty: 'Neurologia', status: 'WAITING', created_at: '2026-11-24' },
    { id: 'w2', patient_name: 'Roberto Dias', phone: '5541977334455', preferred_shift: 'AFTERNOON', specialty: 'Dermatologia', status: 'NOTIFIED', created_at: '2026-11-25' },
]);

const columns: TableColumn[] = [
    { key: 'patient_name', label: 'Paciente' },
    { key: 'specialty', label: 'Especialidade' },
    { key: 'preferred_shift', label: 'Turno' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Entrada' },
];

const filterFields: FilterField[] = [
    { key: 'patient_name', label: 'Paciente', type: 'text' },
    {
        key: 'preferred_shift',
        label: 'Turno',
        type: 'select',
        options: [
            { label: 'Manhã', value: 'MORNING' },
            { label: 'Tarde', value: 'AFTERNOON' },
            { label: 'Qualquer', value: 'ANY' },
        ],
    },
];

const table = useDataTable(entries, filterFields, 'created_at');

function notifyCandidate(item: WaitlistItem): void {
    item.status = 'NOTIFIED';
    toast.success(`Oferta de encaixe enviada via WhatsApp para ${item.patient_name}.`);
}
</script>

<template>
    <TemplatesCrudListTemplate
        title="Fila de Espera"
        description="Pacientes aguardando encaixe automático em caso de cancelamentos."
        table-title="Candidatos na Fila"
        table-description="Listagem paginada e ordenável da fila inteligente."
        search-placeholder="Buscar paciente ou especialidade..."
        :table="table"
        :fields="filterFields"
    >
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
                    <div class="text-[12px] text-gray-900">{{ record.patient_name }}</div>
                    <div class="font-mono text-[10px] text-gray-400">{{ record.phone }}</div>
                </td>
                <td class="px-4 py-2.5 text-[12px] text-gray-600">{{ record.specialty }}</td>
                <td class="px-4 py-2.5 text-[11px] text-gray-500">{{ record.preferred_shift }}</td>
                <td class="px-4 py-2.5">
                    <span class="rounded border border-gray-200 bg-gray-50 px-2 py-0.5 text-[10px] text-gray-700">
                        {{ record.status }}
                    </span>
                </td>
                <td class="px-4 py-2.5 text-[11px] tabular-nums text-gray-500">{{ record.created_at }}</td>
            </template>

            <template #actions="{ record }">
                <button
                    v-if="auth.can('waitlist.manage')"
                    type="button"
                    title="Notificar encaixe"
                    class="inline-flex size-7 items-center justify-center rounded text-gray-400 hover:bg-blue-50 hover:text-blue-600"
                    @click="notifyCandidate(record)"
                >
                    <Icon name="lucide:send" class="size-3.5" />
                </button>
            </template>
        </TableDataTable>
    </TemplatesCrudListTemplate>
</template>
