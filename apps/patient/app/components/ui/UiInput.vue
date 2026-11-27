<script setup lang="ts">
interface Props {
    modelValue: string;
    label?: string;
    type?: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    autocomplete?: string;
    icon?: string;
}

withDefaults(defineProps<Props>(), {
    label: undefined,
    type: 'text',
    placeholder: '',
    required: false,
    disabled: false,
    autocomplete: 'off',
    icon: undefined,
});

defineEmits<{
    'update:modelValue': [value: string];
}>();

const inputId = useId();
</script>

<template>
    <div class="space-y-1.5">
        <label v-if="label" :for="inputId" class="block text-xs font-medium text-slate-700">
            {{ label }}
        </label>
        <div class="relative">
            <Icon
                v-if="icon"
                :name="icon"
                class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"
            />
            <input
                :id="inputId"
                :type="type"
                :value="modelValue"
                :placeholder="placeholder"
                :required="required"
                :disabled="disabled"
                :autocomplete="autocomplete"
                class="w-full h-9 rounded-lg border border-slate-200 bg-white text-sm text-slate-900 placeholder:text-slate-400 transition-colors focus:border-sky-500 disabled:bg-slate-50 disabled:text-slate-500"
                :class="icon ? 'pl-9 pr-3' : 'px-3'"
                @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
            />
        </div>
    </div>
</template>
