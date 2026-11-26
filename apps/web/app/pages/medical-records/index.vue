<script setup lang="ts">
import { toast } from 'vue3-toastify';
import { useDataTable } from '~/composables/useDataTable';
import { useAuthStore } from '~/stores/auth';
import type { MedicalRecordItem } from '~/types/clinic';
import type { FilterField, TableColumn } from '~/types/table';

definePageMeta({ permission: 'medical_records.view' });

const auth = useAuthStore();

const records = ref<MedicalRecordItem[]>([
    { id: 'mr1', patient_name: 'Carlos Silva', author_name: 'Dr. Robert Carter', title: 'Evolução Neurológica - Enxaqueca', visibility: 'PRIVATE', created_at: '2026-11-24' },
    { id: 'mr2', patient_name: 'Mariana Costa', author_name: 'Dra. Amanda Stone', title: 'Avaliação Dermatológica Inicial', visibility: 'SHARED_TEAM', created_at: '2026-11-25' },
]);

const columns: TableColumn[] = [
    { key: 'title', label: 'Título Clínico' },
    { key: 'patient_name', label: 'Paciente' },
    { key: 'author_name', label: 'Autor' },
    { key: 'visibility', label: 'Sigilo' },
    { key: 'created_at', label: 'Data' },
];

const filterFields: FilterField[] = [
    { key: 'title', label: 'Título', type: 'text' },
    { key: 'patient_name', label: 'Paciente', type: 'text' },
    {
        key: 'visibility',
        label: 'Sigilo',
        type: 'select',
        options: [
            { label: 'PRIVATE', value: 'PRIVATE' },
            { label: 'SHARED_TEAM', value: 'SHARED_TEAM' },
            { label: 'PUBLIC_CLINIC', value: 'PUBLIC_CLINIC' },
        ],
    },
];

const table = useDataTable(records, filterFields, 'created_at');
const createModalOpen = ref(false);
const form = ref({
    patient_name: 'Carlos Silva',
    title: '',
    visibility: 'PRIVATE' as MedicalRecordItem['visibility'],
    notes: '',
});

function handleCreate(): void {
    records.value.unshift({
        id: `mr-${Date.now()}`,
        patient_name: form.value.patient_name,
        author_name: auth.user?.name ?? 'Profissional',
        title: form.value.title,
        visibility: form.value.visibility,
        created_at: new Date().toISOString().slice(0, 10),
    });
    toast.success(`Prontuário salvo com sigilo ${form.value.visibility}.`);
    createModalOpen.value = false;
    form.value.title = '';
    form.value.notes = '';
}
</script>

<template>
    <TemplatesCrudListTemplate
        title="Prontuários SOAP"
        description="Evoluções clínicas protegidas por níveis de sigilo médico."
        table-title="Registros Clínicos"
        table-description="Listagem paginada respeitando visibilidade PRIVATE, SHARED_TEAM e PUBLIC_CLINIC."
        search-placeholder="Buscar por título, paciente ou autor..."
        :table="table"
        :fields="filterFields"
    >
        <template #header-actions>
            <button
                v-if="auth.can('medical_records.create')"
                type="button"
                class="flex h-8 items-center gap-1.5 rounded bg-blue-600 px-3 text-[12px] font-normal text-white hover:bg-blue-700"
                @click="createModalOpen = true"
            >
                <Icon name="lucide:plus" class="size-3.5" />
                <span>Nova evolução SOAP</span>
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
                <td class="px-4 py-2.5 text-[12px] text-gray-900">{{ record.title }}</td>
                <td class="px-4 py-2.5 text-[12px] text-gray-600">{{ record.patient_name }}</td>
                <td class="px-4 py-2.5 text-[11px] text-gray-500">{{ record.author_name }}</td>
                <td class="px-4 py-2.5">
                    <span class="rounded border border-gray-200 bg-gray-50 px-2 py-0.5 text-[10px] text-gray-700">
                        {{ record.visibility }}
                    </span>
                </td>
                <td class="px-4 py-2.5 text-[11px] tabular-nums text-gray-500">{{ record.created_at }}</td>
            </template>

            <template #actions>
                <span class="text-[11px] text-gray-400">—</span>
            </template>
        </TableDataTable>
    </TemplatesCrudListTemplate>

    <UiModal :open="createModalOpen" title="Nova Evolução Clínica (SOAP)" @close="createModalOpen = false">
        <form class="space-y-3 text-[12px]" @submit.prevent="handleCreate">
            <div>
                <label class="mb-1 block text-[11px] text-gray-600">Paciente</label>
                <input v-model="form.patient_name" type="text" required class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
            </div>
            <div>
                <label class="mb-1 block text-[11px] text-gray-600">Título da Evolução</label>
                <input v-model="form.title" type="text" required class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
            </div>
            <div>
                <label class="mb-1 block text-[11px] text-gray-600">Sigilo</label>
                <select v-model="form.visibility" class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px]">
                    <option value="PRIVATE">PRIVATE (Somente autor)</option>
                    <option value="SHARED_TEAM">SHARED_TEAM (Equipe & delegados)</option>
                    <option value="PUBLIC_CLINIC">PUBLIC_CLINIC (Clínica)</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-[11px] text-gray-600">Anotações SOAP</label>
                <textarea v-model="form.notes" rows="3" class="w-full rounded border border-gray-200 p-2 text-[12px]" />
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                <button type="button" class="h-7 rounded border border-gray-200 px-2.5 text-[11px] text-gray-600" @click="createModalOpen = false">Cancelar</button>
                <button type="submit" class="h-7 rounded bg-blue-600 px-3 text-[11px] text-white hover:bg-blue-700">Salvar</button>
            </div>
        </form>
    </UiModal>
</template>
