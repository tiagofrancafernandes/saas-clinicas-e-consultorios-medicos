<script setup lang="ts">
interface Option {
    label: string;
    value: string | number;
}

interface Props {
    modelValue?: string | number;
    options: Option[];
    label?: string;
    placeholder?: string;
    error?: string;
    disabled?: boolean;
    required?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
    required: false,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

function onChange(event: Event): void {
    const target = event.target as HTMLSelectElement;
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
            <select
                :value="modelValue"
                :disabled="disabled"
                :required="required"
                @change="onChange"
                class="w-full h-8 px-2.5 text-xs bg-white text-[#1d1d1a] border rounded transition-colors focus:outline-none focus:border-[#454540] disabled:bg-[#f3f3f0] disabled:cursor-not-allowed appearance-none cursor-pointer"
                :class="[
                    error ? 'border-rose-400 focus:border-rose-500' : 'border-[#dededb]',
                ]"
            >
                <option v-if="placeholder" value="" disabled selected>
                    {{ placeholder }}
                </option>
                <option
                    v-for="opt in options"
                    :key="String(opt.value)"
                    :value="opt.value"
                >
                    {{ opt.label }}
                </option>
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-[#73736c]">
                <svg class="h-3 w-3 fill-current" viewBox="0 0 20 20">
                    <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                </svg>
            </div>
        </div>

        <p v-if="error" class="text-[11px] text-rose-500 font-normal mt-1">
            {{ error }}
        </p>
    </div>
</template>
