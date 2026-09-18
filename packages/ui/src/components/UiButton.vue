<script setup lang="ts">
import { Icon } from '@iconify/vue';

interface Props {
    variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
    size?: 'sm' | 'md';
    icon?: string;
    loading?: boolean;
    disabled?: boolean;
    type?: 'button' | 'submit' | 'reset';
}

withDefaults(defineProps<Props>(), {
    variant: 'primary',
    size: 'sm',
    type: 'button',
    loading: false,
    disabled: false,
});
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        :class="[
            'inline-flex items-center justify-center gap-1.5 font-medium rounded-md transition-colors focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed',
            // Size variants (compact, small buttons as specified)
            size === 'sm' ? 'px-2.5 py-1 text-xs' : 'px-3.5 py-1.5 text-sm',
            // Variant colors with subtle borders and fine focus states (no heavy rings)
            variant === 'primary' &&
                'bg-blue-600 text-white hover:bg-blue-700 border border-blue-600 focus:border-blue-700',
            variant === 'secondary' &&
                'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200 focus:border-gray-300 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700',
            variant === 'ghost' &&
                'bg-transparent text-gray-700 hover:bg-gray-100 border border-transparent dark:text-gray-200 dark:hover:bg-gray-800',
            variant === 'danger' &&
                'bg-red-600 text-white hover:bg-red-700 border border-red-600 focus:border-red-700',
        ]"
    >
        <Icon v-if="loading" icon="lucide:loader-2" class="animate-spin text-sm" />
        <Icon v-else-if="icon" :icon="icon" class="text-sm" />
        <slot />
    </button>
</template>
