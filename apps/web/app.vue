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

interface ArrivalQueueItem {
    id: string;
    patientName: string;
    arrivedAt: string;
    scheduledTime: string;
    doctor: string;
    room: string;
    status: 'WAITING_ROOM' | 'IN_CONSULTATION' | 'COMPLETED';
}

// Current clinic / tenant context
const activeTenant = ref({
    id: 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
    name: 'Clinica Santa Monica',
    schema: 'tenant_f47ac10b_58cc_4372',
});

// Primary Role Switcher: Reception, Doctor, Clinic Admin, Super Admin
const activeRole = ref<'reception' | 'doctor' | 'clinic-admin' | 'super-admin'>('reception');

// Reception Sub-tabs: schedule vs waitlist vs analytics
const receptionTab = ref<'schedule' | 'waitlist' | 'analytics'>('schedule');

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

// Reception Waiting Room Arrivals
const arrivalsQueue = ref<ArrivalQueueItem[]>([
    {
        id: 'arr_1',
        patientName: 'Carlos Silva',
        arrivedAt: '13:52',
        scheduledTime: '14:00',
        doctor: 'Dr. Robert Carter',
        room: 'Room 102 - Neurology Suite',
        status: 'WAITING_ROOM',
    },
    {
        id: 'arr_2',
        patientName: 'Mariana Costa',
        arrivedAt: '14:15',
        scheduledTime: '14:30',
        doctor: 'Dr. Amanda Stone',
        room: 'Room 101 - General Practice',
        status: 'WAITING_ROOM',
    },
]);

const summonAlertMessage = ref('');

function handleSummonPatient(item: ArrivalQueueItem): void {
    item.status = 'IN_CONSULTATION';
    summonAlertMessage.value = `📢 Patient ${item.patientName} summoned to ${item.room}! Real-time broadcast (PatientSummonedToRoom) sent to TV waiting displays.`;
}

function handleCheckinPatient(item: ArrivalQueueItem): void {
    item.status = 'WAITING_ROOM';
}

// Doctor SOAP Notes & Consultation State
const soapNote = ref({
    title: 'Neurological Assessment - Migraine Evolution',
    subjective: 'Patient reports cephalalgia reduction from 5x to 1x weekly after prophylactic therapy.',
    objective: 'Normal cranial nerve reflexes. Blood pressure 120/80 mmHg. MRI normal.',
    assessment: 'Chronic migraine with aura (CID-10 G43.1) in clinical remission.',
    plan: 'Maintain daily topiramate 25mg. Scheduled 60-day return consultation.',
    visibility: 'PRIVATE' as 'PRIVATE' | 'SHARED_TEAM' | 'PUBLIC_CLINIC',
});

const soapSavedMessage = ref('');

function handleSaveSoap(): void {
    soapSavedMessage.value = `Clinical note saved with tier: ${soapNote.value.visibility}. Scoped access rules verified!`;
}

const delegationMessage = ref('');

function handleGrantDoctorDelegation(): void {
    delegationMessage.value = 'Temporary delegation granted to Dr. Amanda Stone for 48 hours.';
}

// Clinic Admin State (Rooms, Packages, Health Insurances, Subscription)
const clinicRooms = ref([
    { id: '77777777-7777-7777-7777-777777777777', name: 'Room 101 - General Practice', status: 'ACTIVE' },
    { id: '88888888-8888-8888-8888-888888888888', name: 'Room 102 - Neurology Suite', status: 'ACTIVE' },
]);

const clinicPackages = ref([
    { name: 'Physical Therapy (10 Sessions)', sessions: 10, price: 'R$ 1.500,00', validity: '90 days' },
    { name: 'Acupuncture Protocol (5 Sessions)', sessions: 5, price: 'R$ 800,00', validity: '60 days' },
]);

const clinicInsurances = ref([
    { name: 'Unimed Seguros', ans: '30001', status: 'Active (Direct Billing)' },
    { name: 'Bradesco Saúde', ans: '005711', status: 'Active (Prior Auth Req)' },
]);

const clinicSubscription = ref({
    status: 'ACTIVE' as 'ACTIVE' | 'SUSPENDED',
    plan: 'Clinic Pro Enterprise',
    interval: 'Monthly',
    nextBilling: '2027-09-18',
});

function toggleSubscriptionStatus(): void {
    clinicSubscription.value.status =
        clinicSubscription.value.status === 'ACTIVE' ? 'SUSPENDED' : 'ACTIVE';
}

// Super Admin State (Central Platform)
const centralTenants = ref([
    {
        id: 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
        name: 'Clinica Santa Monica',
        slug: 'clinica-santa-monica',
        timezone: 'America/Sao_Paulo',
        status: 'ACTIVE',
        schema: 'tenant_f47ac10b_58cc_4372',
    },
    {
        id: 'a8912734-1122-3344-5566-778899aabbcc',
        name: 'Instituto de Olhos Paulista',
        slug: 'olhos-paulista',
        timezone: 'America/Sao_Paulo',
        status: 'ACTIVE',
        schema: 'tenant_a8912734_1122_3344',
    },
]);

const centralAuditLogs = ref([
    { id: '1', action: 'TENANT_CREATED', target: 'Clinica Santa Monica', actor: 'system_root', time: '11:30:00' },
    { id: '2', action: 'SUBSCRIPTION_ACTIVATED', target: 'Clinica Santa Monica (Pro)', actor: 'billing_webhook', time: '11:30:05' },
    { id: '3', action: 'SCHEMA_MIGRATION_RUN', target: 'tenant_f47ac10b_58cc_4372', actor: 'migration_daemon', time: '11:30:12' },
    { id: '4', action: 'SECURITY_AUDIT_LOG', target: 'stancl_tenancy_guard', actor: 'audit_service', time: '11:35:00' },
]);

const isProvisionModalOpen = ref(false);
const newTenantName = ref('');
const newTenantSlug = ref('');
const provisionSuccessMessage = ref('');

function handleProvisionTenant(): void {
    if (!newTenantName.value) return;
    const slug = newTenantSlug.value || newTenantName.value.toLowerCase().replace(/\s+/g, '-');
    const newId = 'tenant-' + Date.now();
    centralTenants.value.push({
        id: newId,
        name: newTenantName.value,
        slug,
        timezone: 'America/Sao_Paulo',
        status: 'ACTIVE',
        schema: 'tenant_' + Math.random().toString(36).substring(2, 9),
    });
    centralAuditLogs.value.unshift({
        id: String(Date.now()),
        action: 'TENANT_CREATED',
        target: newTenantName.value,
        actor: 'super_admin_ui',
        time: new Date().toLocaleTimeString(),
    });
    provisionSuccessMessage.value = `Tenant "${newTenantName.value}" provisioned with PostgreSQL schema isolation!`;
    setTimeout(() => {
        isProvisionModalOpen.value = false;
        newTenantName.value = '';
        newTenantSlug.value = '';
        provisionSuccessMessage.value = '';
    }, 1000);
}

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

    const existing = slots.value.find((s) => s.time === newBooking.value.time);
    if (existing && existing.status !== 'available') {
        bookingErrorMessage.value = 'Selected slot is already occupied or blocked.';
        return;
    }

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
            class="w-60 bg-[#18181b] text-[#a1a1aa] flex flex-col justify-between border-r border-[#27272a] select-none"
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

                <!-- Role Switcher Section -->
                <div class="p-2.5 border-b border-[#27272a]">
                    <span class="text-[10px] uppercase font-semibold text-[#71717a] tracking-wider block px-2 mb-1.5"
                        >Role Perspective</span
                    >
                    <div class="space-y-1 text-xs">
                        <button
                            id="btn-role-reception"
                            type="button"
                            @click="activeRole = 'reception'"
                            class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded transition-colors"
                            :class="[
                                activeRole === 'reception'
                                    ? 'bg-[#27272a] text-white font-medium'
                                    : 'hover:bg-[#27272a]/50 hover:text-white',
                            ]"
                        >
                            <Icon icon="lucide:clipboard-check" class="w-4 h-4 text-emerald-400" />
                            <span>Reception Desk</span>
                        </button>

                        <button
                            id="btn-role-doctor"
                            type="button"
                            @click="activeRole = 'doctor'"
                            class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded transition-colors"
                            :class="[
                                activeRole === 'doctor'
                                    ? 'bg-[#27272a] text-white font-medium'
                                    : 'hover:bg-[#27272a]/50 hover:text-white',
                            ]"
                        >
                            <Icon icon="lucide:user-check" class="w-4 h-4 text-blue-400" />
                            <span>Healthcare Doctor</span>
                        </button>

                        <button
                            id="btn-role-clinic-admin"
                            type="button"
                            @click="activeRole = 'clinic-admin'"
                            class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded transition-colors"
                            :class="[
                                activeRole === 'clinic-admin'
                                    ? 'bg-[#27272a] text-white font-medium'
                                    : 'hover:bg-[#27272a]/50 hover:text-white',
                            ]"
                        >
                            <Icon icon="lucide:building-2" class="w-4 h-4 text-amber-400" />
                            <span>Clinic Admin</span>
                        </button>

                        <button
                            id="btn-role-super-admin"
                            type="button"
                            @click="activeRole = 'super-admin'"
                            class="w-full flex items-center gap-2.5 px-3 py-1.5 rounded transition-colors"
                            :class="[
                                activeRole === 'super-admin'
                                    ? 'bg-[#27272a] text-white font-medium'
                                    : 'hover:bg-[#27272a]/50 hover:text-white',
                            ]"
                        >
                            <Icon icon="lucide:shield" class="w-4 h-4 text-purple-400" />
                            <span>Super Admin (SaaS)</span>
                        </button>
                    </div>
                </div>

                <!-- Reception Context Navigation -->
                <nav v-if="activeRole === 'reception'" class="p-2.5 space-y-1 text-xs">
                    <button
                        type="button"
                        @click="receptionTab = 'schedule'"
                        class="w-full flex items-center gap-2.5 px-3 py-2 rounded transition-colors"
                        :class="[
                            receptionTab === 'schedule'
                                ? 'bg-[#27272a] text-white font-medium'
                                : 'hover:bg-[#27272a]/50 hover:text-white',
                        ]"
                    >
                        <Icon icon="lucide:calendar" class="w-4 h-4" />
                        <span>Schedule Grid</span>
                    </button>

                    <button
                        type="button"
                        @click="receptionTab = 'waitlist'"
                        class="w-full flex items-center justify-between px-3 py-2 rounded transition-colors"
                        :class="[
                            receptionTab === 'waitlist'
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
                        @click="receptionTab = 'analytics'"
                        class="w-full flex items-center gap-2.5 px-3 py-2 rounded transition-colors"
                        :class="[
                            receptionTab === 'analytics'
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
                    <span class="text-xs font-semibold text-[#1d1d1a]">
                        {{
                            activeRole === 'reception'
                                ? 'Reception Desk'
                                : activeRole === 'doctor'
                                  ? 'Doctor Consultation Desk'
                                  : activeRole === 'clinic-admin'
                                    ? 'Clinic Administration'
                                    : 'SaaS Multi-tenant Control'
                        }}
                    </span>
                    <span class="text-xs text-[#dededb]">/</span>
                    <span class="text-xs text-[#73736c]">
                        {{ activeTenant.name }}
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    <UiButton
                        id="btn-open-booking"
                        v-if="activeRole === 'reception'"
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

            <!-- Page Content Switcher -->
            <main class="flex-1 p-6 overflow-y-auto">
                <!-- ============================================== -->
                <!-- 1. RECEPTION WORKSPACE                         -->
                <!-- ============================================== -->
                <div v-if="activeRole === 'reception'" id="reception-workspace" class="space-y-6">
                    <!-- Live Summon Chime Toast Alert -->
                    <div
                        v-if="summonAlertMessage"
                        id="summon-alert-banner"
                        class="p-3 bg-emerald-50 border border-emerald-300 rounded text-xs text-emerald-900 flex items-center justify-between"
                    >
                        <div class="flex items-center gap-2">
                            <Icon icon="lucide:bell-ring" class="w-4 h-4 text-emerald-600 animate-bounce" />
                            <span>{{ summonAlertMessage }}</span>
                        </div>
                        <button type="button" @click="summonAlertMessage = ''" class="text-emerald-700 hover:text-emerald-900 font-bold">×</button>
                    </div>

                    <!-- Waiting Room Live Arrivals -->
                    <UiCard title="Waiting Room Live Arrivals" description="Patients physically present in the reception waiting for consultation.">
                        <div class="space-y-2 mt-2">
                            <div
                                v-for="item in arrivalsQueue"
                                :key="item.id"
                                :id="item.id"
                                class="p-3 bg-[#fbfbfa] rounded border border-[#dededb] flex items-center justify-between"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-700 flex items-center justify-center font-bold text-xs">
                                        {{ item.patientName.charAt(0) }}
                                    </div>
                                    <div>
                                        <span class="text-xs font-semibold text-[#1d1d1a] block">{{ item.patientName }}</span>
                                        <span class="text-[11px] text-[#73736c]">
                                            Arrived at {{ item.arrivedAt }} • Scheduled: {{ item.scheduledTime }} • {{ item.doctor }} ({{ item.room }})
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <UiBadge :variant="item.status === 'IN_CONSULTATION' ? 'info' : 'success'">
                                        {{ item.status === 'IN_CONSULTATION' ? 'In Consultation' : 'Waiting Room' }}
                                    </UiBadge>
                                    <UiButton
                                        :id="'btn-summon-patient-' + item.id"
                                        v-if="item.status === 'WAITING_ROOM'"
                                        variant="primary"
                                        size="sm"
                                        icon="lucide:volume-2"
                                        @click="handleSummonPatient(item)"
                                    >
                                        Summon to Room
                                    </UiButton>
                                </div>
                            </div>
                        </div>
                    </UiCard>

                    <!-- Reception Schedule Grid -->
                    <section v-if="receptionTab === 'schedule'" class="space-y-4">
                        <div class="p-4 bg-white rounded-lg border border-[#dededb] flex flex-wrap items-center justify-between gap-4">
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
                                        <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: prof.color }" />
                                        {{ prof.name }}
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <UiButton variant="secondary" size="sm" icon="lucide:chevron-left" />
                                <div class="px-3 py-1 bg-[#f8f8f7] rounded border border-[#dededb] text-xs font-medium font-mono text-[#1d1d1a]">
                                    {{ currentDate }} (Thursday)
                                </div>
                                <UiButton variant="secondary" size="sm" icon="lucide:chevron-right" />
                                <UiButton variant="secondary" size="sm">Today</UiButton>
                            </div>
                        </div>

                        <!-- Slots Grid -->
                        <div class="bg-white rounded-lg border border-[#dededb] overflow-hidden">
                            <div class="px-5 py-3 border-b border-[#dededb] bg-[#fbfbfa] flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-[#1d1d1a]">{{ selectedProfessional.name }}</span>
                                    <UiBadge variant="info">{{ selectedProfessional.specialty }}</UiBadge>
                                </div>
                                <span class="text-xs text-[#73736c]">Weekly Shift: 08:00 - 17:00</span>
                            </div>

                            <div class="divide-y divide-[#f3f3f0]">
                                <div
                                    v-for="slot in slots"
                                    :key="slot.time"
                                    class="px-5 py-3 flex items-center justify-between hover:bg-[#fcfcfb] transition-colors"
                                >
                                    <div class="flex items-center gap-4">
                                        <span class="font-mono text-xs font-semibold w-12 text-[#1d1d1a]">{{ slot.time }}</span>
                                        <div v-if="slot.status === 'booked'" class="space-y-0.5">
                                            <span class="text-xs font-medium text-[#1d1d1a] block">{{ slot.patientName }}</span>
                                            <div class="flex items-center gap-2 text-[10px] text-[#73736c]">
                                                <span>{{ slot.type }}</span>
                                                <span>•</span>
                                                <span class="font-medium text-[#454540]">{{ slot.insurance }}</span>
                                            </div>
                                        </div>
                                        <div v-else-if="slot.status === 'blocked'" class="text-xs text-[#a1a1aa] italic">
                                            {{ slot.type || 'Unavailable / Blocked' }}
                                        </div>
                                        <div v-else class="text-xs text-emerald-600 font-medium">Available Slot (30 min)</div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <UiBadge v-if="slot.status === 'booked'" variant="success">Booked</UiBadge>
                                        <UiBadge v-else-if="slot.status === 'blocked'" variant="danger">Blocked</UiBadge>
                                        <UiBadge v-else variant="info">Free</UiBadge>

                                        <UiButton
                                            v-if="slot.status === 'available'"
                                            variant="secondary"
                                            size="sm"
                                            icon="lucide:calendar-plus"
                                            @click="openBookingModal(slot.time)"
                                        >
                                            Book Slot
                                        </UiButton>
                                        <UiButton
                                            v-else-if="slot.status === 'booked'"
                                            variant="secondary"
                                            size="sm"
                                            class="text-rose-600 hover:text-rose-700"
                                            icon="lucide:x"
                                            @click="promptCancelSlot(slot)"
                                        >
                                            Cancel
                                        </UiButton>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Smart Waitlist Section -->
                    <section v-else-if="receptionTab === 'waitlist'" class="space-y-4">
                        <UiCard title="Schedule Void Auto-Fill Waitlist" description="Prioritized queue of patients waiting for cancellations.">
                            <div class="space-y-2 mt-2">
                                <div
                                    v-for="entry in waitlist"
                                    :key="entry.id"
                                    class="p-3 bg-[#fbfbfa] rounded border border-[#dededb] flex items-center justify-between"
                                >
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-semibold text-[#1d1d1a]">{{ entry.patientName }}</span>
                                            <UiBadge :variant="entry.priority === 'high' ? 'danger' : 'info'">
                                                {{ entry.priority === 'high' ? 'High Priority' : 'Standard' }}
                                            </UiBadge>
                                        </div>
                                        <p class="text-[11px] text-[#73736c] font-mono mt-0.5">
                                            {{ entry.phone }} • Preferred Shift: {{ entry.requestedTime }}
                                        </p>
                                    </div>
                                    <UiButton variant="secondary" size="sm" icon="lucide:send">
                                        Send Fast Offer
                                    </UiButton>
                                </div>
                            </div>
                        </UiCard>
                    </section>
                </div>

                <!-- ============================================== -->
                <!-- 2. HEALTHCARE PROFESSIONAL (DOCTOR) WORKSPACE  -->
                <!-- ============================================== -->
                <div v-else-if="activeRole === 'doctor'" id="doctor-workspace" class="space-y-6 max-w-4xl">
                    <!-- Doctor Selector -->
                    <div class="p-3 bg-white rounded-lg border border-[#dededb] flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-medium text-[#73736c]">Practitioner Desk:</span>
                            <div class="flex gap-2">
                                <button
                                    id="btn-select-doctor-1"
                                    type="button"
                                    @click="selectedProfessionalId = '1'"
                                    class="px-2.5 py-1 text-xs rounded border transition-colors font-medium"
                                    :class="[selectedProfessionalId === '1' ? 'bg-[#1d1d1a] text-white border-[#1d1d1a]' : 'bg-white text-[#454540] border-[#dededb]']"
                                >
                                    Dr. Robert Carter (Neurology)
                                </button>
                                <button
                                    id="btn-select-doctor-2"
                                    type="button"
                                    @click="selectedProfessionalId = '2'"
                                    class="px-2.5 py-1 text-xs rounded border transition-colors font-medium"
                                    :class="[selectedProfessionalId === '2' ? 'bg-[#1d1d1a] text-white border-[#1d1d1a]' : 'bg-white text-[#454540] border-[#dededb]']"
                                >
                                    Dr. Amanda Stone (Dermatology)
                                </button>
                            </div>
                        </div>
                        <UiBadge variant="info">Room 102 Connected</UiBadge>
                    </div>

                    <!-- In-Room Consultation Desk -->
                    <UiCard title="Current Consultation - Room 102" description="Live electronic health record and SOAP progress note editor.">
                        <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded flex items-center justify-between text-xs text-blue-900">
                            <div>
                                <strong>Patient in Room:</strong> Carlos Silva (38 yrs • CPF ***.456.789-** • Unimed Seguros)
                            </div>
                            <UiBadge variant="success">Attending Now</UiBadge>
                        </div>

                        <!-- SOAP Alert -->
                        <div v-if="soapSavedMessage" id="soap-saved-alert" class="mb-4 p-2.5 bg-emerald-50 text-emerald-900 border border-emerald-300 rounded text-xs">
                            {{ soapSavedMessage }}
                        </div>

                        <!-- SOAP Form -->
                        <div class="space-y-3">
                            <UiInput
                                id="input-soap-title"
                                v-model="soapNote.title"
                                label="Clinical Evolution Title"
                                placeholder="e.g. Neurological Assessment"
                            />

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs font-medium text-[#1d1d1a] block mb-1">Subjective (S - Anamnese)</label>
                                    <textarea
                                        id="textarea-soap-s"
                                        v-model="soapNote.subjective"
                                        rows="2"
                                        class="w-full text-xs p-2.5 rounded border border-[#dededb] bg-white focus:outline-none focus:border-[#1d1d1a]"
                                    ></textarea>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-[#1d1d1a] block mb-1">Objective (O - Exame Físico)</label>
                                    <textarea
                                        id="textarea-soap-o"
                                        v-model="soapNote.objective"
                                        rows="2"
                                        class="w-full text-xs p-2.5 rounded border border-[#dededb] bg-white focus:outline-none focus:border-[#1d1d1a]"
                                    ></textarea>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs font-medium text-[#1d1d1a] block mb-1">Assessment (A - Diagnóstico / CID)</label>
                                    <textarea
                                        id="textarea-soap-a"
                                        v-model="soapNote.assessment"
                                        rows="2"
                                        class="w-full text-xs p-2.5 rounded border border-[#dededb] bg-white focus:outline-none focus:border-[#1d1d1a]"
                                    ></textarea>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-[#1d1d1a] block mb-1">Plan (P - Conduta & Prescrição)</label>
                                    <textarea
                                        id="textarea-soap-p"
                                        v-model="soapNote.plan"
                                        rows="2"
                                        class="w-full text-xs p-2.5 rounded border border-[#dededb] bg-white focus:outline-none focus:border-[#1d1d1a]"
                                    ></textarea>
                                </div>
                            </div>

                            <!-- Visibility Tiers -->
                            <div class="pt-2 border-t border-[#dededb]">
                                <label class="text-xs font-semibold text-[#1d1d1a] block mb-1.5">Medical Confidentiality Tier (Privacy Boundary):</label>
                                <div class="flex gap-4 text-xs">
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input
                                            id="radio-visibility-private"
                                            type="radio"
                                            value="PRIVATE"
                                            v-model="soapNote.visibility"
                                            name="visibility"
                                        />
                                        <span class="font-medium text-rose-800">PRIVATE</span>
                                        <span class="text-[#73736c] text-[10px]">(Only authoring doctor)</span>
                                    </label>
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input
                                            id="radio-visibility-team"
                                            type="radio"
                                            value="SHARED_TEAM"
                                            v-model="soapNote.visibility"
                                            name="visibility"
                                        />
                                        <span class="font-medium text-blue-800">SHARED_TEAM</span>
                                        <span class="text-[#73736c] text-[10px]">(Clinical team & delegates)</span>
                                    </label>
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input
                                            id="radio-visibility-public"
                                            type="radio"
                                            value="PUBLIC_CLINIC"
                                            v-model="soapNote.visibility"
                                            name="visibility"
                                        />
                                        <span class="font-medium text-emerald-800">PUBLIC_CLINIC</span>
                                        <span class="text-[#73736c] text-[10px]">(All clinic staff)</span>
                                    </label>
                                </div>
                            </div>

                            <div class="pt-3 flex justify-end gap-2">
                                <UiButton
                                    id="btn-save-soap"
                                    variant="primary"
                                    size="sm"
                                    icon="lucide:check"
                                    @click="handleSaveSoap"
                                >
                                    Save SOAP Evolution
                                </UiButton>
                            </div>
                        </div>
                    </UiCard>

                    <!-- Temporary Delegation Card -->
                    <UiCard title="Doctor Delegation Management" description="Temporarily grant confidential medical record access to another practitioner.">
                        <div v-if="delegationMessage" id="delegation-alert" class="mb-3 p-2 bg-emerald-50 text-emerald-900 border border-emerald-300 rounded text-xs">
                            {{ delegationMessage }}
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <div>
                                <span class="font-medium text-[#1d1d1a] block">Delegate to Dr. Amanda Stone (Dermatology)</span>
                                <span class="text-[11px] text-[#73736c]">Covering clinical shift • Duration: 48h</span>
                            </div>
                            <UiButton
                                id="btn-grant-delegation"
                                variant="secondary"
                                size="sm"
                                icon="lucide:user-plus"
                                @click="handleGrantDoctorDelegation"
                            >
                                Grant Temporary Access
                            </UiButton>
                        </div>
                    </UiCard>
                </div>

                <!-- ============================================== -->
                <!-- 3. CLINIC ADMIN WORKSPACE                      -->
                <!-- ============================================== -->
                <div v-else-if="activeRole === 'clinic-admin'" id="clinic-admin-workspace" class="space-y-6 max-w-4xl">
                    <!-- Subscription Status Widget with Write Lock Toggle -->
                    <UiCard title="SaaS Subscription & Write Lock Status" description="Tenant subscription state machine and API mutation guard.">
                        <div class="flex items-center justify-between p-3 bg-[#fbfbfa] rounded border border-[#dededb]">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-[#1d1d1a]">{{ clinicSubscription.plan }}</span>
                                    <UiBadge
                                        id="subscription-status-badge"
                                        :variant="clinicSubscription.status === 'ACTIVE' ? 'success' : 'danger'"
                                    >
                                        {{ clinicSubscription.status }}
                                    </UiBadge>
                                </div>
                                <p class="text-[11px] text-[#73736c] mt-0.5">
                                    Next billing: {{ clinicSubscription.nextBilling }} • Interval: {{ clinicSubscription.interval }}
                                </p>
                            </div>
                            <UiButton
                                id="btn-toggle-subscription"
                                variant="secondary"
                                size="sm"
                                @click="toggleSubscriptionStatus"
                            >
                                Simulate {{ clinicSubscription.status === 'ACTIVE' ? 'Suspension' : 'Activation' }}
                            </UiButton>
                        </div>

                        <div
                            v-if="clinicSubscription.status === 'SUSPENDED'"
                            id="write-lock-alert"
                            class="mt-3 p-3 bg-rose-50 border border-rose-300 rounded text-xs text-rose-900"
                        >
                            <strong>⚠️ Subscription Suspended:</strong> Write operations are locked via <code class="bg-rose-100 px-1 py-0.5 rounded">CheckTenantSubscription</code> middleware. The clinic is running in Read-Only mode.
                        </div>
                        <div v-else class="mt-2 text-[11px] text-emerald-700">
                            ✓ Normal Operation: Write locks disabled. Booking, updates, and records allowed.
                        </div>
                    </UiCard>

                    <!-- Rooms List Card -->
                    <UiCard id="clinic-rooms-card" title="Consulting Rooms" description="Configured clinical rooms with deterministic IDs.">
                        <div class="space-y-2 mt-2">
                            <div
                                v-for="room in clinicRooms"
                                :key="room.id"
                                class="p-2.5 bg-[#fbfbfa] rounded border border-[#dededb] flex items-center justify-between text-xs"
                            >
                                <div>
                                    <span class="font-medium text-[#1d1d1a] block">{{ room.name }}</span>
                                    <span class="font-mono text-[10px] text-[#73736c]">UUID: {{ room.id }}</span>
                                </div>
                                <UiBadge variant="success">{{ room.status }}</UiBadge>
                            </div>
                        </div>
                    </UiCard>

                    <!-- Packages & Health Insurances -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <UiCard id="clinic-packages-card" title="Treatment Packages" description="Pre-paid credit plans.">
                            <div class="space-y-2 mt-2 text-xs">
                                <div
                                    v-for="pkg in clinicPackages"
                                    :key="pkg.name"
                                    class="p-2.5 bg-[#fbfbfa] rounded border border-[#dededb] flex items-center justify-between"
                                >
                                    <div>
                                        <span class="font-medium text-[#1d1d1a] block">{{ pkg.name }}</span>
                                        <span class="text-[10px] text-[#73736c]">Validity: {{ pkg.validity }}</span>
                                    </div>
                                    <span class="font-mono font-semibold text-emerald-700">{{ pkg.price }}</span>
                                </div>
                            </div>
                        </UiCard>

                        <UiCard id="clinic-insurances-card" title="Health Insurance Agreements" description="Accepted health providers.">
                            <div class="space-y-2 mt-2 text-xs">
                                <div
                                    v-for="ins in clinicInsurances"
                                    :key="ins.name"
                                    class="p-2.5 bg-[#fbfbfa] rounded border border-[#dededb] flex items-center justify-between"
                                >
                                    <div>
                                        <span class="font-medium text-[#1d1d1a] block">{{ ins.name }}</span>
                                        <span class="text-[10px] text-[#73736c]">ANS: {{ ins.ans }}</span>
                                    </div>
                                    <UiBadge variant="info">{{ ins.status }}</UiBadge>
                                </div>
                            </div>
                        </UiCard>
                    </div>
                </div>

                <!-- ============================================== -->
                <!-- 4. SUPER ADMIN (CENTRAL PLATFORM) WORKSPACE    -->
                <!-- ============================================== -->
                <div v-else-if="activeRole === 'super-admin'" id="super-admin-workspace" class="space-y-6 max-w-4xl">
                    <UiCard
                        id="super-admin-tenants-card"
                        title="Central Multi-Tenant Registry"
                        description="PostgreSQL isolated tenant databases provisioned by Stancl/Tenancy."
                    >
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-xs font-medium text-[#73736c]">Active Clinics: {{ centralTenants.length }}</span>
                            <UiButton
                                id="btn-open-provision-modal"
                                variant="primary"
                                size="sm"
                                icon="lucide:plus"
                                @click="isProvisionModalOpen = true"
                            >
                                Provision New Clinic
                            </UiButton>
                        </div>

                        <div class="space-y-2 text-xs">
                            <div
                                v-for="tenant in centralTenants"
                                :key="tenant.id"
                                class="p-3 bg-[#fbfbfa] rounded border border-[#dededb] flex items-center justify-between"
                            >
                                <div>
                                    <span class="font-semibold text-[#1d1d1a] block">{{ tenant.name }}</span>
                                    <span class="font-mono text-[10px] text-[#73736c]">
                                        Slug: {{ tenant.slug }} • Schema: {{ tenant.schema }} • Timezone: {{ tenant.timezone }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <UiBadge variant="success">{{ tenant.status }}</UiBadge>
                                </div>
                            </div>
                        </div>
                    </UiCard>

                    <!-- Central Audit Logs Table -->
                    <UiCard
                        id="central-audit-logs-card"
                        title="Central Audit Logs (central_audit_logs)"
                        description="Security and administrative audit trail recorded in public schema."
                    >
                        <div class="divide-y divide-[#dededb] mt-2 text-xs">
                            <div
                                v-for="log in centralAuditLogs"
                                :key="log.id"
                                class="py-2.5 flex items-center justify-between"
                            >
                                <div class="flex items-center gap-2.5">
                                    <Icon icon="lucide:shield-check" class="w-4 h-4 text-purple-600" />
                                    <div>
                                        <span class="font-mono font-semibold text-[#1d1d1a]">{{ log.action }}</span>
                                        <span class="text-[#73736c] block text-[11px]">{{ log.target }} • Actor: {{ log.actor }}</span>
                                    </div>
                                </div>
                                <span class="font-mono text-[10px] text-[#73736c]">{{ log.time }}</span>
                            </div>
                        </div>
                    </UiCard>
                </div>
            </main>
        </div>

        <!-- Provision New Tenant Modal -->
        <UiModal
            :is-open="isProvisionModalOpen"
            title="Provision New Clinic Tenant"
            description="Creates tenant and executes isolated schema migrations."
            @close="isProvisionModalOpen = false"
        >
            <form @submit.prevent="handleProvisionTenant" class="space-y-3 text-xs">
                <div v-if="provisionSuccessMessage" class="p-2.5 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded">
                    {{ provisionSuccessMessage }}
                </div>

                <UiInput
                    id="input-new-tenant-name"
                    v-model="newTenantName"
                    label="Clinic Legal Name"
                    placeholder="e.g. Clinica Bella Vista"
                    required
                />

                <UiInput
                    id="input-new-tenant-slug"
                    v-model="newTenantSlug"
                    label="Subdomain / Slug"
                    placeholder="e.g. bella-vista"
                />

                <div class="pt-3 border-t border-[#dededb] flex justify-end gap-2">
                    <UiButton variant="secondary" size="sm" type="button" @click="isProvisionModalOpen = false">
                        Cancel
                    </UiButton>
                    <UiButton id="btn-submit-provision-tenant" variant="primary" size="sm" type="submit" icon="lucide:check">
                        Provision Schema
                    </UiButton>
                </div>
            </form>
        </UiModal>

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
                    id="input-booking-patient-name"
                    v-model="newBooking.patientName"
                    label="Patient Full Name"
                    placeholder="e.g. Lucas Silveira"
                    required
                />

                <UiInput
                    id="input-booking-patient-phone"
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
                    <UiButton id="btn-submit-booking" variant="primary" size="sm" type="submit" icon="lucide:check">
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
