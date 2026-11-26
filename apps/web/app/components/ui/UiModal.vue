<script setup lang="ts">
interface Props {
    open: boolean;
    title: string;
    description?: string;
    maxWidth?: 'sm' | 'md' | 'lg';
}

withDefaults(defineProps<Props>(), {
    maxWidth: 'md',
});

const emit = defineEmits<{ (e: 'close'): void }>();

const widths = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
};
</script>

<template>
    <ClientOnly>
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
                v-if="open"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4"
                @click.self="emit('close')"
            >
                <div
                    class="flex max-h-[90vh] w-full flex-col overflow-hidden rounded-md border border-gray-200 bg-white shadow-xl"
                    :class="widths[maxWidth]"
                >
                    <div class="flex items-start justify-between border-b border-gray-100 px-4 py-3">
                        <div>
                            <h3 class="text-[13px] font-medium text-gray-900">{{ title }}</h3>
                            <p v-if="description" class="mt-0.5 text-[11px] text-gray-400">{{ description }}</p>
                        </div>
                        <button
                            type="button"
                            class="flex size-6 items-center justify-center rounded text-gray-400 hover:bg-gray-100 hover:text-gray-700"
                            @click="emit('close')"
                        >
                            <Icon name="lucide:x" class="size-3.5" />
                        </button>
                    </div>

                    <div class="overflow-y-auto p-4">
                        <slot />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
    </ClientOnly>
</template>
