<script setup lang="ts">
interface Props {
    modelValue?: string | number;
    type?: string;
    label?: string;
    placeholder?: string;
    error?: string;
    disabled?: boolean;
    required?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    type: 'text',
    disabled: false,
    required: false,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

function onInput(event: Event): void {
    const target = event.target as HTMLInputElement;
    emit('update:modelValue', target.value);
}
</script>

<template>
    <div class="space-y-1.5 w-full">
        <label v-if="label" class="block text-xs font-medium text-[#454540]">
            {{ label }}
            <span v-if="required" class="text-rose-500">*</span>
        </label>

        <div class="relative">
            <input
                :type="type"
                :value="modelValue"
                :placeholder="placeholder"
                :disabled="disabled"
                :required="required"
                @input="onInput"
                class="w-full h-8 px-2.5 text-xs bg-white text-[#1d1d1a] border rounded transition-colors placeholder:text-[#a0a099] focus:outline-none focus:border-[#454540] disabled:bg-[#f3f3f0] disabled:cursor-not-allowed"
                :class="[error ? 'border-rose-400 focus:border-rose-500' : 'border-[#dededb]']"
            />
        </div>

        <p v-if="error" class="text-[11px] text-rose-500 font-normal mt-1">
            {{ error }}
        </p>
    </div>
</template>
