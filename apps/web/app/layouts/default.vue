<script setup lang="ts">
import { useAuthStore } from '~/stores/auth';

const SIDEBAR_STORAGE_KEY = 'clinic_sidebar_collapsed';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const sidebarOpen = ref(false);
const sidebarCollapsed = ref(false);
const paletteOpen = ref(false);

watch(
    () => route.fullPath,
    () => {
        sidebarOpen.value = false;
    },
);

function toggleSidebarCollapse(): void {
    sidebarCollapsed.value = !sidebarCollapsed.value;
    if (import.meta.client) {
        localStorage.setItem(SIDEBAR_STORAGE_KEY, sidebarCollapsed.value ? '1' : '0');
    }
}

function handleKeydown(event: KeyboardEvent): void {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        paletteOpen.value = !paletteOpen.value;
    } else if (
        event.key === '/' &&
        !['INPUT', 'TEXTAREA', 'SELECT'].includes((event.target as HTMLElement)?.tagName ?? '')
    ) {
        event.preventDefault();
        paletteOpen.value = true;
    }
}

onMounted(() => {
    sidebarCollapsed.value = localStorage.getItem(SIDEBAR_STORAGE_KEY) === '1';
    window.addEventListener('keydown', handleKeydown);
});
onUnmounted(() => window.removeEventListener('keydown', handleKeydown));

async function handleLogout(): Promise<void> {
    await auth.logout();
    await router.push('/login');
}
</script>

<template>
    <div class="min-h-screen bg-[#f9fafb]">
        <LayoutAppSidebar
            :open="sidebarOpen"
            :collapsed="sidebarCollapsed"
            @close="sidebarOpen = false"
            @toggle-collapse="toggleSidebarCollapse"
            @logout="handleLogout"
        />

        <div
            class="flex min-h-screen flex-col transition-[margin] duration-200"
            :class="sidebarCollapsed ? 'lg:ml-[64px]' : 'lg:ml-[240px]'"
        >
            <LayoutAppNavbar
                @open-sidebar="sidebarOpen = true"
                @open-palette="paletteOpen = true"
            />

            <main class="mx-auto w-full max-w-[1520px] flex-1 px-4 py-5 lg:px-7 lg:py-6">
                <slot />
            </main>
        </div>

        <LayoutCommandPaletteModal
            :open="paletteOpen"
            @close="paletteOpen = false"
        />
    </div>
</template>
