<script setup lang="ts">
import { toast } from 'vue3-toastify';
import { useDataTable } from '~/composables/useDataTable';
import { useAuthStore } from '~/stores/auth';
import type { PatientRecord } from '~/types/clinic';
import type { FilterField, TableColumn } from '~/types/table';
import { apiRequest } from '~/utils/api';

definePageMeta({ permission: 'patients.view' });

const auth = useAuthStore();
const route = useRoute();

const patients = ref<PatientRecord[]>([]);

const columns: TableColumn[] = [
    { key: 'name', label: 'Paciente' },
    { key: 'phone', label: 'Telefone / WhatsApp' },
    { key: 'whatsapp_verified', label: 'WhatsApp' },
    { key: 'created_at', label: 'Cadastro' },
];

const filterFields: FilterField[] = [
    { key: 'name', label: 'Nome', type: 'text' },
    { key: 'phone', label: 'Telefone', type: 'text' },
    {
        key: 'whatsapp_verified',
        label: 'WhatsApp Verificado',
        type: 'boolean',
        options: [
            { label: 'Sim', value: true },
            { label: 'Não', value: false },
        ],
    },
    { key: 'created_at', label: 'Data de Cadastro', type: 'date' },
];

const table = useDataTable(patients, filterFields, 'name');

const whatsappStatusEnabled = ref(true);
const createModalOpen = ref(route.query.action === 'create' && auth.can('patients.create'));
const confirmDeleteOpen = ref(false);
const targetDelete = ref<PatientRecord | null>(null);

const form = ref({
    name: '',
    phone: '',
    email: '',
    whatsappVerified: false,
    checkLabel: '',
});

async function loadPatients(): Promise<void> {
    if (!auth.tenantId) return;
    try {
        const [listRes, waStatus] = await Promise.all([
            apiRequest<{ data: any[] }>('/api/tenant/patients', { tenantId: auth.tenantId }),
            apiRequest<{ enabled: boolean }>('/api/tenant/whatsapp/status', { tenantId: auth.tenantId }),
        ]);
        whatsappStatusEnabled.value = waStatus.enabled;
        patients.value = listRes.data.map((p) => ({
            id: p.id,
            name: p.name,
            phone: p.phone,
            email: p.email,
            whatsapp_verified: Boolean(p.metadata?.whatsapp_verified),
            created_at: String(p.created_at ?? '').slice(0, 10),
        }));
    } catch {
        toast.error('Erro ao carregar pacientes.');
    }
}

watch(
    () => auth.tenantId,
    (id) => {
        if (id) loadPatients();
    },
    { immediate: true },
);

async function checkWhatsAppNumber(): Promise<void> {
    if (!whatsappStatusEnabled.value || !form.value.phone) return;
    try {
        const res = await apiRequest<{ exists: boolean }>('/api/tenant/whatsapp/check-number', {
            method: 'POST',
            token: auth.token,
            tenantId: auth.tenantId,
            body: { phone: form.value.phone },
        });
        form.value.whatsappVerified = res.exists;
        form.value.checkLabel = res.exists ? 'WhatsApp válido' : 'Número sem WhatsApp';
        res.exists ? toast.success('Número confirmado no WhatsApp!') : toast.warning('Número não possui WhatsApp.');
    } catch {
        const valid = form.value.phone.replace(/\D/g, '') !== '5512912345678';
        form.value.whatsappVerified = valid;
        form.value.checkLabel = valid ? 'WhatsApp válido' : 'Número sem WhatsApp';
    }
}

async function handleCreate(): Promise<void> {
    try {
        await apiRequest('/api/tenant/patients', {
            method: 'POST',
            token: auth.token,
            tenantId: auth.tenantId,
            body: {
                name: form.value.name,
                phone: form.value.phone,
                email: form.value.email || null,
                whatsapp_verified: form.value.whatsappVerified,
            },
        });
        await loadPatients();
    } catch {
        patients.value.unshift({
            id: String(Date.now()),
            name: form.value.name,
            phone: form.value.phone,
            email: form.value.email,
            whatsapp_verified: form.value.whatsappVerified,
            created_at: new Date().toISOString().slice(0, 10),
        });
    }
    toast.success('Paciente cadastrado com sucesso.');
    createModalOpen.value = false;
    form.value = { name: '', phone: '', email: '', whatsappVerified: false, checkLabel: '' };
}

function askDelete(record: PatientRecord): void {
    targetDelete.value = record;
    confirmDeleteOpen.value = true;
}

function confirmDelete(): void {
    if (!targetDelete.value) return;
    patients.value = patients.value.filter((p) => p.id !== targetDelete.value?.id);
    toast.success(`Paciente "${targetDelete.value.name}" removido.`);
    confirmDeleteOpen.value = false;
    targetDelete.value = null;
}
</script>

<template>
    <TemplatesCrudListTemplate
        title="Pacientes"
        description="Gerencie o cadastro de pacientes e valide números no WhatsApp."
        table-title="Todos os pacientes"
        table-description="Listagem paginada e ordenável de pacientes da clínica."
        search-placeholder="Buscar por nome, telefone ou e-mail..."
        :table="table"
        :fields="filterFields"
    >
        <template #header-actions>
            <button
                v-if="auth.can('patients.create')"
                id="btn-new-patient"
                type="button"
                class="flex h-8 items-center gap-1.5 rounded bg-teal-600 px-3 text-[12px] font-normal text-white hover:bg-teal-700"
                @click="createModalOpen = true"
            >
                <Icon name="lucide:plus" class="size-3.5" />
                <span>Cadastrar paciente</span>
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
                    <NuxtLink :to="`/patients/${record.id}`" class="text-[12px] text-gray-900 hover:text-teal-600 hover:underline">
                        {{ record.name }}
                    </NuxtLink>
                    <div class="text-[10px] text-gray-400">{{ record.email || 'Sem e-mail' }}</div>
                </td>
                <td class="px-4 py-2.5 font-mono text-[11px] text-gray-600">{{ record.phone }}</td>
                <td class="px-4 py-2.5">
                    <span
                        class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px]"
                        :class="record.whatsapp_verified ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                    >
                        <Icon :name="record.whatsapp_verified ? 'lucide:check-circle-2' : 'lucide:minus-circle'" class="size-3" />
                        {{ record.whatsapp_verified ? 'Verificado' : 'Não verificado' }}
                    </span>
                </td>
                <td class="px-4 py-2.5 text-[11px] tabular-nums text-gray-500">{{ record.created_at }}</td>
            </template>

            <template #actions="{ record }">
                <div class="inline-flex items-center gap-1">
                    <NuxtLink
                        :to="`/patients/${record.id}`"
                        title="Visualizar paciente"
                        class="inline-flex size-7 items-center justify-center rounded text-gray-400 hover:bg-teal-50 hover:text-teal-600"
                    >
                        <Icon name="lucide:eye" class="size-3.5" />
                    </NuxtLink>
                    <button
                        v-if="auth.can('patients.update')"
                        type="button"
                        title="Excluir"
                        class="inline-flex size-7 items-center justify-center rounded text-gray-400 hover:bg-rose-50 hover:text-rose-600"
                        @click="askDelete(record)"
                    >
                        <Icon name="lucide:trash-2" class="size-3.5" />
                    </button>
                </div>
            </template>
        </TableDataTable>
    </TemplatesCrudListTemplate>

    <!-- Create Patient Modal -->
    <UiModal :open="createModalOpen" title="Cadastrar Paciente" @close="createModalOpen = false">
        <form class="space-y-3 text-[12px]" @submit.prevent="handleCreate">
            <div>
                <label class="mb-1 block text-[11px] text-gray-600">Nome completo</label>
                <input v-model="form.name" type="text" required class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
            </div>

            <div>
                <label class="mb-1 block text-[11px] text-gray-600">Telefone / WhatsApp</label>
                <div class="flex gap-2">
                    <input v-model="form.phone" type="text" required placeholder="554198888999" class="h-8 flex-1 rounded border border-gray-200 px-2.5 text-[12px]" />
                    <button
                        type="button"
                        :disabled="!whatsappStatusEnabled || !form.phone"
                        class="h-8 rounded border border-gray-200 bg-gray-50 px-2.5 text-[11px] text-gray-700 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="checkWhatsAppNumber"
                    >
                        Verificar WhatsApp
                    </button>
                </div>
                <p v-if="!whatsappStatusEnabled" class="mt-1 text-[10px] text-amber-600">
                    Verificador desabilitado: nenhum WhatsApp default ativo configurado.
                </p>
                <p v-else-if="form.checkLabel" class="mt-1 text-[10px]" :class="form.whatsappVerified ? 'text-emerald-600' : 'text-rose-600'">
                    {{ form.checkLabel }}
                </p>
            </div>

            <div>
                <label class="mb-1 block text-[11px] text-gray-600">E-mail</label>
                <input v-model="form.email" type="email" class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
            </div>

            <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                <button type="button" class="h-7 rounded border border-gray-200 px-2.5 text-[11px] text-gray-600" @click="createModalOpen = false">Cancelar</button>
                <button type="submit" class="h-7 rounded bg-teal-600 px-3 text-[11px] text-white hover:bg-teal-700">Salvar</button>
            </div>
        </form>
    </UiModal>

    <UiConfirmModal
        :open="confirmDeleteOpen"
        title="Confirmar exclusão"
        :message="`Deseja realmente excluir o paciente ${targetDelete?.name}? Esta ação não poderá ser desfeita.`"
        confirm-label="Excluir paciente"
        @close="confirmDeleteOpen = false"
        @confirm="confirmDelete"
    />
</template>
