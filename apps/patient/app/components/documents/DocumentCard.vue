<script setup lang="ts">
import { DOCUMENT_COLLECTION_LABELS } from '~/constants/enums';
import type { PatientDocument } from '~/types/document';
import { formatDateTimeInTz, formatFileSize } from '~/utils/formatters';

interface Props {
    document: PatientDocument;
    timezone: string;
    downloading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    downloading: false,
});

defineEmits<{
    download: [doc: PatientDocument];
    preview: [doc: PatientDocument];
}>();

const canPreview = computed(() => {
    const mime = props.document.mime_type || '';
    return mime.startsWith('image/') || mime === 'application/pdf';
});

const fileIcon = computed(() => {
    const mime = props.document.mime_type || '';
    if (mime.startsWith('image/')) {
        return 'heroicons:photo';
    }
    if (mime === 'application/pdf') {
        return 'heroicons:document-text';
    }
    return 'heroicons:paper-clip';
});

const collectionLabel = computed(() => DOCUMENT_COLLECTION_LABELS[props.document.collection] || 'Documento');
</script>

<template>
    <UiCard class="transition-colors hover:border-slate-300">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                    <Icon :name="fileIcon" class="w-5 h-5" />
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-medium text-slate-900 truncate">
                            {{ document.original_filename }}
                        </p>
                        <UiBadge tone="neutral">
                            {{ collectionLabel }}
                        </UiBadge>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ formatFileSize(document.size_bytes) }}
                        <template v-if="document.created_at">
                            · Enviado em {{ formatDateTimeInTz(document.created_at, timezone) }}
                        </template>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <UiButton
                    v-if="canPreview"
                    variant="secondary"
                    size="sm"
                    icon="heroicons:eye"
                    :loading="downloading"
                    @click="$emit('preview', document)"
                >
                    Visualizar
                </UiButton>
                <UiButton
                    variant="primary"
                    size="sm"
                    icon="heroicons:arrow-down-tray"
                    :loading="downloading"
                    @click="$emit('download', document)"
                >
                    Baixar
                </UiButton>
            </div>
        </div>
    </UiCard>
</template>
