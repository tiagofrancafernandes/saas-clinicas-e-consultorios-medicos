<script setup lang="ts">
interface Props {
    tone?: 'info' | 'success' | 'warning' | 'danger' | 'neutral';
}

const props = withDefaults(defineProps<Props>(), {
    tone: 'neutral',
});

const toneClass = computed(() => {
    switch (props.tone) {
        case 'info':
            return 'bg-sky-50 text-sky-700 border-sky-200';
        case 'success':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'warning':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'danger':
            return 'bg-rose-50 text-rose-700 border-rose-200';
        default:
            return 'bg-slate-100 text-slate-600 border-slate-200';
    }
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-medium whitespace-nowrap"
        :class="toneClass"
    >
        <slot />
    </span>
</template>
