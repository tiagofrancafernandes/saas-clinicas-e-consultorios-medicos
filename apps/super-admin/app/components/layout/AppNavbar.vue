<script setup lang="ts">
import { getInitials } from '~/utils/formatters';

const emit = defineEmits<{
    openSidebar: [];
}>();

const route = useRoute();
const { user } = useAuth();
const { open: openCommandPalette } = useCommandPalette();

const breadcrumbLabel = computed(() => {
    const path = route.path;
    if (path === '/') {
        return 'Dashboard';
    }
    if (path.startsWith('/tenants/') && path !== '/tenants') {
        return 'Detalhes da Clínica';
    }
    if (path.startsWith('/tenants')) {
        return 'Clínicas (Tenants)';
    }
    if (path.startsWith('/subscriptions')) {
        return 'Assinaturas e Planos';
    }
    if (path.startsWith('/users')) {
        return 'Usuários & ACL';
    }
    if (path.startsWith('/audit-logs')) {
        return 'Auditoria Central';
    }
    if (path.startsWith('/settings')) {
        return 'Configurações';
    }
    return 'Painel';
});
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-[56px] shrink-0 items-center border-b border-gray-200 bg-white px-4 lg:px-6"
    >
        <button
            type="button"
            aria-label="Abrir menu"
            class="mr-3 flex size-8 cursor-pointer items-center justify-center rounded text-gray-500 hover:bg-gray-100 lg:hidden"
            @click="emit('openSidebar')"
        >
            <Icon name="lucide:menu" class="size-4" />
        </button>

        <div class="flex items-center gap-1.5 text-[12px]">
            <span class="font-light text-gray-400">Super Admin</span>
            <Icon name="lucide:chevron-right" class="size-3.5 text-gray-300" />
            <span class="font-normal text-gray-800">{{ breadcrumbLabel }}</span>
        </div>

        <div class="ml-auto flex items-center gap-2">
            <!-- Command Palette Trigger -->
            <button
                type="button"
                class="flex h-8 cursor-pointer items-center gap-2 rounded-md border border-gray-200 bg-gray-50/70 px-2.5 text-[12px] font-light text-gray-500 transition-colors hover:border-gray-300 hover:bg-white sm:w-[230px]"
                @click="openCommandPalette"
            >
                <Icon name="lucide:search" class="size-3.5 shrink-0 text-gray-400" />
                <span class="hidden flex-1 truncate text-left sm:inline">Comando rápido...</span>
                <kbd
                    class="hidden rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[10px] font-normal text-gray-400 sm:inline"
                >
                    ⌘K
                </kbd>
            </button>

            <div class="mx-1 hidden h-4 w-px bg-gray-200 sm:block" />

            <div
                class="flex size-7 items-center justify-center rounded-full bg-blue-50 text-[11px] font-medium text-blue-700"
                :title="user?.name || 'Super Admin'"
            >
                {{ getInitials(user?.name || 'Super Admin') }}
            </div>
        </div>
    </header>
</template>
