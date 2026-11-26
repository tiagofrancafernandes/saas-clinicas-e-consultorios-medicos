<script setup lang="ts">
interface Props {
    variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger';
    size?: 'xs' | 'sm';
    type?: 'button' | 'submit' | 'reset';
    icon?: string;
    loading?: boolean;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'outline',
    size: 'sm',
    type: 'button',
    icon: undefined,
    loading: false,
    disabled: false,
});

const variantClasses: Record<NonNullable<Props['variant']>, string> = {
    primary: 'bg-blue-600 text-white border border-blue-600 hover:bg-blue-700 hover:border-blue-700',
    secondary: 'bg-gray-900 text-white border border-gray-900 hover:bg-gray-800',
    outline: 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50',
    ghost: 'bg-transparent text-gray-600 border border-transparent hover:bg-gray-100 hover:text-gray-900',
    danger: 'bg-red-600 text-white border border-red-600 hover:bg-red-700',
};

const sizeClasses: Record<NonNullable<Props['size']>, string> = {
    xs: 'h-7 px-2.5 text-[11px] rounded',
    sm: 'h-8 px-3 text-[12px] rounded-md',
};
</script>

<template>
    <button
        :type="props.type"
        :disabled="props.disabled || props.loading"
        :class="[
            'inline-flex cursor-pointer items-center justify-center gap-1.5 font-normal transition-colors select-none outline-none focus:outline-none focus:ring-0 active:outline-none disabled:cursor-not-allowed disabled:opacity-50',
            variantClasses[props.variant],
            sizeClasses[props.size],
        ]"
    >
        <Icon v-if="props.loading" name="lucide:loader-2" class="size-3.5 animate-spin" />
        <Icon v-else-if="props.icon" :name="props.icon" class="size-3.5 shrink-0" />
        <slot />
    </button>
</template>
