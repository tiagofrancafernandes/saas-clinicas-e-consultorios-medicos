<script setup lang="ts">
interface Props {
    padding?: boolean;
}

withDefaults(defineProps<Props>(), {
    padding: true,
});
</script>

<template>
    <div
        class="bg-white border border-slate-200 rounded-xl shadow-[0_1px_2px_rgba(15,23,42,0.03)]"
        :class="padding ? 'p-4 sm:p-5' : ''"
    >
        <slot />
    </div>
</template>
