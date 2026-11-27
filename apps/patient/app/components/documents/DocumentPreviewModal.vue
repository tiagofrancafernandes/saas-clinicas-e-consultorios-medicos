<script setup lang="ts">
import type { PatientDocument } from '~/types/document';

interface Props {
    modelValue: boolean;
    document: PatientDocument | null;
    previewUrl: string | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
}>();

const isImage = computed(() => (props.document?.mime_type || '').startsWith('image/'));

function handleClose(open: boolean): void {
    emit('update:modelValue', open);
}
</script>

<template>
    <UiModal
        :model-value="modelValue"
        :title="document?.original_filename || 'Visualização de documento'"
        max-width-class="max-w-3xl"
        @update:model-value="handleClose"
    >
        <div v-if="previewUrl" class="space-y-3">
            <img
                v-if="isImage"
                :src="previewUrl"
                :alt="document?.original_filename"
                class="max-h-[70vh] w-auto mx-auto rounded-lg border border-slate-200"
            />
            <iframe
                v-else
                :src="previewUrl"
                class="w-full h-[70vh] rounded-lg border border-slate-200"
                title="Visualizador de PDF"
            />
        </div>
    </UiModal>
</template>
