<script setup lang="ts">
import { PATIENT_NAV_ITEMS } from '~/constants/navigation';

const route = useRoute();
</script>

<template>
    <nav class="md:hidden fixed bottom-0 inset-x-0 z-30 h-16 bg-white border-t border-slate-200 grid grid-cols-4 px-2">
        <NuxtLink
            v-for="item in PATIENT_NAV_ITEMS"
            :key="item.to"
            :to="item.to"
            class="flex flex-col items-center justify-center gap-1 text-[11px] font-medium transition-colors"
            :class="route.path === item.to ? 'text-sky-600' : 'text-slate-500'"
        >
            <Icon :name="item.icon" class="w-5 h-5" />
            <span>{{ item.label }}</span>
        </NuxtLink>
    </nav>
</template>
