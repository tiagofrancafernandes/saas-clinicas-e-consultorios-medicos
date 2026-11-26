<script setup lang="ts">
import { toast } from 'vue3-toastify';
import { useDataTable } from '~/composables/useDataTable';
import { useAuthStore } from '~/stores/auth';
import type { WhatsAppInstanceSummary } from '~/types/clinic';
import type { FilterField, TableColumn } from '~/types/table';
import { apiRequest } from '~/utils/api';

definePageMeta({ permission: 'clinic.settings.manage' });

const auth = useAuthStore();

const instances = ref<WhatsAppInstanceSummary[]>([]);

const columns: TableColumn[] = [
    { key: 'label', label: 'Identificação' },
    { key: 'phone_number', label: 'Número' },
    { key: 'category', label: 'Categoria' },
    { key: 'attendant_name', label: 'Atendente' },
    { key: 'active', label: 'Status' },
];

const filterFields: FilterField[] = [
    { key: 'label', label: 'Label', type: 'text' },
    {
        key: 'category',
        label: 'Categoria',
        type: 'select',
        options: [
            { label: 'DEFAULT', value: 'DEFAULT' },
            { label: 'REMINDERS', value: 'REMINDERS' },
            { label: 'MARKETING', value: 'MARKETING' },
            { label: 'SUPPORT', value: 'SUPPORT' },
            { label: 'BILLING', value: 'BILLING' },
        ],
    },
    {
        key: 'active',
        label: 'Ativo',
        type: 'boolean',
        options: [
            { label: 'Ativo', value: true },
            { label: 'Inativo', value: false },
        ],
    },
];

const table = useDataTable(instances, filterFields, 'label');

const editModalOpen = ref(false);
const sendTestModalOpen = ref(false);
const confirmToggleOpen = ref(false);
const targetInstance = ref<WhatsAppInstanceSummary | null>(null);

const editForm = ref({
    label: '',
    category: 'DEFAULT',
    attendant_name: '',
    is_default: false,
    active: true,
});

async function loadInstances(): Promise<void> {
    if (!auth.tenantId) return;
    try {
        const res = await apiRequest<{ data: WhatsAppInstanceSummary[] }>('/api/tenant/whatsapp/instances', {
            tenantId: auth.tenantId,
        });
        instances.value = res.data;
    } catch {
        toast.error('Erro ao carregar instâncias WhatsApp.');
    }
}

onMounted(loadInstances);

function openSendTest(record: WhatsAppInstanceSummary): void {
    targetInstance.value = record;
    sendTestModalOpen.value = true;
}

function openEdit(record: WhatsAppInstanceSummary): void {
    targetInstance.value = record;
    editForm.value = {
        label: record.label,
        category: record.category,
        attendant_name: record.attendant_name ?? '',
        is_default: record.is_default,
        active: record.active,
    };
    editModalOpen.value = true;
}

async function handleSaveEdit(): Promise<void> {
    if (!targetInstance.value || !auth.tenantId) return;
    try {
        await apiRequest(`/api/tenant/whatsapp/instances/${targetInstance.value.id}`, {
            method: 'PUT',
            tenantId: auth.tenantId,
            body: editForm.value,
        });
        toast.success('Instância WhatsApp atualizada.');
        editModalOpen.value = false;
        await loadInstances();
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao atualizar instância.');
    }
}

function askToggleActive(record: WhatsAppInstanceSummary): void {
    targetInstance.value = record;
    confirmToggleOpen.value = true;
}

async function confirmToggleActive(): Promise<void> {
    if (!targetInstance.value || !auth.tenantId) return;
    const nextActive = !targetInstance.value.active;
    try {
        await apiRequest(`/api/tenant/whatsapp/instances/${targetInstance.value.id}`, {
            method: 'PUT',
            tenantId: auth.tenantId,
            body: { active: nextActive },
        });
        toast.success(`Instância ${nextActive ? 'ativada' : 'inativada'}.`);
        confirmToggleOpen.value = false;
        await loadInstances();
    } catch {
        toast.error('Erro ao alterar status.');
    }
}

async function setAsDefault(record: WhatsAppInstanceSummary): Promise<void> {
    if (!auth.tenantId) return;
    try {
        await apiRequest(`/api/tenant/whatsapp/instances/${record.id}`, {
            method: 'PUT',
            tenantId: auth.tenantId,
            body: { is_default: true },
        });
        toast.success(`"${record.label}" definida como padrão da clínica.`);
        await loadInstances();
    } catch {
        toast.error('Erro ao definir instância padrão.');
    }
}

async function testConnection(record: WhatsAppInstanceSummary): Promise<void> {
    if (!auth.tenantId) return;
    try {
        await apiRequest(`/api/tenant/whatsapp/instances/${record.id}/test-connection`, {
            method: 'POST',
            tenantId: auth.tenantId,
        });
        toast.success(`Conexão Evolution API ativa para "${record.label}".`);
    } catch (err: any) {
        toast.error(err?.data?.message || 'Falha ao testar conexão.');
    }
}
</script>

<template>
    <TemplatesCrudListTemplate
        title="Administração de WhatsApp"
        description="Gerencie ativação, padrão (default), categoria e nome do atendente das instâncias vinculadas pelo Super Admin."
        table-title="Instâncias de API do WhatsApp"
        table-description="O cadastro de novas instâncias é exclusivo do Super Admin."
        search-placeholder="Buscar por label, número ou atendente..."
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
                    <div class="flex items-center gap-1.5">
                        <span class="text-[12px] text-gray-900">{{ record.label }}</span>
                        <span v-if="record.is_default" class="rounded bg-teal-50 px-1.5 py-0.5 text-[9px] text-teal-700">DEFAULT</span>
                    </div>
                    <div class="font-mono text-[10px] text-gray-400">{{ record.instance_name }}</div>
                </td>
                <td class="px-4 py-2.5 font-mono text-[11px] text-gray-600">{{ record.phone_number }}</td>
                <td class="px-4 py-2.5">
                    <span class="rounded border border-gray-200 bg-gray-50 px-2 py-0.5 text-[10px] text-gray-700">{{ record.category }}</span>
                </td>
                <td class="px-4 py-2.5 text-[12px] text-gray-600">{{ record.attendant_name || '—' }}</td>
                <td class="px-4 py-2.5">
                    <span
                        class="rounded px-2 py-0.5 text-[10px]"
                        :class="record.active ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                    >
                        {{ record.active ? 'Ativo' : 'Inativo' }}
                    </span>
                </td>
            </template>

            <template #actions="{ record }">
                <div class="inline-flex items-center gap-1">
                    <button
                        v-if="!record.is_default"
                        type="button"
                        title="Definir como padrão"
                        class="inline-flex h-7 items-center rounded border border-gray-200 px-2 text-[10px] text-gray-600 hover:bg-gray-50"
                        @click="setAsDefault(record)"
                    >
                        Padrão
                    </button>
                    <button
                        type="button"
                        title="Editar configurações"
                        class="inline-flex size-7 items-center justify-center rounded text-gray-400 hover:bg-teal-50 hover:text-teal-600"
                        @click="openEdit(record)"
                    >
                        <Icon name="lucide:pencil" class="size-3.5" />
                    </button>
                    <button
                        type="button"
                        title="Testar conexão"
                        class="inline-flex size-7 items-center justify-center rounded text-gray-400 hover:bg-gray-100 hover:text-gray-700"
                        @click="testConnection(record)"
                    >
                        <Icon name="lucide:plug-zap" class="size-3.5" />
                    </button>
                    <button
                        type="button"
                        title="Enviar mensagem de teste"
                        class="inline-flex size-7 items-center justify-center rounded text-gray-400 hover:bg-teal-50 hover:text-teal-600"
                        @click="openSendTest(record)"
                    >
                        <Icon name="lucide:send" class="size-3.5" />
                    </button>
                    <button
                        type="button"
                        :title="record.active ? 'Inativar' : 'Ativar'"
                        class="inline-flex size-7 items-center justify-center rounded text-gray-400 hover:bg-amber-50 hover:text-amber-600"
                        @click="askToggleActive(record)"
                    >
                        <Icon :name="record.active ? 'lucide:pause-circle' : 'lucide:play-circle'" class="size-3.5" />
                    </button>
                </div>
            </template>
        </TableDataTable>
    </TemplatesCrudListTemplate>

    <!-- Edit WhatsApp Instance Modal -->
    <UiModal :open="editModalOpen" title="Configurar Instância WhatsApp" @close="editModalOpen = false">
        <form class="space-y-3 text-[12px]" @submit.prevent="handleSaveEdit">
            <div>
                <label class="mb-1 block text-[11px] text-gray-600">Label</label>
                <input v-model="editForm.label" type="text" required class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Categoria</label>
                    <select v-model="editForm.category" class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px]">
                        <option value="DEFAULT">DEFAULT</option>
                        <option value="REMINDERS">REMINDERS</option>
                        <option value="MARKETING">MARKETING</option>
                        <option value="SUPPORT">SUPPORT</option>
                        <option value="BILLING">BILLING</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Nome do Atendente</label>
                    <input v-model="editForm.attendant_name" type="text" class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
                </div>
            </div>
            <div class="flex items-center gap-4 pt-1">
                <label class="flex items-center gap-1.5">
                    <input v-model="editForm.active" type="checkbox" class="size-3.5 accent-teal-600" />
                    <span>Ativa</span>
                </label>
                <label class="flex items-center gap-1.5">
                    <input v-model="editForm.is_default" type="checkbox" class="size-3.5 accent-teal-600" />
                    <span>Definir como padrão (Default)</span>
                </label>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                <button type="button" class="h-7 rounded border border-gray-200 px-2.5 text-[11px] text-gray-600" @click="editModalOpen = false">Cancelar</button>
                <button type="submit" class="h-7 rounded bg-teal-600 px-3 text-[11px] text-white hover:bg-teal-700">Salvar</button>
            </div>
        </form>
    </UiModal>

    <WhatsappWhatsAppSendTestModal
        :open="sendTestModalOpen"
        :instance="targetInstance"
        @close="sendTestModalOpen = false"
    />

    <UiConfirmModal
        :open="confirmToggleOpen"
        :title="targetInstance?.active ? 'Inativar instância WhatsApp' : 'Ativar instância WhatsApp'"
        :message="`Deseja realmente ${targetInstance?.active ? 'inativar' : 'ativar'} a instância ${targetInstance?.label}?`"
        :confirm-label="targetInstance?.active ? 'Inativar' : 'Ativar'"
        :danger="Boolean(targetInstance?.active)"
        @close="confirmToggleOpen = false"
        @confirm="confirmToggleActive"
    />
</template>
