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
const userMenuOpen = ref(false);
const userMenuRef = ref<HTMLElement | null>(null);

function handleUserMenuOutside(event: MouseEvent): void {
    if (!userMenuRef.value) return;
    if (!userMenuRef.value.contains(event.target as Node)) {
        userMenuOpen.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', handleUserMenuOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', handleUserMenuOutside));

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
                <div class="flex size-8 shrink-0 items-center justify-center rounded bg-teal-600 text-xs font-medium text-white">
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

        <!-- Fixed Sidebar Footer (User Dropup) -->
        <div ref="userMenuRef" class="relative shrink-0 border-t border-gray-100 p-3">
            <div
                v-if="userMenuOpen && visuallyExpanded"
                class="absolute bottom-full left-3 right-3 z-50 mb-1 rounded border border-gray-200 bg-white py-1 shadow-lg"
            >
                <NuxtLink
                    to="/profile"
                    class="flex h-8 items-center gap-2 px-3 text-[12px] text-gray-700 hover:bg-gray-50"
                    @click="userMenuOpen = false; emit('close')"
                >
                    <Icon name="lucide:user-cog" class="size-3.5 text-gray-500" />
                    <span>Meu Perfil</span>
                </NuxtLink>
                <button
                    type="button"
                    class="flex h-8 w-full items-center gap-2 px-3 text-left text-[12px] text-rose-600 hover:bg-rose-50"
                    @click="userMenuOpen = false; emit('logout')"
                >
                    <Icon name="lucide:log-out" class="size-3.5" />
                    <span>Sair</span>
                </button>
            </div>

            <button
                id="btn-sidebar-user-menu"
                type="button"
                class="flex w-full items-center gap-2.5 overflow-hidden rounded p-1 text-left transition-colors hover:bg-gray-50"
                @click="userMenuOpen = !userMenuOpen"
            >
                <div class="flex size-7 shrink-0 items-center justify-center overflow-hidden rounded-full bg-teal-50 text-[11px] font-medium text-teal-700">
                    <img
                        v-if="auth.user?.avatar_url"
                        :src="auth.user.avatar_url"
                        alt="Avatar"
                        class="size-full object-cover"
                    />
                    <span v-else>{{ initials }}</span>
                </div>
                <div v-show="visuallyExpanded" class="min-w-0 flex-1 overflow-hidden whitespace-nowrap">
                    <div class="truncate text-[12px] font-normal text-gray-800">
                        {{ auth.user?.name }}
                    </div>
                    <div class="truncate text-[10px] text-gray-400">
                        {{ auth.primaryRole }}
                    </div>
                </div>
                <Icon
                    v-show="visuallyExpanded"
                    name="lucide:chevrons-up-down"
                    class="size-3.5 shrink-0 text-gray-400"
                />
            </button>
        </div>
    </aside>
</template>
