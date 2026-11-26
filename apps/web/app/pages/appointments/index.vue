<script setup lang="ts">
import { toast } from 'vue3-toastify';
import { useDataTable } from '~/composables/useDataTable';
import { useAuthStore } from '~/stores/auth';
import type { AppointmentRecord } from '~/types/clinic';
import type { FilterField, TableColumn } from '~/types/table';

definePageMeta({ permission: 'appointments.view' });

const auth = useAuthStore();
const route = useRoute();

const appointments = ref<AppointmentRecord[]>([
    { id: 'a1', patient_name: 'Carlos Silva', professional_name: 'Dr. Robert Carter', room_name: 'Sala 102', scheduled_at: '2026-11-25 14:00', status: 'CONFIRMED', billing_type: 'Insurance' },
    { id: 'a2', patient_name: 'Mariana Costa', professional_name: 'Dra. Amanda Stone', room_name: 'Sala 101', scheduled_at: '2026-11-25 14:30', status: 'SCHEDULED', billing_type: 'Private' },
    { id: 'a3', patient_name: 'Juliana Lima', professional_name: 'Dr. Robert Carter', room_name: 'Sala 102', scheduled_at: '2026-11-25 15:00', status: 'IN_CONSULTATION', billing_type: 'Package' },
]);

const columns: TableColumn[] = [
    { key: 'patient_name', label: 'Paciente' },
    { key: 'professional_name', label: 'Profissional' },
    { key: 'room_name', label: 'Sala' },
    { key: 'scheduled_at', label: 'Horário' },
    { key: 'status', label: 'Status' },
];

const filterFields: FilterField[] = [
    { key: 'patient_name', label: 'Paciente', type: 'text' },
    { key: 'professional_name', label: 'Profissional', type: 'text' },
    {
        key: 'status',
        label: 'Status',
        type: 'select',
        options: [
            { label: 'Agendado', value: 'SCHEDULED' },
            { label: 'Confirmado', value: 'CONFIRMED' },
            { label: 'Em consulta', value: 'IN_CONSULTATION' },
            { label: 'Cancelado', value: 'CANCELLED' },
        ],
    },
];

const table = useDataTable(appointments, filterFields, 'scheduled_at');
const createModalOpen = ref(route.query.action === 'create' && auth.can('appointments.create'));
const cancelModalOpen = ref(false);
const targetCancel = ref<AppointmentRecord | null>(null);

const form = ref({
    patient_name: '',
    professional_name: 'Dr. Robert Carter',
    room_name: 'Sala 102',
    scheduled_at: '2026-11-25 16:00',
    billing_type: 'Private' as AppointmentRecord['billing_type'],
});

function handleCreate(): void {
    appointments.value.unshift({
        id: `a-${Date.now()}`,
        patient_name: form.value.patient_name,
        professional_name: form.value.professional_name,
        room_name: form.value.room_name,
        scheduled_at: form.value.scheduled_at,
        status: 'SCHEDULED',
        billing_type: form.value.billing_type,
    });
    toast.success('Agendamento criado com sucesso.');
    createModalOpen.value = false;
    form.value.patient_name = '';
}

function summonPatient(record: AppointmentRecord): void {
    record.status = 'IN_CONSULTATION';
    toast.info(`Paciente ${record.patient_name} chamado para ${record.room_name}.`);
}

function askCancel(record: AppointmentRecord): void {
    targetCancel.value = record;
    cancelModalOpen.value = true;
}

function confirmCancel(): void {
    if (!targetCancel.value) return;
    targetCancel.value.status = 'CANCELLED';
    toast.success('Agendamento cancelado.');
    cancelModalOpen.value = false;
    targetCancel.value = null;
}
</script>

<template>
    <TemplatesCrudListTemplate
        title="Agendamentos"
        description="Controle de horários, confirmações e chamadas para consultório."
        table-title="Agenda da Clínica"
        table-description="Listagem paginada com ordenação por horário, profissional ou status."
        search-placeholder="Buscar paciente, médico ou sala..."
        :table="table"
        :fields="filterFields"
    >
        <template #header-actions>
            <button
                v-if="auth.can('appointments.create')"
                type="button"
                class="flex h-8 items-center gap-1.5 rounded bg-blue-600 px-3 text-[12px] font-normal text-white hover:bg-blue-700"
                @click="createModalOpen = true"
            >
                <Icon name="lucide:plus" class="size-3.5" />
                <span>Novo agendamento</span>
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
                <td class="px-4 py-2.5 text-[12px] text-gray-900">{{ record.patient_name }}</td>
                <td class="px-4 py-2.5 text-[12px] text-gray-600">{{ record.professional_name }}</td>
                <td class="px-4 py-2.5 text-[11px] text-gray-500">{{ record.room_name }}</td>
                <td class="px-4 py-2.5 font-mono text-[11px] text-gray-600">{{ record.scheduled_at }}</td>
                <td class="px-4 py-2.5">
                    <span class="rounded border border-gray-200 bg-gray-50 px-2 py-0.5 text-[10px] text-gray-700">
                        {{ record.status }}
                    </span>
                </td>
            </template>

            <template #actions="{ record }">
                <div class="inline-flex items-center gap-1">
                    <button
                        v-if="auth.can('appointments.summon') && record.status !== 'CANCELLED'"
                        type="button"
                        title="Chamar à sala"
                        class="inline-flex size-7 items-center justify-center rounded text-gray-400 hover:bg-blue-50 hover:text-blue-600"
                        @click="summonPatient(record)"
                    >
                        <Icon name="lucide:volume-2" class="size-3.5" />
                    </button>
                    <button
                        v-if="auth.can('appointments.cancel') && record.status !== 'CANCELLED'"
                        type="button"
                        title="Cancelar"
                        class="inline-flex size-7 items-center justify-center rounded text-gray-400 hover:bg-rose-50 hover:text-rose-600"
                        @click="askCancel(record)"
                    >
                        <Icon name="lucide:x-circle" class="size-3.5" />
                    </button>
                </div>
            </template>
        </TableDataTable>
    </TemplatesCrudListTemplate>

    <UiModal :open="createModalOpen" title="Novo Agendamento" @close="createModalOpen = false">
        <form class="space-y-3 text-[12px]" @submit.prevent="handleCreate">
            <div>
                <label class="mb-1 block text-[11px] text-gray-600">Paciente</label>
                <input v-model="form.patient_name" type="text" required class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Profissional</label>
                    <input v-model="form.professional_name" type="text" required class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
                </div>
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Horário</label>
                    <input v-model="form.scheduled_at" type="text" required class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                <button type="button" class="h-7 rounded border border-gray-200 px-2.5 text-[11px] text-gray-600" @click="createModalOpen = false">Cancelar</button>
                <button type="submit" class="h-7 rounded bg-blue-600 px-3 text-[11px] text-white hover:bg-blue-700">Agendar</button>
            </div>
        </form>
    </UiModal>

    <UiConfirmModal
        :open="cancelModalOpen"
        title="Confirmar cancelamento"
        :message="`Deseja cancelar o agendamento de ${targetCancel?.patient_name} em ${targetCancel?.scheduled_at}?`"
        confirm-label="Cancelar consulta"
        @close="cancelModalOpen = false"
        @confirm="confirmCancel"
    />
</template>
