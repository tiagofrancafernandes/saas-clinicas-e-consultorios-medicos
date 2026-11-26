<script setup lang="ts">
const { state, handleConfirm, handleCancel } = useConfirmModal();
</script>

<template>
    <UiModal :open="state.open" :title="state.title" max-width="max-w-[420px]" @close="handleCancel">
        <div class="flex items-start gap-3">
            <div
                :class="[
                    'flex size-8 shrink-0 items-center justify-center rounded-md',
                    state.variant === 'danger' ? 'bg-red-50 text-red-600' : 'bg-blue-50 text-blue-600',
                ]"
            >
                <Icon
                    :name="state.variant === 'danger' ? 'lucide:alert-triangle' : 'lucide:help-circle'"
                    class="size-4"
                />
            </div>
            <p class="text-[12px] font-light leading-relaxed text-gray-600">
                {{ state.message }}
            </p>
        </div>

        <template #footer>
            <UiButton variant="outline" size="xs" @click="handleCancel">
                {{ state.cancelLabel }}
            </UiButton>
            <UiButton :variant="state.variant === 'danger' ? 'danger' : 'primary'" size="xs" @click="handleConfirm">
                {{ state.confirmLabel }}
            </UiButton>
        </template>
    </UiModal>
</template>
