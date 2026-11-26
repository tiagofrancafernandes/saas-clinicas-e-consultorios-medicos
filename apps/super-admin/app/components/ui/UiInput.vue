<script setup lang="ts">
interface Props {
    modelValue: string | number | null | undefined;
    id?: string;
    name?: string;
    autocomplete?: string;
    label?: string;
    type?: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    readonly?: boolean;
    error?: string;
    helper?: string;
}

const props = withDefaults(defineProps<Props>(), {
    id: undefined,
    name: undefined,
    autocomplete: undefined,
    label: undefined,
    type: 'text',
    placeholder: '',
    required: false,
    disabled: false,
    readonly: false,
    error: undefined,
    helper: undefined,
});

const generatedId = useId();
const inputId = computed(() => props.id ?? generatedId);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

function onInput(event: Event): void {
    const target = event.target as HTMLInputElement;
    emit('update:modelValue', target.value);
}
</script>

<template>
    <div class="w-full">
        <label v-if="props.label" :for="inputId" class="mb-1 block text-[12px] font-normal text-gray-700">
            {{ props.label }}
            <span v-if="props.required" class="text-red-500">*</span>
        </label>
        <input
            :id="inputId"
            :name="props.name ?? inputId"
            :autocomplete="props.autocomplete"
            :value="props.modelValue ?? ''"
            :type="props.type"
            :placeholder="props.placeholder"
            :required="props.required"
            :disabled="props.disabled"
            :readonly="props.readonly"
            :class="[
                'h-8 w-full rounded-md border bg-white px-2.5 text-[12px] font-light text-gray-900 placeholder:text-gray-400 transition-colors outline-none focus:outline-none focus:ring-0 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500',
                props.error ? 'border-red-300' : 'border-gray-200 hover:border-gray-300 focus:border-gray-400',
            ]"
            @input="onInput"
        />
        <p v-if="props.error" class="mt-1 text-[11px] text-red-600">{{ props.error }}</p>
        <p v-else-if="props.helper" class="mt-1 text-[11px] text-gray-400">{{ props.helper }}</p>
    </div>
</template>
