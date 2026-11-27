<script setup lang="ts">
import { DOCUMENT_COLLECTION_LABELS } from '~/constants/enums';
import { useDocumentsStore } from '~/stores/useDocumentsStore';
import { usePortalSummaryStore } from '~/stores/usePortalSummaryStore';
import type { PatientDocument } from '~/types/document';

const documentsStore = useDocumentsStore();
const summaryStore = usePortalSummaryStore();

const previewModalOpen = ref(false);
const previewDoc = ref<PatientDocument | null>(null);
const previewUrl = ref<string | null>(null);

const clinicTimezone = computed(() => summaryStore.summary?.clinic?.timezone || 'America/Sao_Paulo');

const collectionFilters = computed(() => [
    { key: 'all', label: 'Todos' },
    ...Object.entries(DOCUMENT_COLLECTION_LABELS).map(([key, label]) => ({ key, label })),
]);

onMounted(() => {
    summaryStore.fetchSummary();
    documentsStore.fetchDocuments();
});

watch(previewModalOpen, (open) => {
    if (!open && previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
        previewDoc.value = null;
    }
});

async function handlePreview(doc: PatientDocument): Promise<void> {
    const url = await documentsStore.fetchDocumentUrl(doc);
    if (!url) {
        return;
    }
    previewDoc.value = doc;
    previewUrl.value = url;
    previewModalOpen.value = true;
}
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">Meus Documentos</h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Visualize e baixe exames, receitas, atestados e arquivos compartilhados pela clínica.
                </p>
            </div>

            <UiButton
                variant="secondary"
                size="sm"
                icon="heroicons:arrow-path"
                :loading="documentsStore.loading"
                @click="documentsStore.fetchDocuments(true)"
            >
                Atualizar
            </UiButton>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 sm:items-center justify-between">
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="filter in collectionFilters"
                    :key="filter.key"
                    type="button"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors cursor-pointer"
                    :class="{
                        'bg-sky-50 text-sky-700 border-sky-200': documentsStore.selectedCollection === filter.key,
                        'bg-white text-slate-600 border-slate-200 hover:bg-slate-50':
                            documentsStore.selectedCollection !== filter.key,
                    }"
                    @click="documentsStore.selectedCollection = filter.key"
                >
                    {{ filter.label }}
                </button>
            </div>

            <div class="w-full sm:w-64">
                <UiInput
                    v-model="documentsStore.searchQuery"
                    placeholder="Buscar documento..."
                    icon="heroicons:magnifying-glass"
                />
            </div>
        </div>

        <div v-if="documentsStore.loading" class="space-y-3">
            <DocumentCardSkeleton v-for="i in 3" :key="i" />
        </div>

        <div v-else-if="documentsStore.filteredDocuments.length > 0" class="space-y-3">
            <DocumentCard
                v-for="doc in documentsStore.filteredDocuments"
                :key="doc.id"
                :document="doc"
                :timezone="clinicTimezone"
                :downloading="Boolean(documentsStore.downloadingIds[doc.id])"
                @download="documentsStore.downloadDocument"
                @preview="handlePreview"
            />
        </div>

        <UiEmptyState
            v-else
            icon="heroicons:document-text"
            title="Nenhum documento encontrado"
            description="Quando a clínica anexar exames, receitas ou atestados ao seu prontuário, eles ficarão disponíveis aqui."
        />

        <DocumentPreviewModal v-model="previewModalOpen" :document="previewDoc" :preview-url="previewUrl" />
    </div>
</template>
