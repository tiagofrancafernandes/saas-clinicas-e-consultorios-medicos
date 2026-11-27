<script setup lang="ts">
import { PATIENT_NAV_ITEMS } from '~/constants/navigation';
import { usePortalSummaryStore } from '~/stores/usePortalSummaryStore';

const route = useRoute();
const { user, logout } = usePatientAuth();
const summaryStore = usePortalSummaryStore();

const clinicName = computed(() => summaryStore.summary?.clinic?.name || 'Portal do Paciente');
const loggingOut = ref(false);

async function handleLogout(): Promise<void> {
    loggingOut.value = true;
    try {
        summaryStore.reset();
        await logout();
    } finally {
        loggingOut.value = false;
    }
}
</script>

<template>
    <header class="fixed top-0 inset-x-0 z-30 h-14 bg-white/95 backdrop-blur border-b border-slate-200">
        <div class="max-w-5xl mx-auto h-full px-4 sm:px-6 flex items-center justify-between gap-4">
            <div class="flex items-center gap-6">
                <NuxtLink to="/" class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-sky-600 text-white flex items-center justify-center">
                        <Icon name="heroicons:heart" class="w-4 h-4" />
                    </div>
                    <div class="leading-tight">
                        <span class="block text-sm font-semibold text-slate-900">
                            {{ clinicName }}
                        </span>
                        <span class="block text-[11px] text-slate-500">Área do Paciente</span>
                    </div>
                </NuxtLink>

                <nav class="hidden md:flex items-center gap-1">
                    <NuxtLink
                        v-for="item in PATIENT_NAV_ITEMS"
                        :key="item.to"
                        :to="item.to"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
                        :class="
                            route.path === item.to
                                ? 'bg-sky-50 text-sky-700'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                        "
                    >
                        <Icon :name="item.icon" class="w-4 h-4" />
                        <span>{{ item.label }}</span>
                    </NuxtLink>
                </nav>
            </div>

            <div class="flex items-center gap-3">
                <span class="hidden sm:inline text-xs text-slate-500">
                    {{ user?.name }}
                </span>
                <UiButton
                    variant="ghost"
                    size="sm"
                    icon="heroicons:arrow-right-on-rectangle"
                    :loading="loggingOut"
                    @click="handleLogout"
                >
                    Sair
                </UiButton>
            </div>
        </div>
    </header>
</template>
