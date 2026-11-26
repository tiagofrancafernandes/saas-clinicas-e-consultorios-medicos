<script setup lang="ts">
interface Props {
    padding?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    padding: true,
});
</script>

<template>
    <section :class="['rounded-md border border-gray-200 bg-white', props.padding ? 'p-4' : '']">
        <slot />
    </section>
</template>
