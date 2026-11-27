<script setup lang="ts">
import { toast } from 'vue3-toastify';
import type { TypeaheadOption } from '~/components/ui/UiTypeaheadSelect.vue';
import { useAuthStore } from '~/stores/auth';
import { apiRequest } from '~/utils/api';

export interface MedicalRecordApiItem {
    id: string;
    patient_id: string;
    professional_id: string;
    appointment_id?: string | null;
    parent_record_id?: string | null;
    created_by_user_id?: number | null;
    type: 'CLINICAL_NOTE' | 'SOAP' | 'PSYCHOTHERAPY' | 'ANAMNESIS' | 'ADDENDUM';
    visibility: 'PRIVATE' | 'SHARED_TEAM' | 'PUBLIC_CLINIC';
    status: 'DRAFT' | 'FINALIZED';
    title: string;
    content: string;
    structured_data?: Record<string, string> | null;
    finalized_at_utc?: string | null;
    created_at?: string;
    updated_at?: string;
    patient?: { id: string; name: string; phone?: string };
    professional?: { id: string; name: string; specialty?: string };
    created_by?: { id: number; name: string; email?: string } | null;
    addendums?: MedicalRecordApiItem[];
}

interface Props {
    open: boolean;
    record?: MedicalRecordApiItem | null;
    professionals: Array<{ id: string; name: string; specialty?: string }>;
    fixedPatient?: { id: string; name: string } | null;
}

const props = withDefaults(defineProps<Props>(), {
    record: null,
    fixedPatient: null,
});
const emit = defineEmits<{ (e: 'close'): void; (e: 'saved'): void }>();

const auth = useAuthStore();
const canSelectOtherProfessional = computed(
    () =>
        auth.can('medical_records.create_on_behalf')
        || auth.primaryRole === 'CLINIC_ADMIN'
        || auth.primaryRole === 'SUPER_ADMIN',
);

const saving = ref(false);
const autoSaving = ref(false);
const lastSavedLabel = ref('');
const finalizeConfirmOpen = ref(false);
const addendumOpen = ref(false);
const savingAddendum = ref(false);
const addendumForm = reactive({
    title: 'Adendo pós-atendimento',
    content: '',
});

const currentRecordId = ref<string | null>(null);
const currentStatus = ref<'DRAFT' | 'FINALIZED'>('DRAFT');
const currentAddendums = ref<MedicalRecordApiItem[]>([]);
const initialized = ref(false);

const form = reactive({
    patient_id: null as string | number | null,
    patient_name: '',
    professional_id: '' as string,
    type: 'CLINICAL_NOTE' as MedicalRecordApiItem['type'],
    visibility: 'PRIVATE' as MedicalRecordApiItem['visibility'],
    title: '',
    content: '',
    structured_data: {
        subjective: '',
        objective: '',
        assessment: '',
        plan: '',
        demand: '',
        interventions: '',
        clinical_observations: '',
        referrals: '',
        chief_complaint: '',
        history: '',
        medications: '',
        allergies: '',
    },
});

const isReadOnly = computed(() => currentStatus.value === 'FINALIZED');

let autoSaveTimer: ReturnType<typeof setTimeout> | null = null;

function buildStructuredPayload(): Record<string, string> | null {
    if (form.type === 'SOAP') {
        return {
            subjective: form.structured_data.subjective,
            objective: form.structured_data.objective,
            assessment: form.structured_data.assessment,
            plan: form.structured_data.plan,
        };
    }
    if (form.type === 'PSYCHOTHERAPY') {
        return {
            demand: form.structured_data.demand,
            interventions: form.structured_data.interventions,
            clinical_observations: form.structured_data.clinical_observations,
            referrals: form.structured_data.referrals,
        };
    }
    if (form.type === 'ANAMNESIS') {
        return {
            chief_complaint: form.structured_data.chief_complaint,
            history: form.structured_data.history,
            medications: form.structured_data.medications,
            allergies: form.structured_data.allergies,
        };
    }
    return null;
}

function resetStructuredData(data?: Record<string, string> | null): void {
    form.structured_data.subjective = data?.subjective ?? '';
    form.structured_data.objective = data?.objective ?? '';
    form.structured_data.assessment = data?.assessment ?? '';
    form.structured_data.plan = data?.plan ?? '';
    form.structured_data.demand = data?.demand ?? '';
    form.structured_data.interventions = data?.interventions ?? '';
    form.structured_data.clinical_observations = data?.clinical_observations ?? '';
    form.structured_data.referrals = data?.referrals ?? '';
    form.structured_data.chief_complaint = data?.chief_complaint ?? '';
    form.structured_data.history = data?.history ?? '';
    form.structured_data.medications = data?.medications ?? '';
    form.structured_data.allergies = data?.allergies ?? '';
}

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            if (autoSaveTimer) clearTimeout(autoSaveTimer);
            initialized.value = false;
            return;
        }

        lastSavedLabel.value = '';
        addendumOpen.value = false;
        addendumForm.title = 'Adendo pós-atendimento';
        addendumForm.content = '';

        if (props.record) {
            currentRecordId.value = props.record.id;
            currentStatus.value = props.record.status;
            currentAddendums.value = props.record.addendums ?? [];
            form.patient_id = props.record.patient_id;
            form.patient_name = props.record.patient?.name ?? '';
            form.professional_id = props.record.professional_id;
            form.type = props.record.type;
            form.visibility = props.record.visibility;
            form.title = props.record.title;
            form.content = props.record.content ?? '';
            resetStructuredData(props.record.structured_data);
        } else {
            currentRecordId.value = null;
            currentStatus.value = 'DRAFT';
            currentAddendums.value = [];
            form.patient_id = props.fixedPatient?.id ?? null;
            form.patient_name = props.fixedPatient?.name ?? '';
            form.professional_id = auth.user?.professional_id
                || (canSelectOtherProfessional.value ? (props.professionals[0]?.id ?? '') : '');
            form.type = 'CLINICAL_NOTE';
            form.visibility = 'PRIVATE';
            form.title = '';
            form.content = '';
            resetStructuredData(null);
        }

        nextTick(() => {
            initialized.value = true;
        });
    },
);

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

async function performAutoSave(): Promise<void> {
    if (!auth.tenantId || !currentRecordId.value || isReadOnly.value) return;
    if (!form.title.trim()) return;

    autoSaving.value = true;
    try {
        await apiRequest(`/api/tenant/medical-records/${currentRecordId.value}`, {
            method: 'PUT',
            tenantId: auth.tenantId,
            body: {
                title: form.title,
                content: form.content,
                type: form.type,
                visibility: form.visibility,
                structured_data: buildStructuredPayload(),
            },
        });
        const now = new Date();
        lastSavedLabel.value = `Rascunho salvo às ${now.toTimeString().slice(0, 5)}`;
        emit('saved');
    } catch {
        // Silent auto-save failure; manual save remains available
    } finally {
        autoSaving.value = false;
    }
}

watch(
    () => [form.title, form.content, form.type, form.visibility, { ...form.structured_data }],
    () => {
        if (!initialized.value || !props.open || isReadOnly.value || !currentRecordId.value) return;
        if (autoSaveTimer) clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(() => {
            performAutoSave();
        }, 1500);
    },
    { deep: true },
);

async function saveDraft(silent = false): Promise<string | null> {
    if (!auth.tenantId) return null;
    if (!form.patient_id) {
        toast.error('Selecione o paciente.');
        return null;
    }
    if (canSelectOtherProfessional.value && !form.professional_id) {
        toast.error('Selecione o profissional.');
        return null;
    }
    if (!form.title.trim()) {
        toast.error('Informe o título do registro.');
        return null;
    }

    saving.value = true;
    try {
        if (currentRecordId.value) {
            await apiRequest(`/api/tenant/medical-records/${currentRecordId.value}`, {
                method: 'PUT',
                tenantId: auth.tenantId,
                body: {
                    title: form.title,
                    content: form.content,
                    type: form.type,
                    visibility: form.visibility,
                    structured_data: buildStructuredPayload(),
                },
            });
            const now = new Date();
            lastSavedLabel.value = `Rascunho salvo às ${now.toTimeString().slice(0, 5)}`;
            if (!silent) toast.success('Rascunho salvo.');
            emit('saved');
            return currentRecordId.value;
        }

        const res = await apiRequest<{ data: MedicalRecordApiItem }>(
            `/api/tenant/patients/${form.patient_id}/medical-records`,
            {
                method: 'POST',
                tenantId: auth.tenantId,
                body: {
                    professional_id: form.professional_id || undefined,
                    title: form.title,
                    content: form.content,
                    type: form.type,
                    visibility: form.visibility,
                    status: 'DRAFT',
                    structured_data: buildStructuredPayload(),
                },
            },
        );
        currentRecordId.value = res.data.id;
        currentStatus.value = 'DRAFT';
        const now = new Date();
        lastSavedLabel.value = `Rascunho salvo às ${now.toTimeString().slice(0, 5)}`;
        if (!silent) toast.success('Rascunho criado. Auto-save ativado!');
        emit('saved');
        return res.data.id;
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao salvar rascunho.');
        return null;
    } finally {
        saving.value = false;
    }
}

async function confirmFinalize(): Promise<void> {
    if (!auth.tenantId) return;
    const id = await saveDraft(true);
    if (!id) return;

    saving.value = true;
    try {
        await apiRequest(`/api/tenant/medical-records/${id}/finalize`, {
            method: 'POST',
            tenantId: auth.tenantId,
        });
        currentStatus.value = 'FINALIZED';
        finalizeConfirmOpen.value = false;
        toast.success('Prontuário finalizado e bloqueado para alterações.');
        emit('saved');
        emit('close');
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao finalizar prontuário.');
    } finally {
        saving.value = false;
    }
}

async function submitAddendum(): Promise<void> {
    if (!auth.tenantId || !currentRecordId.value || !form.patient_id) return;
    if (!addendumForm.content.trim()) {
        toast.error('Escreva o conteúdo do adendo.');
        return;
    }

    savingAddendum.value = true;
    try {
        const res = await apiRequest<{ data: MedicalRecordApiItem }>(
            `/api/tenant/patients/${form.patient_id}/medical-records`,
            {
                method: 'POST',
                tenantId: auth.tenantId,
                body: {
                    professional_id: form.professional_id || undefined,
                    parent_record_id: currentRecordId.value,
                    title: addendumForm.title || 'Adendo',
                    content: addendumForm.content,
                    visibility: form.visibility,
                },
            },
        );
        currentAddendums.value.push(res.data);
        addendumForm.content = '';
        addendumOpen.value = false;
        toast.success('Adendo registrado com sucesso.');
        emit('saved');
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao registrar adendo.');
    } finally {
        savingAddendum.value = false;
    }
}
</script>

<template>
    <UiModal
        :open="props.open"
        :title="isReadOnly ? 'Visualizar Prontuário (Finalizado)' : currentRecordId ? 'Editar Rascunho de Prontuário' : 'Novo Registro Clínico'"
        :description="
            isReadOnly
                ? 'Registros finalizados são imutáveis. Utilize Adendos para complementar informações.'
                : 'Salve como rascunho durante o atendimento; ao encerrar, clique em Finalizar.'
        "
        max-width="3xl"
        @close="emit('close')"
    >
        <div class="space-y-3 text-[12px]">
            <div class="flex flex-wrap items-center justify-between gap-2 rounded border border-gray-100 bg-gray-50 px-3 py-2">
                <div class="flex items-center gap-2">
                    <span
                        class="rounded border px-2 py-0.5 text-[10px]"
                        :class="
                            isReadOnly
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                : 'border-amber-200 bg-amber-50 text-amber-700'
                        "
                    >
                        {{ isReadOnly ? 'Finalizado' : 'Rascunho' }}
                    </span>
                    <span v-if="autoSaving" class="flex items-center gap-1 text-[11px] text-gray-500">
                        <Icon name="lucide:loader-2" class="size-3 animate-spin" />
                        Salvando rascunho...
                    </span>
                    <span v-else-if="lastSavedLabel" class="text-[11px] text-gray-500">{{ lastSavedLabel }}</span>
                </div>

                <span v-if="props.record?.finalized_at_utc" class="font-mono text-[10px] text-gray-400">
                    Finalizado em: {{ props.record.finalized_at_utc }}
                </span>
            </div>

            <div
                v-if="props.record?.created_by?.name || props.record?.professional?.name"
                class="flex flex-wrap items-center gap-3 rounded border border-teal-100 bg-teal-50/50 px-3 py-1.5 text-[11px] text-gray-600"
            >
                <span v-if="props.record?.professional?.name">
                    Profissional titular: <strong class="font-medium text-gray-800">{{ props.record.professional.name }}</strong>
                </span>
                <span v-if="props.record?.created_by?.name">
                    Inserido por: <strong class="font-medium text-gray-800">{{ props.record.created_by.name }}</strong>
                </span>
            </div>

            <div v-if="!props.fixedPatient || canSelectOtherProfessional" class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <div v-if="!props.fixedPatient" class="sm:col-span-2">
                    <label class="mb-1 block text-[11px] text-gray-600">Paciente</label>
                    <UiTypeaheadSelect
                        v-model="form.patient_id"
                        :preload="Boolean(auth.tenantId) && !currentRecordId"
                        :fetch-options="searchPatients"
                        :initial-option="form.patient_id && form.patient_name ? { value: form.patient_id, label: form.patient_name } : null"
                        :disabled="Boolean(currentRecordId) || isReadOnly"
                        placeholder="Selecione ou busque o paciente..."
                        required
                        @select="form.patient_name = $event?.label ?? ''"
                    />
                </div>

                <div v-if="canSelectOtherProfessional" class="sm:col-span-2">
                    <label class="mb-1 block text-[11px] text-gray-600">Profissional</label>
                    <UiTypeaheadSelect
                        :model-value="form.professional_id || null"
                        :options="props.professionals.map((p) => ({ value: p.id, label: p.name, description: p.specialty || undefined }))"
                        :disabled="Boolean(currentRecordId) || isReadOnly"
                        placeholder="Selecione o profissional..."
                        required
                        @update:model-value="form.professional_id = $event ? String($event) : ''"
                    />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-2 sm:grid-cols-4">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-[11px] text-gray-600">Modelo</label>
                    <select
                        v-model="form.type"
                        :disabled="isReadOnly"
                        class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px] disabled:bg-gray-50"
                    >
                        <option value="CLINICAL_NOTE">Evolução Livre</option>
                        <option value="PSYCHOTHERAPY">Sessão Psicológica</option>
                        <option value="SOAP">Prontuário SOAP</option>
                        <option value="ANAMNESIS">Anamnese</option>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-[11px] text-gray-600">Sigilo</label>
                    <select
                        v-model="form.visibility"
                        :disabled="isReadOnly"
                        class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px] disabled:bg-gray-50"
                    >
                        <option value="PRIVATE">PRIVATE (Somente autor)</option>
                        <option value="SHARED_TEAM">SHARED_TEAM (Equipe)</option>
                        <option value="PUBLIC_CLINIC">PUBLIC_CLINIC (Clínica)</option>
                    </select>
                </div>

                <div class="sm:col-span-4">
                    <label class="mb-1 block text-[11px] text-gray-600">Título</label>
                    <input
                        v-model="form.title"
                        type="text"
                        :disabled="isReadOnly"
                        placeholder="Ex: Sessão #04 / Retorno"
                        class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px] disabled:bg-gray-50"
                    />
                </div>
            </div>

            <!-- Template: SOAP -->
            <div v-if="form.type === 'SOAP'" class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">S — Subjetivo</label>
                    <textarea v-model="form.structured_data.subjective" :disabled="isReadOnly" rows="2" class="w-full rounded border border-gray-200 p-2 text-[12px] disabled:bg-gray-50 min-h-24 max-h-60" />
                </div>
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">O — Objetivo</label>
                    <textarea v-model="form.structured_data.objective" :disabled="isReadOnly" rows="2" class="w-full rounded border border-gray-200 p-2 text-[12px] disabled:bg-gray-50 min-h-24 max-h-60" />
                </div>
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">A — Avaliação</label>
                    <textarea v-model="form.structured_data.assessment" :disabled="isReadOnly" rows="2" class="w-full rounded border border-gray-200 p-2 text-[12px] disabled:bg-gray-50 min-h-24 max-h-60" />
                </div>
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">P — Plano</label>
                    <textarea v-model="form.structured_data.plan" :disabled="isReadOnly" rows="2" class="w-full rounded border border-gray-200 p-2 text-[12px] disabled:bg-gray-50 min-h-24 max-h-60" />
                </div>
            </div>

            <!-- Template: Psychotherapy -->
            <div v-else-if="form.type === 'PSYCHOTHERAPY'" class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Demanda / Foco da Sessão</label>
                    <textarea v-model="form.structured_data.demand" :disabled="isReadOnly" rows="2" class="w-full rounded border border-gray-200 p-2 text-[12px] disabled:bg-gray-50 min-h-24 max-h-60" />
                </div>
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Intervenções Realizadas</label>
                    <textarea v-model="form.structured_data.interventions" :disabled="isReadOnly" rows="2" class="w-full rounded border border-gray-200 p-2 text-[12px] disabled:bg-gray-50 min-h-24 max-h-60" />
                </div>
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Observações Clínicas</label>
                    <textarea v-model="form.structured_data.clinical_observations" :disabled="isReadOnly" rows="2" class="w-full rounded border border-gray-200 p-2 text-[12px] disabled:bg-gray-50 min-h-24 max-h-60" />
                </div>
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Encaminhamentos / Combinados</label>
                    <textarea v-model="form.structured_data.referrals" :disabled="isReadOnly" rows="2" class="w-full rounded border border-gray-200 p-2 text-[12px] disabled:bg-gray-50 min-h-24 max-h-60" />
                </div>
            </div>

            <!-- Template: Anamnesis -->
            <div v-else-if="form.type === 'ANAMNESIS'" class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Queixa Principal</label>
                    <textarea v-model="form.structured_data.chief_complaint" :disabled="isReadOnly" rows="2" class="w-full rounded border border-gray-200 p-2 text-[12px] disabled:bg-gray-50 min-h-24 max-h-60" />
                </div>
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Histórico Clínico</label>
                    <textarea v-model="form.structured_data.history" :disabled="isReadOnly" rows="2" class="w-full rounded border border-gray-200 p-2 text-[12px] disabled:bg-gray-50 min-h-24 max-h-60" />
                </div>
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Medicações em Uso</label>
                    <textarea v-model="form.structured_data.medications" :disabled="isReadOnly" rows="2" class="w-full rounded border border-gray-200 p-2 text-[12px] disabled:bg-gray-50 min-h-24 max-h-60" />
                </div>
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Alergias</label>
                    <textarea v-model="form.structured_data.allergies" :disabled="isReadOnly" rows="2" class="w-full rounded border border-gray-200 p-2 text-[12px] disabled:bg-gray-50 min-h-24 max-h-60" />
                </div>
            </div>

            <div>
                <label class="mb-1 block text-[11px] text-gray-600">
                    {{ form.type === 'CLINICAL_NOTE' ? 'Anotações / Evolução' : 'Observações Gerais (opcional)' }}
                </label>
                <textarea
                    v-model="form.content"
                    :disabled="isReadOnly"
                    rows="4"
                    placeholder="Vá registrando as observações ao longo do atendimento..."
                    class="w-full rounded border border-gray-200 p-2 text-[12px] disabled:bg-gray-50 min-h-24 max-h-60"
                />
            </div>

            <!-- Addendums (when finalized) -->
            <div v-if="isReadOnly" class="space-y-2 rounded border border-gray-200 bg-gray-50/70 p-3">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-medium text-gray-700">Adendos ({{ currentAddendums.length }})</span>
                    <button
                        v-if="!addendumOpen && auth.can('medical_records.create')"
                        type="button"
                        class="inline-flex h-7 items-center gap-1 rounded border border-teal-200 bg-teal-50 px-2 text-[11px] text-teal-700 hover:bg-teal-100"
                        @click="addendumOpen = true"
                    >
                        <Icon name="lucide:plus" class="size-3" />
                        <span>Adicionar Adendo</span>
                    </button>
                </div>

                <div v-if="currentAddendums.length > 0" class="space-y-1.5">
                    <div v-for="ad in currentAddendums" :key="ad.id" class="rounded border border-gray-200 bg-white p-2 text-[11px]">
                        <div class="flex items-center justify-between text-gray-500">
                            <span class="text-gray-800">{{ ad.title }}</span>
                            <span class="font-mono text-[10px]">{{ ad.created_at }}</span>
                        </div>
                        <p class="mt-1 whitespace-pre-wrap text-gray-700">{{ ad.content }}</p>
                    </div>
                </div>

                <div v-if="addendumOpen" class="space-y-2 border-t border-gray-200 pt-2">
                    <input
                        v-model="addendumForm.title"
                        type="text"
                        placeholder="Título do adendo"
                        class="h-8 w-full rounded border border-gray-200 bg-white px-2.5 text-[12px]"
                    />
                    <textarea
                        v-model="addendumForm.content"
                        rows="2"
                        placeholder="Complemento clínico datado..."
                        class="w-full rounded border border-gray-200 bg-white p-2 text-[12px] min-h-12 max-h-60"
                    />
                    <div class="flex justify-end gap-2">
                        <button type="button" class="h-7 rounded border border-gray-200 px-2 text-[11px] text-gray-600" @click="addendumOpen = false">
                            Cancelar
                        </button>
                        <button
                            type="button"
                            :disabled="savingAddendum"
                            class="inline-flex h-7 items-center gap-1 rounded bg-teal-600 px-2.5 text-[11px] text-white hover:bg-teal-700 disabled:opacity-50"
                            @click="submitAddendum"
                        >
                            <Icon v-if="savingAddendum" name="lucide:loader-2" class="size-3 animate-spin" />
                            <span>Salvar Adendo</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-100 pt-3">
                <button type="button" class="h-7 rounded border border-gray-200 px-2.5 text-[11px] text-gray-600" @click="emit('close')">
                    Fechar
                </button>
                <template v-if="!isReadOnly">
                    <button
                        type="button"
                        :disabled="saving"
                        class="inline-flex h-7 items-center gap-1.5 rounded border border-amber-200 bg-amber-50 px-3 text-[11px] text-amber-800 hover:bg-amber-100 disabled:opacity-50"
                        @click="saveDraft(false)"
                    >
                        <Icon v-if="saving" name="lucide:loader-2" class="size-3 animate-spin" />
                        <span>Salvar rascunho</span>
                    </button>
                    <button
                        type="button"
                        :disabled="saving"
                        class="inline-flex h-7 items-center gap-1.5 rounded bg-teal-600 px-3 text-[11px] text-white hover:bg-teal-700 disabled:opacity-50"
                        @click="finalizeConfirmOpen = true"
                    >
                        <Icon name="lucide:check-circle-2" class="size-3" />
                        <span>Finalizar registro</span>
                    </button>
                </template>
            </div>
        </div>
    </UiModal>

    <UiConfirmModal
        :open="finalizeConfirmOpen"
        title="Finalizar registro clínico?"
        message="Após finalizado, o registro não poderá mais ser editado diretamente (apenas adendos poderão ser adicionados)."
        confirm-label="Finalizar definitivamente"
        @close="finalizeConfirmOpen = false"
        @confirm="confirmFinalize"
    />
</template>
