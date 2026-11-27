<script setup lang="ts">
interface Props {
    variant?: 'primary' | 'secondary' | 'danger' | 'ghost';
    size?: 'sm' | 'md';
    type?: 'button' | 'submit' | 'reset';
    loading?: boolean;
    disabled?: boolean;
    icon?: string;
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'primary',
    size: 'md',
    type: 'button',
    loading: false,
    disabled: false,
    icon: undefined,
});

const variantClasses = computed(() => {
    switch (props.variant) {
        case 'primary':
            return 'bg-sky-600 text-white border border-sky-600 hover:bg-sky-700 disabled:bg-sky-300 disabled:border-sky-300';
        case 'secondary':
            return 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 disabled:text-slate-400';
        case 'danger':
            return 'bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 disabled:opacity-50';
        case 'ghost':
            return 'bg-transparent text-slate-600 border border-transparent hover:bg-slate-100 disabled:opacity-50';
    }
});

const sizeClasses = computed(() => {
    return props.size === 'sm' ? 'h-8 px-3 text-xs gap-1.5' : 'h-9 px-4 text-sm gap-2';
});
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        class="inline-flex items-center justify-center rounded-lg font-medium transition-colors cursor-pointer disabled:cursor-not-allowed whitespace-nowrap"
        :class="[variantClasses, sizeClasses]"
    >
        <Icon v-if="loading" name="heroicons:arrow-path" class="w-4 h-4 animate-spin shrink-0" />
        <Icon v-else-if="icon" :name="icon" class="w-4 h-4 shrink-0" />
        <slot />
    </button>
</template>
