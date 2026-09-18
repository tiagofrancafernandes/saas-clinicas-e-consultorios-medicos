<script setup lang="ts">
import { Icon } from '@iconify/vue';

interface Props {
    variant?: 'neutral' | 'success' | 'warning' | 'danger' | 'info';
    icon?: string;
}

withDefaults(defineProps<Props>(), {
    variant: 'neutral',
});
</script>

<template>
    <span
        :class="[
            'inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-normal tracking-wide border',
            variant === 'neutral' &&
                'bg-gray-50 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700',
            variant === 'success' &&
                'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800',
            variant === 'warning' &&
                'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:border-amber-800',
            variant === 'danger' &&
                'bg-red-50 text-red-700 border-red-200 dark:bg-red-950 dark:text-red-300 dark:border-red-800',
            variant === 'info' &&
                'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950 dark:text-sky-300 dark:border-sky-800',
        ]"
    >
        <Icon v-if="icon" :icon="icon" class="text-xs" />
        <slot />
    </span>
</template>
