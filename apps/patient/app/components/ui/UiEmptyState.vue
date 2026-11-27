<script setup lang="ts">
interface Props {
    icon: string;
    title: string;
    description: string;
}

defineProps<Props>();
</script>

<template>
    <UiCard class="text-center py-10">
        <div class="mx-auto w-11 h-11 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mb-3">
            <Icon :name="icon" class="w-5 h-5" />
        </div>
        <h3 class="text-sm font-medium text-slate-900">
            {{ title }}
        </h3>
        <p class="mt-1 text-xs text-slate-500 max-w-sm mx-auto">
            {{ description }}
        </p>
        <div v-if="$slots.action" class="mt-4">
            <slot name="action" />
        </div>
    </UiCard>
</template>
