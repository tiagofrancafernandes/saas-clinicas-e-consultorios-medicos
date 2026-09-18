<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { UiButton, UiBadge } from '@saas/ui';
</script>

<template>
    <div class="min-h-screen flex flex-col bg-[#f8f8f7] text-[#1d1d1a]">
        <header
            class="h-12 border-b border-[#dededb] bg-white px-4 flex items-center justify-between"
        >
            <div class="flex items-center gap-2">
                <div
                    class="w-6 h-6 rounded bg-[#f45b45] flex items-center justify-center text-white text-xs font-semibold"
                >
                    <Icon icon="lucide:stethoscope" class="w-3.5 h-3.5" />
                </div>
                <span class="text-sm font-semibold tracking-tight">ClinicSaaS Admin</span>
                <UiBadge variant="info">
                    <span class="text-[10px]">Multi-tenant</span>
                </UiBadge>
            </div>
            <div class="flex items-center gap-2">
                <UiButton variant="secondary" size="sm" icon="lucide:bell">
                    <span class="sr-only">Notifications</span>
                </UiButton>
                <div
                    class="w-7 h-7 rounded-full bg-[#dededb] flex items-center justify-center text-xs font-medium"
                >
                    AD
                </div>
            </div>
        </header>

        <main class="flex-1 p-6 max-w-7xl mx-auto w-full">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-lg font-semibold text-[#1d1d1a]">
                        Practice Management Dashboard
                    </h1>
                    <p class="text-xs text-[#73736c]">
                        Welcome to the clinic scheduling & patient care management platform.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <UiButton variant="secondary" size="sm" icon="lucide:calendar">
                        Schedule
                    </UiButton>
                    <UiButton variant="primary" size="sm" icon="lucide:plus">
                        New Appointment
                    </UiButton>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-4 bg-white rounded border border-[#dededb] shadow-sm">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-[#73736c]">Today's Appointments</span>
                        <UiBadge variant="success">Active</UiBadge>
                    </div>
                    <div class="text-2xl font-semibold text-[#1d1d1a]">24</div>
                    <p class="text-[11px] text-[#73736c] mt-1">Zero double-booking detected</p>
                </div>

                <div class="p-4 bg-white rounded border border-[#dededb] shadow-sm">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-[#73736c]">Waitlist Auto-Fill</span>
                        <UiBadge variant="warning">3 Pending</UiBadge>
                    </div>
                    <div class="text-2xl font-semibold text-[#1d1d1a]">7</div>
                    <p class="text-[11px] text-[#73736c] mt-1">Intelligent void recovery active</p>
                </div>

                <div class="p-4 bg-white rounded border border-[#dededb] shadow-sm">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-[#73736c]">Schema Isolation</span>
                        <UiBadge variant="info">PostgreSQL</UiBadge>
                    </div>
                    <div class="text-2xl font-semibold text-[#1d1d1a]">Strict</div>
                    <p class="text-[11px] text-[#73736c] mt-1">tenant_{uuid} schema partitioning</p>
                </div>
            </div>
        </main>
    </div>
</template>
