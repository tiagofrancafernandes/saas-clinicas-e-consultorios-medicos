<script setup lang="ts">
interface Props {
    modelValue: boolean;
    title: string;
    maxWidthClass?: string;
}

withDefaults(defineProps<Props>(), {
    maxWidthClass: 'max-w-md',
});

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
}>();

function close(): void {
    emit('update:modelValue', false);
}
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
                v-if="modelValue"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-[1px] p-4"
                @click.self="close"
            >
                <div
                    class="w-full bg-white border border-slate-200 rounded-xl shadow-xl overflow-hidden"
                    :class="maxWidthClass"
                >
                    <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100">
                        <h3 class="text-sm font-semibold text-slate-900">
                            {{ title }}
                        </h3>
                        <button
                            type="button"
                            class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 cursor-pointer"
                            @click="close"
                        >
                            <Icon name="heroicons:x-mark" class="w-4 h-4" />
                        </button>
                    </div>
                    <div class="p-5">
                        <slot />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
