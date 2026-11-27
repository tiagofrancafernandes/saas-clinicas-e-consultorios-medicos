<script setup lang="ts">
import { toast } from 'vue3-toastify';
import type { MedicalRecordApiItem } from '~/components/medical-records/MedicalRecordEditorDrawer.vue';
import type { TypeaheadOption } from '~/components/ui/UiTypeaheadSelect.vue';
import { useDataTable } from '~/composables/useDataTable';
import { useAuthStore } from '~/stores/auth';
import type { FilterField } from '~/types/table';
import { apiRequest } from '~/utils/api';

definePageMeta({ permission: 'medical_records.view' });

const auth = useAuthStore();

const records = ref<MedicalRecordApiItem[]>([]);
const professionals = ref<Array<{ id: string; name: string; specialty?: string }>>([]);
const loading = ref(true);

const filters = reactive({
    search: '',
    patient_id: null as string | number | null,
    status: '',
    type: '',
});

const filterFields: FilterField[] = [
    { key: 'title', label: 'Título', type: 'text' },
    { key: 'created_at', label: 'Data de Registro', type: 'date' },
    {
        key: 'status',
        label: 'Status',
        type: 'select',
        options: [
            { label: 'Rascunho', value: 'DRAFT' },
            { label: 'Finalizado', value: 'FINALIZED' },
        ],
    },
    {
        key: 'type',
        label: 'Modelo',
        type: 'select',
        options: [
            { label: 'Evolução Livre', value: 'CLINICAL_NOTE' },
            { label: 'Sessão Psicológica', value: 'PSYCHOTHERAPY' },
            { label: 'SOAP', value: 'SOAP' },
            { label: 'Anamnese', value: 'ANAMNESIS' },
        ],
    },
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

const editorOpen = ref(false);
const editingRecord = ref<MedicalRecordApiItem | null>(null);

async function searchPatients(searchQuery: string): Promise<TypeaheadOption[]> {
    if (!auth.tenantId) return [];
    const params = new URLSearchParams({ limit: '20' });
    if (searchQuery) params.set('search', searchQuery);
    const res = await apiRequest<{ data: Array<{ id: string; name: string; phone?: string; email?: string }> }>(
        `/api/tenant/patients?${params.toString()}`,
        { tenantId: auth.tenantId },
    );
    return (res.data ?? []).map((p) => ({
        value: p.id,
        label: p.name,
        description: p.phone || p.email || undefined,
    }));
}

async function loadProfessionals(): Promise<void> {
    if (!auth.tenantId) return;
    try {
        const res = await apiRequest<{ data: Array<{ id: string; name: string; specialty?: string }> }>(
            '/api/tenant/professionals',
            { tenantId: auth.tenantId },
        );
        professionals.value = res.data ?? [];
    } catch {
        // Ignore
    }
}

async function loadRecords(): Promise<void> {
    if (!auth.tenantId) return;
    loading.value = true;
    try {
        const params = new URLSearchParams({ per_page: '30' });
        if (filters.search.trim()) params.set('search', filters.search.trim());
        if (filters.patient_id) params.set('patient_id', String(filters.patient_id));
        if (filters.status) params.set('status', filters.status);
        if (filters.type) params.set('type', filters.type);

        for (const cond of table.appliedFilters.value) {
            if (cond.field === 'created_at') {
                if (cond.operator === 'gte' && cond.value) params.set('date_from', String(cond.value));
                if (cond.operator === 'lte' && cond.value) params.set('date_to', String(cond.value));
                if (cond.operator === 'equals' && cond.value) {
                    params.set('date_from', String(cond.value));
                    params.set('date_to', String(cond.value));
                }
                if (cond.operator === 'between') {
                    if (cond.value) params.set('date_from', String(cond.value));
                    if (cond.valueEnd) params.set('date_to', String(cond.valueEnd));
                }
            }
        }

        const res = await apiRequest<{ data: MedicalRecordApiItem[] }>(
            `/api/tenant/medical-records?${params.toString()}`,
            { tenantId: auth.tenantId },
        );
        records.value = res.data ?? [];
    } catch {
        toast.error('Erro ao carregar prontuários.');
    } finally {
        loading.value = false;
    }
}

watch(
    () => auth.tenantId,
    (tenantId) => {
        if (tenantId) {
            loadProfessionals();
            loadRecords();
        }
    },
    { immediate: true },
);

watch(
    () => [filters.patient_id, filters.status, filters.type, table.appliedFilters.value],
    () => {
        if (auth.tenantId) loadRecords();
    },
    { deep: true },
);

function openCreate(): void {
    editingRecord.value = null;
    editorOpen.value = true;
}

function openRecord(record: MedicalRecordApiItem): void {
    editingRecord.value = record;
    editorOpen.value = true;
}

function typeLabel(type: string): string {
    if (type === 'SOAP') return 'SOAP';
    if (type === 'PSYCHOTHERAPY') return 'Sessão Psicológica';
    if (type === 'ANAMNESIS') return 'Anamnese';
    if (type === 'CLINICAL_NOTE') return 'Evolução Livre';
    return type;
}

function formatDateTime(iso?: string | null): string {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(d);
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h1 class="text-[18px] font-normal text-gray-900">Prontuário &amp; Evoluções</h1>
                <p class="text-[12px] text-gray-500">
                    Registros clínicos com rascunho durante o atendimento, auto-save e finalização imutável com adendos.
                </p>
            </div>
            <button
                v-if="auth.can('medical_records.create')"
                type="button"
                class="flex h-8 items-center gap-1.5 rounded bg-teal-600 px-3 text-[12px] font-normal text-white hover:bg-teal-700"
                @click="openCreate"
            >
                <Icon name="lucide:plus" class="size-3.5" />
                <span>Nova evolução</span>
            </button>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-2 rounded border border-gray-200 bg-white p-3">
            <div class="min-w-[200px] flex-1">
                <input
                    v-model="filters.search"
                    type="text"
                    placeholder="Buscar por título, paciente ou texto..."
                    class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]"
                    @keyup.enter="loadRecords"
                />
            </div>

            <div class="w-56">
                <UiTypeaheadSelect
                    v-model="filters.patient_id"
                    :preload="Boolean(auth.tenantId)"
                    :fetch-options="searchPatients"
                    placeholder="Filtrar por paciente..."
                    empty-text="Nenhum paciente encontrado."
                />
            </div>

            <select v-model="filters.status" class="h-8 rounded border border-gray-200 bg-white px-2.5 text-[12px] text-gray-700">
                <option value="">Todos os status</option>
                <option value="DRAFT">Rascunhos</option>
                <option value="FINALIZED">Finalizados</option>
            </select>

            <select v-model="filters.type" class="h-8 rounded border border-gray-200 bg-white px-2.5 text-[12px] text-gray-700">
                <option value="">Todos os modelos</option>
                <option value="CLINICAL_NOTE">Evolução Livre</option>
                <option value="PSYCHOTHERAPY">Sessão Psicológica</option>
                <option value="SOAP">SOAP</option>
                <option value="ANAMNESIS">Anamnese</option>
            </select>

            <button
                type="button"
                class="inline-flex h-8 items-center gap-1.5 rounded border px-2.5 text-[12px] transition-colors"
                :class="
                    table.filterPanelOpen.value || table.appliedFilters.value.length > 0
                        ? 'border-teal-300 bg-teal-50 text-teal-700'
                        : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'
                "
                @click="table.toggleFilterPanel()"
            >
                <Icon name="lucide:sliders-horizontal" class="size-3.5" />
                <span>Filtros avançados</span>
                <span
                    v-if="table.appliedFilters.value.length > 0"
                    class="rounded-full bg-teal-600 px-1.5 py-0.2 text-[10px] text-white"
                >
                    {{ table.appliedFilters.value.length }}
                </span>
            </button>

            <button
                type="button"
                class="inline-flex h-8 items-center gap-1 rounded border border-gray-200 bg-white px-2.5 text-[12px] text-gray-700 hover:bg-gray-50"
                @click="loadRecords"
            >
                <Icon name="lucide:search" class="size-3.5" />
                <span>Buscar</span>
            </button>
        </div>

        <TableAdvancedFilterBuilder
            :open="table.filterPanelOpen.value"
            :fields="filterFields"
            :draft-filters="table.draftFilters.value"
            :applied-filters="table.appliedFilters.value"
            :create-empty-filter="table.createEmptyFilter"
            @update:draft-filters="table.draftFilters.value = $event"
            @apply="table.applyFilters()"
            @reset="table.resetFilters()"
            @remove-applied="table.removeAppliedFilter($event)"
            @close="table.filterPanelOpen.value = false"
        />

        <!-- List -->
        <div class="rounded border border-gray-200 bg-white">
            <div v-if="loading" class="p-8 text-center text-[12px] text-gray-400">
                Carregando prontuários...
            </div>
            <div v-else-if="table.filteredRecords.value.length === 0" class="p-8 text-center text-[12px] text-gray-400">
                Nenhum registro clínico encontrado para os filtros aplicados.
            </div>
            <div v-else class="divide-y divide-gray-100">
                <div
                    v-for="rec in table.filteredRecords.value"
                    :key="rec.id"
                    class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-[12px] hover:bg-gray-50/70"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded border px-2 py-0.5 text-[10px]"
                                :class="
                                    rec.status === 'DRAFT'
                                        ? 'border-amber-200 bg-amber-50 text-amber-700'
                                        : 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                "
                            >
                                {{ rec.status === 'DRAFT' ? 'Rascunho' : 'Finalizado' }}
                            </span>
                            <span class="rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 text-[10px] text-gray-600">
                                {{ typeLabel(rec.type) }}
                            </span>
                            <span class="font-medium text-gray-900">{{ rec.title }}</span>
                            <span v-if="rec.addendums?.length" class="text-[10px] text-teal-600">
                                · {{ rec.addendums.length }} adendo(s)
                            </span>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-3 text-[11px] text-gray-500">
                            <span>Paciente: <strong class="font-normal text-gray-700">{{ rec.patient?.name || '—' }}</strong></span>
                            <span>Profissional: <strong class="font-normal text-gray-700">{{ rec.professional?.name || '—' }}</strong></span>
                            <span v-if="rec.created_by?.name">Inserido por: <strong class="font-normal text-gray-700">{{ rec.created_by.name }}</strong></span>
                            <span>Sigilo: {{ rec.visibility }}</span>
                            <span>Data: <strong class="font-normal text-gray-700">{{ formatDateTime(rec.created_at) }}</strong></span>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-7 items-center gap-1.5 rounded border px-2.5 text-[11px]"
                        :class="
                            rec.status === 'DRAFT'
                                ? 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100'
                                : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'
                        "
                        @click="openRecord(rec)"
                    >
                        <Icon :name="rec.status === 'DRAFT' ? 'lucide:pencil' : 'lucide:eye'" class="size-3" />
                        <span>{{ rec.status === 'DRAFT' ? 'Continuar rascunho' : 'Visualizar / Adendo' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <MedicalRecordsMedicalRecordEditorDrawer
            :open="editorOpen"
            :record="editingRecord"
            :professionals="professionals"
            @close="editorOpen = false"
            @saved="loadRecords"
        />
    </div>
</template>
