<script setup lang="ts">
interface Props {
    color?: 'green' | 'yellow' | 'red' | 'blue' | 'gray';
    dot?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    color: 'gray',
    dot: true,
});

const colorClasses: Record<NonNullable<Props['color']>, { wrapper: string; dot: string }> = {
    green: { wrapper: 'bg-green-50 text-green-700', dot: 'bg-green-500' },
    yellow: { wrapper: 'bg-yellow-50 text-yellow-700', dot: 'bg-yellow-500' },
    red: { wrapper: 'bg-red-50 text-red-700', dot: 'bg-red-500' },
    blue: { wrapper: 'bg-blue-50 text-blue-700', dot: 'bg-blue-500' },
    gray: { wrapper: 'bg-gray-100 text-gray-600', dot: 'bg-gray-400' },
};
</script>

<template>
    <span
        :class="[
            'inline-flex items-center gap-1.5 rounded px-2 py-0.5 text-[11px] font-normal',
            colorClasses[props.color].wrapper,
        ]"
    >
        <span v-if="props.dot" :class="['size-1.5 rounded-full', colorClasses[props.color].dot]" />
        <slot />
    </span>
</template>
