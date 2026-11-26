<script setup lang="ts">
interface DropdownOption {
    value: string;
    label: string;
}

interface Props {
    modelValue: string;
    options: DropdownOption[];
    placeholder?: string;
    widthClass?: string;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Selecione...',
    widthClass: 'w-full',
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const open = ref(false);
const containerRef = ref<HTMLElement | null>(null);

const selectedLabel = computed(
    () => props.options.find((item) => String(item.value) === String(props.modelValue))?.label ?? ''
);

function selectOption(value: string): void {
    emit('update:modelValue', value);
    open.value = false;
}

function onClickOutside(event: MouseEvent): void {
    if (containerRef.value && !containerRef.value.contains(event.target as Node)) {
        open.value = false;
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        open.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', onClickOutside);
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onClickOutside);
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="containerRef" :class="['relative', props.widthClass]">
        <button
            type="button"
            :class="[
                'flex h-8 w-full cursor-pointer items-center justify-between gap-2 rounded-md border bg-white px-2.5 text-[12px] font-light transition-colors outline-none',
                open ? 'border-gray-400' : 'border-gray-200 hover:border-gray-300',
            ]"
            @click="open = !open"
        >
            <span :class="['truncate', selectedLabel ? 'text-gray-800' : 'text-gray-400']">
                {{ selectedLabel || props.placeholder }}
            </span>
            <Icon
                name="lucide:chevron-down"
                :class="['size-3.5 shrink-0 text-gray-400 transition-transform duration-150', open ? 'rotate-180' : '']"
            />
        </button>

        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="opacity-0 scale-95 -translate-y-1"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 -translate-y-1"
        >
            <div
                v-show="open"
                class="absolute left-0 top-full z-50 mt-1 max-h-56 w-full min-w-[170px] origin-top-left overflow-y-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg"
            >
                <button
                    v-for="option in props.options"
                    :key="option.value"
                    type="button"
                    :class="[
                        'flex h-8 w-full cursor-pointer items-center justify-between px-3 text-left text-[12px] transition-colors hover:bg-gray-50',
                        String(props.modelValue) === String(option.value)
                            ? 'font-normal text-blue-700'
                            : 'font-light text-gray-700',
                    ]"
                    @click="selectOption(option.value)"
                >
                    <span class="truncate">{{ option.label }}</span>
                    <Icon
                        v-if="String(props.modelValue) === String(option.value)"
                        name="lucide:check"
                        class="size-3.5 shrink-0 text-blue-600"
                    />
                </button>
            </div>
        </Transition>
    </div>
</template>
