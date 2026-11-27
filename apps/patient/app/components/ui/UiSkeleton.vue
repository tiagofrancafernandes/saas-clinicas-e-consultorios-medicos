<script setup lang="ts">
interface Props {
    widthClass?: string;
    heightClass?: string;
    roundedClass?: string;
}

withDefaults(defineProps<Props>(), {
    widthClass: 'w-full',
    heightClass: 'h-4',
    roundedClass: 'rounded-md',
});
</script>

<template>
    <div class="animate-pulse bg-slate-200/80" :class="[widthClass, heightClass, roundedClass]" />
</template>
