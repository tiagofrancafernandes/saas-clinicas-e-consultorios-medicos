<script setup lang="ts">
interface SelectOption {
    value: string;
    label: string;
}

interface Props {
    modelValue: string | null | undefined;
    options: SelectOption[];
    id?: string;
    name?: string;
    label?: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    id: undefined,
    name: undefined,
    label: undefined,
    placeholder: undefined,
    required: false,
    disabled: false,
});

const generatedId = useId();
const selectId = computed(() => props.id ?? generatedId);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

function onChange(event: Event): void {
    const target = event.target as HTMLSelectElement;
    emit('update:modelValue', target.value);
}
</script>

<template>
    <div class="w-full">
        <label v-if="props.label" :for="selectId" class="mb-1 block text-[12px] font-normal text-gray-700">
            {{ props.label }}
            <span v-if="props.required" class="text-red-500">*</span>
        </label>
        <select
            :id="selectId"
            :name="props.name ?? selectId"
            :value="props.modelValue ?? ''"
            :required="props.required"
            :disabled="props.disabled"
            class="h-8 w-full rounded-md border border-gray-200 bg-white px-2.5 text-[12px] font-light text-gray-900 transition-colors outline-none hover:border-gray-300 focus:border-gray-400 focus:outline-none focus:ring-0 disabled:cursor-not-allowed disabled:bg-gray-50"
            @change="onChange"
        >
            <option v-if="props.placeholder" value="" disabled>{{ props.placeholder }}</option>
            <option v-for="option in props.options" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>
    </div>
</template>
