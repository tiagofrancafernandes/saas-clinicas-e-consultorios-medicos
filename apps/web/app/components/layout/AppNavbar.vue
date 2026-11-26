<script setup lang="ts">
import { useAuthStore } from '~/stores/auth';

const emit = defineEmits<{ (e: 'open-sidebar'): void; (e: 'open-palette'): void }>();

const route = useRoute();
const auth = useAuthStore();

const pageTitle = computed(() => {
    const titles: Record<string, string> = {
        '/': 'Painel da Clínica',
        '/appointments': 'Agendamentos',
        '/waitlist': 'Fila de Espera',
        '/patients': 'Pacientes',
        '/medical-records': 'Prontuários SOAP',
        '/users': 'Usuários e Permissões',
        '/clinic': 'Dados da Clínica',
    };
    return titles[route.path] ?? 'Clínica';
});
</script>

<template>
    <header class="sticky top-0 z-30 flex h-[54px] shrink-0 items-center border-b border-gray-200 bg-white px-4 lg:px-6">
        <button
            type="button"
            class="mr-3 flex size-8 items-center justify-center rounded text-gray-500 hover:bg-gray-100 lg:hidden"
            aria-label="Abrir menu"
            @click="emit('open-sidebar')"
        >
            <Icon name="lucide:menu" class="size-4" />
        </button>

        <div class="flex items-center gap-2 text-[12px]">
            <span class="text-gray-400">Clínica</span>
            <Icon name="lucide:chevron-right" class="size-3 text-gray-300" />
            <span class="font-normal text-gray-800">{{ pageTitle }}</span>
        </div>

        <div class="ml-auto flex items-center gap-2">
            <button
                id="btn-command-palette"
                type="button"
                class="flex h-7 items-center gap-2 rounded border border-gray-200 bg-gray-50/70 px-2.5 text-[11px] text-gray-500 transition-colors hover:bg-gray-100"
                @click="emit('open-palette')"
            >
                <Icon name="lucide:search" class="size-3.5 text-gray-400" />
                <span class="hidden sm:inline">Buscar ação...</span>
                <span class="ml-2 rounded border border-gray-200 bg-white px-1 py-0.5 text-[9px] text-gray-400">
                    Ctrl K
                </span>
            </button>

            <div class="hidden h-4 w-px bg-gray-200 sm:block" />

            <span class="hidden rounded border border-gray-200 bg-gray-50 px-2 py-0.5 text-[10px] text-gray-600 sm:inline-block">
                {{ auth.primaryRole }}
            </span>
        </div>
    </header>
</template>
