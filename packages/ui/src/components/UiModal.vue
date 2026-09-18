<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';
import { Icon } from '@iconify/vue';

interface Props {
    isOpen: boolean;
    title?: string;
    description?: string;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl';
}

const props = withDefaults(defineProps<Props>(), {
    isOpen: false,
    maxWidth: 'md',
});

const emit = defineEmits<{
    (e: 'close'): void;
}>();

function handleKeydown(e: KeyboardEvent): void {
    if (e.key === 'Escape' && props.isOpen) {
        emit('close');
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});

const widthClasses = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-[1px]"
                @click.self="emit('close')"
            >
                <div
                    class="w-full bg-white rounded-lg border border-[#dededb] shadow-[0_10px_25px_rgba(0,0,0,0.1)] overflow-hidden flex flex-col max-h-[90vh]"
                    :class="widthClasses[maxWidth]"
                >
                    <div class="px-5 py-3.5 border-b border-[#dededb] flex items-center justify-between">
                        <div>
                            <h3 v-if="title" class="text-sm font-semibold text-[#1d1d1a]">
                                {{ title }}
                            </h3>
                            <p v-if="description" class="text-xs text-[#73736c] mt-0.5">
                                {{ description }}
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="emit('close')"
                            class="p-1 rounded text-[#73736c] hover:text-[#1d1d1a] hover:bg-[#f3f3f0] transition-colors"
                        >
                            <Icon icon="lucide:x" class="w-4 h-4" />
                            <span class="sr-only">Close modal</span>
                        </button>
                    </div>

                    <div class="p-5 overflow-y-auto">
                        <slot />
                    </div>

                    <div
                        v-if="$slots.footer"
                        class="px-5 py-3 bg-[#fbfbfa] border-t border-[#dededb] flex items-center justify-end gap-2"
                    >
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
