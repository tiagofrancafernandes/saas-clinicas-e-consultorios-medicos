<script setup lang="ts">
interface Props {
    title?: string;
    description?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="bg-white rounded-lg border border-[#dededb] shadow-[0_1px_2px_rgba(0,0,0,0.04)] overflow-hidden transition-all">
        <div v-if="title || $slots.header" class="px-5 py-4 border-b border-[#dededb] flex items-center justify-between">
            <div>
                <h3 v-if="title" class="text-sm font-semibold text-[#1d1d1a] tracking-tight">
                    {{ title }}
                </h3>
                <p v-if="description" class="text-xs text-[#73736c] mt-0.5">
                    {{ description }}
                </p>
            </div>
            <div v-if="$slots.headerAction">
                <slot name="headerAction" />
            </div>
        </div>

        <div class="p-5">
            <slot />
        </div>

        <div v-if="$slots.footer" class="px-5 py-3 bg-[#fbfbfa] border-t border-[#dededb] flex items-center justify-between text-xs text-[#73736c]">
            <slot name="footer" />
        </div>
    </div>
</template>
