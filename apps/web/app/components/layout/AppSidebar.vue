<script setup lang="ts">
import { SIDEBAR_GROUPS } from '~/config/navigation';
import { useAuthStore } from '~/stores/auth';

interface Props {
    open: boolean;
    collapsed: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'logout'): void;
    (e: 'toggle-collapse'): void;
}>();

const auth = useAuthStore();
const hovered = ref(false);

const visuallyExpanded = computed(() => !props.collapsed || hovered.value || props.open);

const visibleGroups = computed(() =>
    SIDEBAR_GROUPS.map((group) => ({
        ...group,
        children: group.children.filter((child) => auth.can(child.permission)),
    })).filter((group) => group.children.length > 0),
);

const initials = computed(() => {
    const name = auth.user?.name ?? 'CL';
    return name
        .split(' ')
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
});
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-40 bg-black/25 lg:hidden"
        @click="emit('close')"
    />

    <aside
        :class="[
            open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
            visuallyExpanded ? 'w-[240px]' : 'w-[240px] lg:w-[64px]',
            collapsed && hovered ? 'lg:shadow-lg' : '',
        ]"
        class="fixed inset-y-0 left-0 z-50 flex h-screen flex-col overflow-hidden border-r border-gray-200 bg-white transition-all duration-200"
        @mouseenter="hovered = true"
        @mouseleave="hovered = false"
    >
        <!-- Fixed Sidebar Header -->
        <div class="flex h-[54px] shrink-0 items-center justify-between overflow-hidden border-b border-gray-100 px-3.5">
            <NuxtLink to="/" class="flex min-w-0 items-center gap-2.5 overflow-hidden">
                <div class="flex size-8 shrink-0 items-center justify-center rounded bg-blue-600 text-xs font-medium text-white">
                    <Icon name="lucide:heart-pulse" class="size-4" />
                </div>
                <div v-show="visuallyExpanded" class="overflow-hidden whitespace-nowrap leading-tight">
                    <span class="block text-[13px] font-medium text-gray-900">Portal da Clínica</span>
                    <span class="block text-[10px] text-gray-400">Saúde Multi-Tenant</span>
                </div>
            </NuxtLink>

            <button
                v-show="visuallyExpanded"
                id="btn-toggle-sidebar-collapse"
                type="button"
                :title="collapsed ? 'Fixar sidebar expandida' : 'Recolher sidebar'"
                class="hidden size-7 shrink-0 items-center justify-center rounded text-gray-400 hover:bg-gray-100 hover:text-gray-700 lg:flex"
                @click="emit('toggle-collapse')"
            >
                <Icon :name="collapsed ? 'lucide:panel-left-open' : 'lucide:panel-left-close'" class="size-3.5" />
            </button>

            <button
                type="button"
                class="flex size-7 shrink-0 items-center justify-center rounded text-gray-400 hover:bg-gray-100 lg:hidden"
                aria-label="Fechar menu"
                @click="emit('close')"
            >
                <Icon name="lucide:x" class="size-4" />
            </button>
        </div>

        <!-- Scrollable Navigation Links ONLY -->
        <nav class="flex-1 space-y-2 overflow-y-auto overflow-x-hidden px-3 py-3">
            <LayoutSidebarNavGroup
                v-for="group in visibleGroups"
                :key="group.id"
                :group="group"
                :compact="!visuallyExpanded"
            />
        </nav>

        <!-- Fixed Sidebar Footer -->
        <div class="shrink-0 overflow-hidden border-t border-gray-100 p-3">
            <div class="flex items-center gap-2.5 overflow-hidden rounded p-1">
                <div class="flex size-7 shrink-0 items-center justify-center rounded bg-blue-50 text-[11px] font-medium text-blue-700">
                    {{ initials }}
                </div>
                <div v-show="visuallyExpanded" class="min-w-0 flex-1 overflow-hidden whitespace-nowrap">
                    <div class="truncate text-[12px] font-normal text-gray-800">
                        {{ auth.user?.name }}
                    </div>
                    <div class="truncate text-[10px] text-gray-400">
                        {{ auth.primaryRole }}
                    </div>
                </div>
                <button
                    v-show="visuallyExpanded"
                    type="button"
                    title="Sair"
                    class="flex size-7 shrink-0 items-center justify-center rounded text-gray-400 transition-colors hover:bg-rose-50 hover:text-rose-600"
                    @click="emit('logout')"
                >
                    <Icon name="lucide:log-out" class="size-3.5" />
                </button>
            </div>
        </div>
    </aside>
</template>
