<script setup lang="ts">
interface Props {
    open: boolean;
    title: string;
    subtitle?: string;
    maxWidth?: string;
}

const props = withDefaults(defineProps<Props>(), {
    subtitle: undefined,
    maxWidth: 'max-w-[480px]',
});

const emit = defineEmits<{
    close: [];
}>();

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && props.open) {
        emit('close');
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="props.open"
                class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/40 p-4"
                @click.self="emit('close')"
            >
                <div :class="['w-full rounded-md border border-gray-200 bg-white shadow-lg', props.maxWidth]">
                    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                        <div>
                            <h3 class="text-[14px] font-medium text-gray-900">{{ props.title }}</h3>
                            <p v-if="props.subtitle" class="mt-0.5 text-[11px] font-light text-gray-400">
                                {{ props.subtitle }}
                            </p>
                        </div>
                        <button
                            type="button"
                            aria-label="Fechar modal"
                            class="flex size-7 cursor-pointer items-center justify-center rounded text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700"
                            @click="emit('close')"
                        >
                            <Icon name="lucide:x" class="size-4" />
                        </button>
                    </div>
                    <div class="p-4">
                        <slot />
                    </div>
                    <div
                        v-if="$slots.footer"
                        class="flex items-center justify-end gap-2 border-t border-gray-100 px-4 py-3"
                    >
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
