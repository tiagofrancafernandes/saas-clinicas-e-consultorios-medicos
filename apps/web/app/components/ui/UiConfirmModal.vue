<script setup lang="ts">
interface Props {
    open: boolean;
    title: string;
    message: string;
    confirmLabel?: string;
    danger?: boolean;
}

withDefaults(defineProps<Props>(), {
    confirmLabel: 'Confirmar',
    danger: true,
});

const emit = defineEmits<{ (e: 'close'): void; (e: 'confirm'): void }>();
</script>

<template>
    <UiModal :open="open" :title="title" max-width="sm" @close="emit('close')">
        <div class="space-y-4">
            <div class="flex items-start gap-3">
                <div
                    class="flex size-8 shrink-0 items-center justify-center rounded"
                    :class="danger ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-600'"
                >
                    <Icon name="lucide:alert-triangle" class="size-4" />
                </div>
                <p class="text-[12px] leading-relaxed text-gray-600">{{ message }}</p>
            </div>

            <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                <button
                    type="button"
                    class="h-7 rounded border border-gray-200 bg-white px-2.5 text-[11px] text-gray-600 hover:bg-gray-50"
                    @click="emit('close')"
                >
                    Cancelar
                </button>
                <button
                    id="btn-confirm-modal-action"
                    type="button"
                    class="h-7 rounded px-2.5 text-[11px] font-normal text-white transition-colors"
                    :class="danger ? 'bg-rose-600 hover:bg-rose-700' : 'bg-blue-600 hover:bg-blue-700'"
                    @click="emit('confirm')"
                >
                    {{ confirmLabel }}
                </button>
            </div>
        </div>
    </UiModal>
</template>
