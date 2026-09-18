<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { UiButton, UiBadge } from '@saas/ui';
</script>

<template>
    <div class="min-h-screen flex flex-col bg-gray-50 text-gray-900">
        <header
            class="h-14 border-b border-gray-200 bg-white px-6 flex items-center justify-between"
        >
            <div class="flex items-center gap-2">
                <div
                    class="w-7 h-7 rounded-lg bg-emerald-600 flex items-center justify-center text-white text-sm"
                >
                    <Icon icon="lucide:heart-pulse" class="w-4 h-4" />
                </div>
                <span class="text-sm font-semibold tracking-tight">Patient Portal</span>
                <UiBadge variant="success">Secure Action</UiBadge>
            </div>
            <div class="text-xs text-gray-500">Autonomous Patient Care</div>
        </header>

        <main class="flex-1 p-6 max-w-xl mx-auto w-full flex flex-col justify-center">
            <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
                <div class="text-center mb-6">
                    <div
                        class="w-12 h-12 bg-emerald-100 text-emerald-700 rounded-full flex items-center justify-center mx-auto mb-3"
                    >
                        <Icon icon="lucide:calendar-check-2" class="w-6 h-6" />
                    </div>
                    <h1 class="text-base font-semibold text-gray-900">Appointment Confirmation</h1>
                    <p class="text-xs text-gray-500 mt-1">
                        Confirm, reschedule, or check-in without requiring passwords.
                    </p>
                </div>

                <div class="bg-gray-50 rounded p-4 border border-gray-200 space-y-2 mb-6 text-xs">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Doctor</span>
                        <span class="font-medium text-gray-800">Dr. Robert Carter (Neurology)</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Date & Time</span>
                        <span class="font-medium text-gray-800"
                            >October 15, 2026 at 14:00 (UTC-3)</span
                        >
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Status</span>
                        <UiBadge variant="warning">Awaiting Confirmation</UiBadge>
                    </div>
                </div>

                <div class="flex gap-2">
                    <UiButton variant="secondary" size="sm" class="flex-1" icon="lucide:calendar-x">
                        Reschedule
                    </UiButton>
                    <UiButton variant="primary" size="sm" class="flex-1" icon="lucide:check">
                        Confirm Appointment
                    </UiButton>
                </div>
            </div>
        </main>
    </div>
</template>
