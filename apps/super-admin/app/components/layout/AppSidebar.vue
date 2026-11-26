<script setup lang="ts">
import { SIDEBAR_NAV_GROUPS } from '~/constants/navigation';
import { getInitials } from '~/utils/formatters';

interface Props {
    open: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    close: [];
}>();

const { user, logout } = useAuth();
const { confirm } = useConfirmModal();
const { collapsed, hovered, isVisuallyExpanded, initFromStorage, toggleCollapsed, setHovered } = useSidebarState();

onMounted(() => {
    initFromStorage();
});

async function handleLogout(): Promise<void> {
    const confirmed = await confirm({
        title: 'Encerrar sessão',
        message: 'Deseja realmente sair do painel Super Admin?',
        confirmLabel: 'Sair',
        cancelLabel: 'Cancelar',
        variant: 'primary',
    });

    if (confirmed) {
        await logout();
    }
}
</script>

<template>
    <div v-if="props.open" class="fixed inset-0 z-40 bg-black/30 lg:hidden" @click="emit('close')" />

    <aside
        :class="[
            'fixed inset-y-0 left-0 z-50 flex flex-col overflow-hidden border-r border-gray-200 bg-white transition-[width,transform,box-shadow] duration-200 ease-out',
            props.open ? 'translate-x-0 w-[240px]' : '-translate-x-full lg:translate-x-0',
            isVisuallyExpanded ? 'lg:w-[240px]' : 'lg:w-[64px]',
            collapsed && hovered ? 'lg:shadow-xl' : '',
        ]"
        @mouseenter="setHovered(true)"
        @mouseleave="setHovered(false)"
    >
        <!-- Fixed Header -->
        <div class="flex h-[56px] shrink-0 items-center overflow-hidden border-b border-gray-100 px-3.5">
            <NuxtLink
                to="/"
                class="flex min-w-0 items-center gap-2.5 overflow-hidden whitespace-nowrap"
                @click="emit('close')"
            >
                <div
                    class="flex size-8 shrink-0 items-center justify-center rounded bg-blue-600 text-[12px] font-medium text-white"
                >
                    SA
                </div>
                <div
                    :class="[
                        'overflow-hidden leading-tight whitespace-nowrap transition-opacity duration-150',
                        isVisuallyExpanded ? 'opacity-100' : 'opacity-0',
                    ]"
                >
                    <div class="text-[13px] font-medium tracking-tight text-gray-900">Super Admin</div>
                    <div class="text-[10px] font-light text-gray-400">Saúde SaaS Control</div>
                </div>
            </NuxtLink>

            <button
                v-if="isVisuallyExpanded"
                type="button"
                :title="collapsed ? 'Fixar sidebar expandida' : 'Recolher sidebar'"
                class="ml-auto hidden size-7 shrink-0 cursor-pointer items-center justify-center rounded text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 lg:flex"
                @click="toggleCollapsed"
            >
                <Icon :name="collapsed ? 'lucide:panel-left-open' : 'lucide:panel-left-close'" class="size-3.5" />
            </button>

            <button
                type="button"
                aria-label="Fechar menu"
                class="ml-auto flex size-7 shrink-0 cursor-pointer items-center justify-center rounded text-gray-400 hover:bg-gray-100 hover:text-gray-700 lg:hidden"
                @click="emit('close')"
            >
                <Icon name="lucide:x" class="size-4" />
            </button>
        </div>

        <!-- Scrollable Nav Links Area -->
        <nav class="flex-1 space-y-5 overflow-x-hidden overflow-y-auto px-3 py-4">
            <div v-for="group in SIDEBAR_NAV_GROUPS" :key="group.title">
                <div
                    :class="[
                        'mb-1.5 overflow-hidden px-2 text-[10px] font-medium uppercase tracking-wider whitespace-nowrap text-gray-400 transition-opacity duration-150',
                        isVisuallyExpanded ? 'opacity-100' : 'opacity-0',
                    ]"
                >
                    {{ group.title }}
                </div>
                <div class="space-y-0.5">
                    <SidebarNavGroup
                        v-for="item in group.items"
                        :key="item.id"
                        :item="item"
                        :expanded-view="isVisuallyExpanded"
                        @navigate="emit('close')"
                    />
                </div>
            </div>
        </nav>

        <!-- Fixed Footer -->
        <div class="shrink-0 overflow-hidden border-t border-gray-100 p-3">
            <div class="flex items-center gap-2.5 overflow-hidden rounded px-1.5 py-1.5 whitespace-nowrap">
                <div
                    class="flex size-7 shrink-0 items-center justify-center rounded-full bg-blue-50 text-[11px] font-medium text-blue-700"
                >
                    {{ getInitials(user?.name || 'Super Admin') }}
                </div>
                <div
                    :class="[
                        'min-w-0 flex-1 overflow-hidden whitespace-nowrap transition-opacity duration-150',
                        isVisuallyExpanded ? 'opacity-100' : 'opacity-0',
                    ]"
                >
                    <div class="truncate text-[12px] font-normal text-gray-900">
                        {{ user?.name || 'Super Admin' }}
                    </div>
                    <div class="truncate text-[10px] font-light text-gray-400">
                        {{ user?.email || 'admin@plataforma.com' }}
                    </div>
                </div>
                <button
                    v-if="isVisuallyExpanded"
                    type="button"
                    title="Sair"
                    class="flex size-7 shrink-0 cursor-pointer items-center justify-center rounded text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600"
                    @click="handleLogout"
                >
                    <Icon name="lucide:log-out" class="size-3.5" />
                </button>
            </div>
        </div>
    </aside>
</template>
