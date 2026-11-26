<script setup lang="ts">
const { mobileOpen, collapsed, initFromStorage } = useSidebarState();

onMounted(() => {
    initFromStorage();
});
</script>

<template>
    <div class="min-h-screen bg-[#f9fafb]">
        <AppSidebar :open="mobileOpen" @close="mobileOpen = false" />

        <div
            :class="[
                'flex min-h-screen flex-col transition-[margin-left] duration-200 ease-out',
                collapsed ? 'lg:ml-[64px]' : 'lg:ml-[240px]',
            ]"
        >
            <AppNavbar @open-sidebar="mobileOpen = true" />

            <main class="mx-auto w-full max-w-[1600px] flex-1 px-4 py-5 lg:px-6 lg:py-6">
                <slot />
            </main>
        </div>

        <CommandPalette />
        <ConfirmModal />
    </div>
</template>
