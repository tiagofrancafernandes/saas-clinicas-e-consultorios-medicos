import { defineStore } from 'pinia';
import { usePortalService } from '~/services/portalService';
import type { PatientDocument } from '~/types/document';

export const useDocumentsStore = defineStore('patientDocuments', () => {
    const portalService = usePortalService();
    const notify = useNotify();

    const documents = ref<PatientDocument[]>([]);
    const loaded = ref(false);
    const loading = ref(false);
    const downloadingIds = ref<Record<string, boolean>>({});
    const searchQuery = ref('');
    const selectedCollection = ref<string>('all');

    const filteredDocuments = computed(() => {
        const q = searchQuery.value.trim().toLowerCase();

        return documents.value.filter((doc) => {
            const matchesCollection = selectedCollection.value === 'all' || doc.collection === selectedCollection.value;
            const matchesQuery = !q || doc.original_filename.toLowerCase().includes(q);
            return matchesCollection && matchesQuery;
        });
    });

    async function fetchDocuments(force = false): Promise<void> {
        if (loaded.value && !force) {
            return;
        }
        loading.value = true;
        try {
            const response = await portalService.getDocuments();
            documents.value = response.data;
            loaded.value = true;
        } catch {
            notify.error('Não foi possível carregar seus documentos.');
        } finally {
            loading.value = false;
        }
    }

    async function fetchDocumentUrl(doc: PatientDocument): Promise<string | null> {
        downloadingIds.value = { ...downloadingIds.value, [doc.id]: true };
        try {
            const blob = await portalService.downloadDocumentBlob(doc.id);
            return URL.createObjectURL(blob);
        } catch {
            notify.error('Não foi possível carregar o arquivo.');
            return null;
        } finally {
            const { [doc.id]: _, ...rest } = downloadingIds.value;
            downloadingIds.value = rest;
        }
    }

    async function downloadDocument(doc: PatientDocument): Promise<void> {
        const url = await fetchDocumentUrl(doc);
        if (!url) {
            return;
        }

        const link = document.createElement('a');
        link.href = url;
        link.download = doc.original_filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    }

    return {
        documents,
        filteredDocuments,
        loading,
        downloadingIds,
        searchQuery,
        selectedCollection,
        fetchDocuments,
        fetchDocumentUrl,
        downloadDocument,
    };
});
