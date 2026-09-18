<script setup lang="ts">
import { ref, computed } from 'vue';
import { Icon } from '@iconify/vue';
import { UiButton, UiBadge, UiCard, UiInput, UiSelect, UiModal } from '@saas/ui';

interface Professional {
    id: string;
    name: string;
    specialty: string;
    color: string;
}

interface AppointmentSlot {
    time: string;
    patientName?: string;
    status: 'available' | 'booked' | 'blocked';
    type?: string;
    insurance?: string;
}

interface WaitlistEntry {
    id: string;
    patientName: string;
    phone: string;
    priority: 'high' | 'normal';
    requestedTime: string;
}

// Current clinic / tenant context
const activeTenant = ref({
    id: 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
    name: 'Clinica Santa Monica',
    schema: 'tenant_f47ac10b_58cc_4372',
});

// Navigation state
const activeTab = ref<'schedule' | 'waitlist' | 'analytics'>('schedule');

// Professionals list
const professionals = ref<Professional[]>([
    { id: '1', name: 'Dr. Robert Carter', specialty: 'Neurology', color: '#2563eb' },
    { id: '2', name: 'Dr. Amanda Stone', specialty: 'Dermatology', color: '#059669' },
    { id: '3', name: 'Dr. Gregory House', specialty: 'Diagnostics', color: '#d97706' },
]);

const selectedProfessionalId = ref('1');

const selectedProfessional = computed(() => {
    return (
        professionals.value.find((p) => p.id === selectedProfessionalId.value) ??
        professionals.value[0]
    );
});

// Current date
const currentDate = ref('2026-10-15');

// Mock slots for demonstration
const slots = ref<AppointmentSlot[]>([
    {
        time: '08:00',
        patientName: 'Carlos Silva',
        status: 'booked',
        type: 'First Consultation',
        insurance: 'Unimed',
    },
    { time: '08:30', status: 'available' },
    {
        time: '09:00',
        patientName: 'Mariana Costa',
        status: 'booked',
        type: 'Follow-up',
        insurance: 'Private',
    },
    { time: '09:30', status: 'available' },
    { time: '10:00', status: 'blocked', type: 'Clinical Staff Meeting' },
    { time: '10:30', status: 'available' },
    {
        time: '11:00',
        patientName: 'Fernando Souza',
        status: 'booked',
        type: 'Exam Review',
        insurance: 'Bradesco Saúde',
    },
    { time: '11:30', status: 'available' },
    { time: '14:00', status: 'available' },
    {
        time: '14:30',
        patientName: 'Beatriz Lima',
        status: 'booked',
        type: 'Return (Free)',
        insurance: 'Private',
    },
    { time: '15:00', status: 'available' },
    { time: '15:30', status: 'available' },
]);

// Waitlist candidates
const waitlist = ref<WaitlistEntry[]>([
    {
        id: 'w1',
        patientName: 'Juliana Mendes',
        phone: '+55 11 98822-1100',
        priority: 'high',
        requestedTime: 'Morning',
    },
    {
        id: 'w2',
        patientName: 'Lucas Oliveira',
        phone: '+55 11 97711-2233',
        priority: 'normal',
        requestedTime: 'Afternoon',
    },
    {
        id: 'w3',
        patientName: 'Camila Rocha',
        phone: '+55 11 99933-4455',
        priority: 'normal',
        requestedTime: 'Any',
    },
]);

// Modal state
const isBookingModalOpen = ref(false);
const isCancelModalOpen = ref(false);
const selectedSlotToCancel = ref<AppointmentSlot | null>(null);

// Form state
const newBooking = ref({
    patientName: '',
    patientPhone: '',
    time: '08:30',
    type: 'first_consultation',
    billingType: 'private',
    insuranceName: '',
    authCode: '',
});

const bookingSuccessMessage = ref('');
const bookingErrorMessage = ref('');

function openBookingModal(time?: string): void {
    if (time) {
        newBooking.value.time = time;
    }
    bookingSuccessMessage.value = '';
    bookingErrorMessage.value = '';
    isBookingModalOpen.value = true;
}

function handleBookSubmit(): void {
    if (!newBooking.value.patientName || !newBooking.value.patientPhone) {
        bookingErrorMessage.value = 'Please provide patient name and phone number.';
        return;
    }

    // Check collision
    const existing = slots.value.find((s) => s.time === newBooking.value.time);
    if (existing && existing.status !== 'available') {
        bookingErrorMessage.value = 'Selected slot is already occupied or blocked.';
        return;
    }

    // Book slot
    if (existing) {
        existing.status = 'booked';
        existing.patientName = newBooking.value.patientName;
        existing.type =
            newBooking.value.type === 'first_consultation' ? 'First Consultation' : 'Follow-up';
        existing.insurance =
            newBooking.value.billingType === 'private'
                ? 'Private'
                : newBooking.value.insuranceName || 'Insurance';
    }

    bookingSuccessMessage.value = `Appointment booked for ${newBooking.value.patientName} at ${newBooking.value.time}!`;
    setTimeout(() => {
        isBookingModalOpen.value = false;
        newBooking.value.patientName = '';
        newBooking.value.patientPhone = '';
    }, 1200);
}

function promptCancelSlot(slot: AppointmentSlot): void {
    selectedSlotToCancel.value = slot;
    isCancelModalOpen.value = true;
}

function confirmCancelSlot(): void {
    if (!selectedSlotToCancel.value) return;

    selectedSlotToCancel.value.status = 'available';
    selectedSlotToCancel.value.patientName = undefined;
    selectedSlotToCancel.value.type = undefined;
    selectedSlotToCancel.value.insurance = undefined;

    // Trigger auto-fill from waitlist if available
    if (waitlist.value.length > 0) {
        const candidate = waitlist.value.shift();
        if (candidate) {
            alert(
                `Cancellation processed! Auto-fill offer dispatched via WhatsApp/SMS to waitlist candidate: ${candidate.patientName} (${candidate.phone})`,
            );
        }
    }

    isCancelModalOpen.value = false;
    selectedSlotToCancel.value = null;
}
</script>

<template>
    <div class="min-h-screen flex bg-[#f8f8f7] text-[#1d1d1a]">
        <!-- Dark Minimal Sidebar adhering to DESIGN.md -->
        <aside
            class="w-56 bg-[#18181b] text-[#a1a1aa] flex flex-col justify-between border-r border-[#27272a] select-none"
        >
            <div>
                <!-- Brand / Clinic Logo -->
                <div class="h-14 px-4 flex items-center gap-2.5 border-b border-[#27272a]">
                    <div
                        class="w-6 h-6 rounded bg-[#f45b45] flex items-center justify-center text-white"
                    >
                        <Icon icon="lucide:stethoscope" class="w-3.5 h-3.5" />
                    </div>
                    <div class="overflow-hidden">
                        <span class="text-xs font-semibold text-white tracking-tight block truncate"
                            >ClinicSaaS B2B</span
                        >
                        <span class="text-[10px] text-[#71717a] block truncate">{{
                            activeTenant.name
                        }}</span>
                    </div>
                </div>

                <!-- Navigation Links -->
                <nav class="p-2.5 space-y-1 text-xs">
                    <button
                        type="button"
                        @click="activeTab = 'schedule'"
                        class="w-full flex items-center gap-2.5 px-3 py-2 rounded transition-colors"
                        :class="[
                            activeTab === 'schedule'
                                ? 'bg-[#27272a] text-white font-medium'
                                : 'hover:bg-[#27272a]/50 hover:text-white',
                        ]"
                    >
                        <Icon icon="lucide:calendar" class="w-4 h-4" />
                        <span>Schedule Grid</span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'waitlist'"
                        class="w-full flex items-center justify-between px-3 py-2 rounded transition-colors"
                        :class="[
                            activeTab === 'waitlist'
                                ? 'bg-[#27272a] text-white font-medium'
                                : 'hover:bg-[#27272a]/50 hover:text-white',
                        ]"
                    >
                        <div class="flex items-center gap-2.5">
                            <Icon icon="lucide:users" class="w-4 h-4" />
                            <span>Smart Waitlist</span>
                        </div>
                        <span
                            class="text-[10px] bg-amber-500/20 text-amber-300 px-1.5 py-0.5 rounded font-mono"
                        >
                            {{ waitlist.length }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'analytics'"
                        class="w-full flex items-center gap-2.5 px-3 py-2 rounded transition-colors"
                        :class="[
                            activeTab === 'analytics'
                                ? 'bg-[#27272a] text-white font-medium'
                                : 'hover:bg-[#27272a]/50 hover:text-white',
                        ]"
                    >
                        <Icon icon="lucide:activity" class="w-4 h-4" />
                        <span>Practice Metrics</span>
                    </button>
                </nav>
            </div>

            <!-- Footer / Tenant Schema Indicator -->
            <div class="p-3 border-t border-[#27272a] text-[11px] space-y-1">
                <div class="flex items-center justify-between text-[#71717a]">
                    <span>PostgreSQL Schema:</span>
                    <span
                        class="w-2 h-2 rounded-full bg-emerald-500"
                        title="Active & Isolated"
                    ></span>
                </div>
                <div class="font-mono text-[10px] text-[#a1a1aa] truncate">
                    {{ activeTenant.schema }}
                </div>
            </div>
        </aside>

        <!-- Main Workspace -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Top App Bar -->
            <header
                class="h-14 border-b border-[#dededb] bg-white px-6 flex items-center justify-between"
            >
                <div class="flex items-center gap-3">
                    <span class="text-xs font-semibold text-[#1d1d1a]">Practice Management</span>
                    <span class="text-xs text-[#dededb]">/</span>
                    <span class="text-xs text-[#73736c]">
                        {{
                            activeTab === 'schedule'
                                ? 'Appointments & Slots'
                                : activeTab === 'waitlist'
                                  ? 'Auto-fill Waitlist'
                                  : 'Analytics & Performance'
                        }}
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    <UiButton
                        variant="primary"
                        size="sm"
                        icon="lucide:plus"
                        @click="openBookingModal()"
                    >
                        New Appointment
                    </UiButton>
                    <div class="h-4 w-px bg-[#dededb]" />
                    <div
                        class="w-7 h-7 rounded-full bg-[#f3f3f0] border border-[#dededb] flex items-center justify-center text-xs font-medium text-[#454540]"
                    >
                        TF
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 p-6 overflow-y-auto">
                <!-- Statistics Metric Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <UiCard>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-medium text-[#73736c]">Total Slots</span>
                            <Icon icon="lucide:calendar-clock" class="w-4 h-4 text-[#73736c]" />
                        </div>
                        <div class="text-2xl font-semibold text-[#1d1d1a] tracking-tight">12</div>
                        <p class="text-[11px] text-[#73736c] mt-1">30-min shifts (UTC synced)</p>
                    </UiCard>

                    <UiCard>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-medium text-[#73736c]">Occupancy Rate</span>
                            <UiBadge variant="success">Active</UiBadge>
                        </div>
                        <div class="text-2xl font-semibold text-[#1d1d1a] tracking-tight">
                            41.6%
                        </div>
                        <p class="text-[11px] text-emerald-600 mt-1">Pessimistic lock protected</p>
                    </UiCard>

                    <UiCard>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-medium text-[#73736c]">Waitlist Queue</span>
                            <UiBadge variant="warning">{{ waitlist.length }} Pending</UiBadge>
                        </div>
                        <div class="text-2xl font-semibold text-[#1d1d1a] tracking-tight">
                            {{ waitlist.length }}
                        </div>
                        <p class="text-[11px] text-[#73736c] mt-1">FIFO priority dispatch</p>
                    </UiCard>

                    <UiCard>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-medium text-[#73736c]"
                                >Security Boundary</span
                            >
                            <UiBadge variant="info">Isolated</UiBadge>
                        </div>
                        <div class="text-2xl font-semibold text-[#1d1d1a] tracking-tight">
                            Multi-tenant
                        </div>
                        <p class="text-[11px] text-[#73736c] mt-1">PostgreSQL schema isolation</p>
                    </UiCard>
                </div>

                <!-- Main Schedule View -->
                <section v-if="activeTab === 'schedule'" class="space-y-4">
                    <!-- Schedule Header & Filters -->
                    <div
                        class="p-4 bg-white rounded-lg border border-[#dededb] flex flex-wrap items-center justify-between gap-4"
                    >
                        <!-- Professional Selection -->
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-[#73736c]">Doctor:</label>
                            <div class="flex gap-1.5">
                                <button
                                    v-for="prof in professionals"
                                    :key="prof.id"
                                    type="button"
                                    @click="selectedProfessionalId = prof.id"
                                    class="px-3 py-1 text-xs rounded border transition-colors flex items-center gap-1.5"
                                    :class="[
                                        selectedProfessionalId === prof.id
                                            ? 'bg-[#1d1d1a] text-white border-[#1d1d1a] font-medium'
                                            : 'bg-white text-[#454540] border-[#dededb] hover:bg-[#f8f8f7]',
                                    ]"
                                >
                                    <span
                                        class="w-2 h-2 rounded-full"
                                        :style="{ backgroundColor: prof.color }"
                                    />
                                    {{ prof.name }}
                                </button>
                            </div>
                        </div>

                        <!-- Date Navigation -->
                        <div class="flex items-center gap-2">
                            <UiButton variant="secondary" size="sm" icon="lucide:chevron-left" />
                            <div
                                class="px-3 py-1 bg-[#f8f8f7] rounded border border-[#dededb] text-xs font-medium font-mono text-[#1d1d1a]"
                            >
                                {{ currentDate }} (Thursday)
                            </div>
                            <UiButton variant="secondary" size="sm" icon="lucide:chevron-right" />
                            <UiButton variant="secondary" size="sm">Today</UiButton>
                        </div>
                    </div>

                    <!-- Slot Grid -->
                    <div class="bg-white rounded-lg border border-[#dededb] overflow-hidden">
                        <div
                            class="px-5 py-3 border-b border-[#dededb] bg-[#fbfbfa] flex items-center justify-between"
                        >
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold text-[#1d1d1a]">{{
                                    selectedProfessional.name
                                }}</span>
                                <UiBadge variant="info">{{
                                    selectedProfessional.specialty
                                }}</UiBadge>
                            </div>
                            <span class="text-xs text-[#73736c]"
                                >Available Shift: 08:00 - 17:00</span
                            >
                        </div>

                        <div class="divide-y divide-[#dededb]">
                            <div
                                v-for="slot in slots"
                                :key="slot.time"
                                class="p-3.5 flex items-center justify-between hover:bg-[#fdfdfc] transition-colors"
                            >
                                <div class="flex items-center gap-4">
                                    <div
                                        class="w-16 font-mono text-xs font-semibold text-[#1d1d1a]"
                                    >
                                        {{ slot.time }}
                                    </div>

                                    <div
                                        v-if="slot.status === 'booked'"
                                        class="flex items-center gap-3"
                                    >
                                        <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                        <div>
                                            <span class="text-xs font-medium text-[#1d1d1a] block">
                                                {{ slot.patientName }}
                                            </span>
                                            <span
                                                class="text-[11px] text-[#73736c] flex items-center gap-1.5"
                                            >
                                                <span>{{ slot.type }}</span>
                                                <span>•</span>
                                                <span class="text-blue-600 font-medium">{{
                                                    slot.insurance
                                                }}</span>
                                            </span>
                                        </div>
                                    </div>

                                    <div
                                        v-else-if="slot.status === 'blocked'"
                                        class="flex items-center gap-3 text-[#73736c]"
                                    >
                                        <div class="w-2 h-2 rounded-full bg-zinc-400"></div>
                                        <span class="text-xs italic"
                                            >{{ slot.type }} (Unavailability Block)</span
                                        >
                                    </div>

                                    <div v-else class="flex items-center gap-3 text-emerald-600">
                                        <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                        <span class="text-xs font-medium">Free Slot (30m)</span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <template v-if="slot.status === 'available'">
                                        <UiButton
                                            variant="secondary"
                                            size="sm"
                                            icon="lucide:plus"
                                            @click="openBookingModal(slot.time)"
                                        >
                                            Book Slot
                                        </UiButton>
                                    </template>

                                    <template v-else-if="slot.status === 'booked'">
                                        <UiButton
                                            variant="secondary"
                                            size="sm"
                                            class="text-rose-600 hover:text-rose-700"
                                            icon="lucide:x"
                                            @click="promptCancelSlot(slot)"
                                        >
                                            Cancel
                                        </UiButton>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Waitlist Section -->
                <section v-else-if="activeTab === 'waitlist'" class="space-y-4">
                    <UiCard
                        title="Intelligent Void Recovery (FIFO Waitlist)"
                        description="Patients queued for appointment openings. Auto-fill triggers upon cancellation."
                    >
                        <div class="space-y-2">
                            <div
                                v-for="entry in waitlist"
                                :key="entry.id"
                                class="p-3 bg-[#fbfbfa] rounded border border-[#dededb] flex items-center justify-between"
                            >
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-medium text-[#1d1d1a]">{{
                                            entry.patientName
                                        }}</span>
                                        <UiBadge
                                            :variant="entry.priority === 'high' ? 'danger' : 'info'"
                                        >
                                            {{
                                                entry.priority === 'high'
                                                    ? 'High Priority'
                                                    : 'Standard'
                                            }}
                                        </UiBadge>
                                    </div>
                                    <p class="text-[11px] text-[#73736c] font-mono mt-0.5">
                                        {{ entry.phone }} • Preferred Shift:
                                        {{ entry.requestedTime }}
                                    </p>
                                </div>

                                <UiButton variant="secondary" size="sm" icon="lucide:send">
                                    Send Fast Offer
                                </UiButton>
                            </div>
                        </div>
                    </UiCard>
                </section>

                <!-- Metrics Section -->
                <section v-else-if="activeTab === 'analytics'" class="space-y-4">
                    <UiCard
                        title="Audit & Tenancy Health"
                        description="Transactional integrity and database schema monitoring."
                    >
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                            <div class="p-3 bg-[#f8f8f7] rounded border border-[#dededb] space-y-2">
                                <span class="font-semibold text-[#1d1d1a]"
                                    >Tenancy Architecture</span
                                >
                                <p class="text-[#73736c]">
                                    Each clinic runs in an isolated PostgreSQL schema (<code
                                        class="text-[10px] bg-white px-1 py-0.5 rounded border border-[#dededb]"
                                        >tenant_{uuid}</code
                                    >). Cross-tenant query pollution is impossible at the database
                                    engine level.
                                </p>
                            </div>
                            <div class="p-3 bg-[#f8f8f7] rounded border border-[#dededb] space-y-2">
                                <span class="font-semibold text-[#1d1d1a]">Concurrency Engine</span>
                                <p class="text-[#73736c]">
                                    Pessimistic slot locks (<code
                                        class="text-[10px] bg-white px-1 py-0.5 rounded border border-[#dededb]"
                                        >SELECT FOR UPDATE</code
                                    >) ensure strict zero double-booking even under massive
                                    concurrent requests.
                                </p>
                            </div>
                        </div>
                    </UiCard>
                </section>
            </main>
        </div>

        <!-- New Appointment Modal -->
        <UiModal
            :is-open="isBookingModalOpen"
            title="Book New Appointment"
            description="Pessimistic lock slot reservation with insurance validation."
            @close="isBookingModalOpen = false"
        >
            <form @submit.prevent="handleBookSubmit" class="space-y-3">
                <div
                    v-if="bookingSuccessMessage"
                    class="p-2.5 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded text-xs"
                >
                    {{ bookingSuccessMessage }}
                </div>
                <div
                    v-if="bookingErrorMessage"
                    class="p-2.5 bg-rose-50 text-rose-800 border border-rose-200 rounded text-xs"
                >
                    {{ bookingErrorMessage }}
                </div>

                <UiInput
                    v-model="newBooking.patientName"
                    label="Patient Full Name"
                    placeholder="e.g. Lucas Silveira"
                    required
                />

                <UiInput
                    v-model="newBooking.patientPhone"
                    label="Mobile Phone / WhatsApp"
                    placeholder="+55 11 99999-0000"
                    required
                />

                <div class="grid grid-cols-2 gap-3">
                    <UiSelect
                        v-model="newBooking.time"
                        label="Time Slot"
                        :options="
                            slots.map((s) => ({ label: `${s.time} (${s.status})`, value: s.time }))
                        "
                    />

                    <UiSelect
                        v-model="newBooking.billingType"
                        label="Billing Method"
                        :options="[
                            { label: 'Private (Particular)', value: 'private' },
                            { label: 'Health Insurance (Convênio)', value: 'insurance' },
                            { label: 'Pre-paid Package', value: 'package' },
                        ]"
                    />
                </div>

                <div v-if="newBooking.billingType === 'insurance'" class="grid grid-cols-2 gap-3">
                    <UiInput
                        v-model="newBooking.insuranceName"
                        label="Insurance Name"
                        placeholder="e.g. Unimed Seguros"
                    />
                    <UiInput
                        v-model="newBooking.authCode"
                        label="Prior Auth Code"
                        placeholder="AUTH-998811"
                    />
                </div>

                <div class="pt-3 border-t border-[#dededb] flex justify-end gap-2">
                    <UiButton
                        variant="secondary"
                        size="sm"
                        type="button"
                        @click="isBookingModalOpen = false"
                    >
                        Cancel
                    </UiButton>
                    <UiButton variant="primary" size="sm" type="submit" icon="lucide:check">
                        Confirm Booking
                    </UiButton>
                </div>
            </form>
        </UiModal>

        <!-- Cancel Confirmation Modal -->
        <UiModal
            :is-open="isCancelModalOpen"
            title="Cancel Appointment"
            description="Slot will be immediately freed and auto-fill will notify waitlist candidates."
            @close="isCancelModalOpen = false"
        >
            <div class="space-y-4">
                <p class="text-xs text-[#454540]">
                    Are you sure you want to cancel the appointment for
                    <span class="font-semibold">{{ selectedSlotToCancel?.patientName }}</span>
                    at <span class="font-semibold">{{ selectedSlotToCancel?.time }}</span
                    >?
                </p>

                <div class="flex justify-end gap-2 pt-3 border-t border-[#dededb]">
                    <UiButton
                        variant="secondary"
                        size="sm"
                        type="button"
                        @click="isCancelModalOpen = false"
                    >
                        Keep Appointment
                    </UiButton>
                    <UiButton
                        variant="danger"
                        size="sm"
                        type="button"
                        icon="lucide:trash-2"
                        @click="confirmCancelSlot"
                    >
                        Confirm Cancellation
                    </UiButton>
                </div>
            </div>
        </UiModal>
    </div>
</template>
