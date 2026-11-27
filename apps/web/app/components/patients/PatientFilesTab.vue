<script setup lang="ts">
import { toast } from 'vue3-toastify';
import { useAuthStore } from '~/stores/auth';
import { apiRequest } from '~/utils/api';

interface PatientFileItem {
    id: string;
    collection: string;
    visibility: string;
    original_filename: string;
    mime_type: string;
    size_bytes: number;
    created_at: string;
}

interface Props {
    patientId: string;
}

const props = defineProps<Props>();
const auth = useAuthStore();
const config = useRuntimeConfig();

const files = ref<PatientFileItem[]>([]);
const loading = ref(false);
const uploading = ref(false);
const uploadModalOpen = ref(false);
const deleteTarget = ref<PatientFileItem | null>(null);

const selectedFile = ref<File | null>(null);
const uploadForm = reactive({
    collection: 'exams',
    visibility: 'SHARED_TEAM',
});

const COLLECTION_LABELS: Record<string, string> = {
    exams: 'Exames',
    prescriptions: 'Receitas / Prescrições',
    scans: 'Digitalizações',
    reports: 'Laudos / Atestados',
    other: 'Outros',
};

function collectionLabel(collection: string): string {
    return COLLECTION_LABELS[collection] || collection;
}

function formatSize(bytes: number): string {
    if (!bytes) return '0 KB';
    if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function formatDate(iso: string): string {
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(d);
}

async function loadFiles(): Promise<void> {
    if (!auth.tenantId) return;
    loading.value = true;
    try {
        const res = await apiRequest<{ data: PatientFileItem[] }>(`/api/tenant/patients/${props.patientId}/files`, {
            tenantId: auth.tenantId,
        });
        files.value = res.data ?? [];
    } catch {
        toast.error('Erro ao carregar documentos do paciente.');
    } finally {
        loading.value = false;
    }
}

watch(
    () => [props.patientId, auth.tenantId],
    ([id, tenantId]) => {
        if (id && tenantId) {
            loadFiles();
        }
    },
    { immediate: true },
);

function onFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    selectedFile.value = input.files?.[0] ?? null;
}

async function handleUpload(): Promise<void> {
    if (!auth.tenantId) return;
    if (!selectedFile.value) {
        toast.error('Selecione um arquivo para anexar.');
        return;
    }

    const body = new FormData();
    body.append('file', selectedFile.value);
    body.append('collection', uploadForm.collection);
    body.append('visibility', uploadForm.visibility);

    uploading.value = true;
    try {
        await apiRequest(`/api/tenant/patients/${props.patientId}/files`, {
            method: 'POST',
            tenantId: auth.tenantId,
            body,
        });
        toast.success('Documento anexado com sucesso.');
        selectedFile.value = null;
        uploadModalOpen.value = false;
        await loadFiles();
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao enviar documento.');
    } finally {
        uploading.value = false;
    }
}

async function handleDownload(file: PatientFileItem): Promise<void> {
    if (!auth.tenantId) return;
    try {
        const apiBase = String(config.public.apiBase || 'http://localhost:8000').replace(/\/$/, '');
        const response = await fetch(`${apiBase}/api/tenant/patients/${props.patientId}/files/${file.id}/download`, {
            headers: {
                Authorization: `Bearer ${auth.token || ''}`,
                'X-Tenant-ID': auth.tenantId,
            },
        });
        if (!response.ok) {
            throw new Error('Download failed');
        }
        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = file.original_filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    } catch {
        toast.error('Não foi possível baixar o arquivo.');
    }
}

async function confirmDelete(): Promise<void> {
    if (!auth.tenantId || !deleteTarget.value) return;
    try {
        await apiRequest(`/api/tenant/patients/${props.patientId}/files/${deleteTarget.value.id}`, {
            method: 'DELETE',
            tenantId: auth.tenantId,
        });
        toast.success('Documento removido.');
        deleteTarget.value = null;
        await loadFiles();
    } catch {
        toast.error('Erro ao remover documento.');
    }
}
</script>

<template>
    <div class="rounded border border-gray-200 bg-white p-4">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-[13px] font-normal text-gray-900">Arquivos &amp; Exames</h2>
                <p class="text-[11px] text-gray-400">Exames, receitas, digitalizações e laudos anexados ao paciente.</p>
            </div>
            <button
                v-if="auth.can('files.upload')"
                id="btn-open-upload-patient-file"
                type="button"
                class="inline-flex h-7 items-center gap-1.5 rounded bg-teal-600 px-2.5 text-[11px] text-white hover:bg-teal-700"
                @click="uploadModalOpen = true"
            >
                <Icon name="lucide:upload" class="size-3" />
                <span>Anexar documento</span>
            </button>
        </div>

        <div v-if="loading" class="py-8 text-center text-[12px] text-gray-400">
            Carregando documentos...
        </div>
        <div v-else-if="files.length === 0" class="py-8 text-center text-[12px] text-gray-400">
            Nenhum arquivo ou exame anexado recentemente.
        </div>
        <div v-else class="divide-y divide-gray-100 text-[12px]">
            <div v-for="file in files" :key="file.id" class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded border border-teal-200 bg-teal-50 px-1.5 py-0.5 text-[10px] text-teal-700">
                            {{ collectionLabel(file.collection) }}
                        </span>
                        <span class="truncate font-medium text-gray-900">{{ file.original_filename }}</span>
                        <span class="rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 text-[10px] text-gray-500">
                            {{ file.visibility }}
                        </span>
                    </div>
                    <div class="mt-0.5 text-[11px] text-gray-400">
                        {{ formatSize(file.size_bytes) }} · {{ formatDate(file.created_at) }}
                    </div>
                </div>

                <div class="flex items-center gap-1.5">
                    <button
                        type="button"
                        class="inline-flex h-7 items-center gap-1 rounded border border-gray-200 bg-white px-2 text-[11px] text-gray-700 hover:bg-gray-50"
                        @click="handleDownload(file)"
                    >
                        <Icon name="lucide:download" class="size-3" />
                        <span>Baixar</span>
                    </button>
                    <button
                        v-if="auth.can('files.upload')"
                        type="button"
                        class="inline-flex h-7 items-center gap-1 rounded border border-rose-200 bg-rose-50 px-2 text-[11px] text-rose-700 hover:bg-rose-100"
                        @click="deleteTarget = file"
                    >
                        <Icon name="lucide:trash-2" class="size-3" />
                    </button>
                </div>
            </div>
        </div>

        <UiModal
            :open="uploadModalOpen"
            title="Anexar documento do paciente"
            description="Envie exames, receitas, digitalizações ou laudos (máx. 20 MB)."
            max-width="lg"
            @close="uploadModalOpen = false"
        >
            <form class="space-y-3 text-[12px]" @submit.prevent="handleUpload">
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Tipo / Categoria</label>
                    <select v-model="uploadForm.collection" class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px]">
                        <option value="exams">Exames</option>
                        <option value="prescriptions">Receitas / Prescrições</option>
                        <option value="scans">Digitalizações</option>
                        <option value="reports">Laudos / Atestados</option>
                        <option value="other">Outros</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Visibilidade</label>
                    <select v-model="uploadForm.visibility" class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px]">
                        <option value="SHARED_TEAM">SHARED_TEAM (Equipe clínica)</option>
                        <option value="PUBLIC_CLINIC">PUBLIC_CLINIC (Toda a clínica)</option>
                        <option value="PRIVATE">PRIVATE (Restrito)</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Arquivo</label>
                    <input
                        id="input-patient-file"
                        type="file"
                        required
                        class="block w-full rounded border border-gray-200 bg-white p-1.5 text-[11px] text-gray-700 file:mr-2 file:rounded file:border-0 file:bg-gray-100 file:px-2.5 file:py-1 file:text-[11px] file:text-gray-700"
                        @change="onFileChange"
                    />
                    <p v-if="selectedFile" class="mt-1 text-[10px] text-gray-500">
                        Selecionado: {{ selectedFile.name }} ({{ formatSize(selectedFile.size) }})
                    </p>
                </div>

                <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                    <button type="button" class="h-7 rounded border border-gray-200 px-2.5 text-[11px] text-gray-600" @click="uploadModalOpen = false">
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        :disabled="uploading"
                        class="inline-flex h-7 items-center gap-1.5 rounded bg-teal-600 px-3 text-[11px] text-white hover:bg-teal-700 disabled:opacity-50"
                    >
                        <Icon v-if="uploading" name="lucide:loader-2" class="size-3 animate-spin" />
                        <span>Enviar arquivo</span>
                    </button>
                </div>
            </form>
        </UiModal>

        <UiConfirmModal
            :open="Boolean(deleteTarget)"
            title="Remover documento?"
            :message="`Deseja remover '${deleteTarget?.original_filename}' do prontuário do paciente?`"
            confirm-label="Remover"
            @close="deleteTarget = null"
            @confirm="confirmDelete"
        />
    </div>
</template>
