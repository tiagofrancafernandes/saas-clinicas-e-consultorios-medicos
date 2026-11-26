<script setup lang="ts">
import { ref } from 'vue';
import { Icon } from '@iconify/vue';
import { UiButton, UiBadge, UiCard, UiInput } from '@saas/ui';

// View modes: 'action-token' (from SMS/WhatsApp link) or 'portal-login' (OTP login) or 'patient-dashboard'
const activeView = ref<'action-token' | 'otp-login' | 'patient-dashboard'>('action-token');

// Fast Action Token State
const tokenData = ref({
    token: 'sec_tok_99fa12b8478cd39',
    clinicName: 'Clinica Santa Monica',
    doctorName: 'Dr. James Wilson',
    specialty: 'Neurology',
    dateFormatted: 'Thursday, October 15, 2026',
    timeFormatted: '14:00 (UTC-3)',
    status: 'PENDING' as 'PENDING' | 'CONFIRMED' | 'CANCELLED' | 'ARRIVED',
    message: '',
});

function handleTokenConfirm(): void {
    tokenData.value.status = 'CONFIRMED';
    tokenData.value.message =
        'Your consultation has been confirmed! We sent instructions to your WhatsApp.';
}

function handleTokenCancel(): void {
    tokenData.value.status = 'CANCELLED';
    tokenData.value.message =
        'Appointment cancelled. Your slot was returned to the clinic and waitlist notified.';
}

function handleTokenCheckin(): void {
    tokenData.value.status = 'ARRIVED';
    tokenData.value.message =
        'Reception alerted! Dr. James Wilson has been notified that you have arrived.';
}

// OTP Passwordless Login State
const phoneInput = ref('+55 11 98877-6655');
const otpCodeInput = ref('');
const otpStep = ref<'phone' | 'verify'>('phone');
const otpErrorMessage = ref('');
const generatedDemoCode = ref('849201');

const patientSubTab = ref<'appointments' | 'records' | 'files' | 'clinic'>('appointments');

function handleRequestOtp(): void {
    if (!phoneInput.value) {
        otpErrorMessage.value = 'Please enter a valid phone number.';
        return;
    }
    otpErrorMessage.value = '';
    otpStep.value = 'verify';
}

function handleVerifyOtp(): void {
    if (otpCodeInput.value !== generatedDemoCode.value && otpCodeInput.value !== '123456') {
        otpErrorMessage.value = 'Invalid OTP code. Please try again.';
        return;
    }
    otpErrorMessage.value = '';
    activeView.value = 'patient-dashboard';
}

function handleLogout(): void {
    activeView.value = 'otp-login';
    otpStep.value = 'phone';
    otpCodeInput.value = '';
}
</script>

<template>
    <div class="min-h-screen flex flex-col bg-[#f8f8f7] text-[#1d1d1a]">
        <!-- Patient Portal Top Bar -->
        <header
            class="h-14 border-b border-[#dededb] bg-white px-6 flex items-center justify-between"
        >
            <div class="flex items-center gap-2.5">
                <div
                    class="w-7 h-7 rounded-md bg-emerald-600 flex items-center justify-center text-white"
                >
                    <Icon icon="lucide:heart-pulse" class="w-4 h-4" />
                </div>
                <div>
                    <span class="text-xs font-semibold tracking-tight text-[#1d1d1a]"
                        >Patient Care Portal</span
                    >
                    <span class="text-[10px] text-[#73736c] block">{{ tokenData.clinicName }}</span>
                </div>
            </div>

            <!-- View Switcher -->
            <div class="flex items-center gap-2">
                <button
                    id="btn-nav-action-token"
                    type="button"
                    @click="activeView = 'action-token'"
                    class="px-2.5 py-1 text-xs rounded transition-colors"
                    :class="[
                        activeView === 'action-token'
                            ? 'bg-[#1d1d1a] text-white font-medium'
                            : 'text-[#73736c] hover:bg-[#f3f3f0]',
                    ]"
                >
                    Fast Action Link
                </button>

                <button
                    id="btn-nav-otp-login"
                    type="button"
                    @click="activeView = 'otp-login'"
                    class="px-2.5 py-1 text-xs rounded transition-colors"
                    :class="[
                        activeView === 'otp-login' || activeView === 'patient-dashboard'
                            ? 'bg-[#1d1d1a] text-white font-medium'
                            : 'text-[#73736c] hover:bg-[#f3f3f0]',
                    ]"
                >
                    Patient Area (OTP)
                </button>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 p-6 max-w-xl mx-auto w-full flex flex-col justify-center">
            <!-- 1. FAST ACTION TOKEN VIEW (1-Click Confirmation) -->
            <section v-if="activeView === 'action-token'" class="space-y-4">
                <UiCard>
                    <div class="text-center pb-4 border-b border-[#dededb]">
                        <div
                            class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-2 border border-emerald-200"
                        >
                            <Icon icon="lucide:calendar-check" class="w-5 h-5" />
                        </div>
                        <h1 class="text-sm font-semibold text-[#1d1d1a]">
                            Appointment Confirmation
                        </h1>
                        <p class="text-xs text-[#73736c] mt-0.5">
                            Passwordless fast action secure token link
                        </p>
                    </div>

                    <!-- Status Notification Banner -->
                    <div
                        v-if="tokenData.message"
                        class="mt-4 p-3 rounded text-xs border"
                        :class="[
                            tokenData.status === 'CONFIRMED' || tokenData.status === 'ARRIVED'
                                ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                : 'bg-rose-50 text-rose-800 border-rose-200',
                        ]"
                    >
                        {{ tokenData.message }}
                    </div>

                    <!-- Appointment Summary -->
                    <div
                        class="mt-4 p-3.5 bg-[#fbfbfa] rounded border border-[#dededb] space-y-2.5 text-xs"
                    >
                        <div class="flex justify-between items-center">
                            <span class="text-[#73736c]">Doctor:</span>
                            <span class="font-medium text-[#1d1d1a]">{{
                                tokenData.doctorName
                            }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-[#73736c]">Specialty:</span>
                            <span class="text-[#1d1d1a]">{{ tokenData.specialty }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-[#73736c]">Date:</span>
                            <span class="font-medium text-[#1d1d1a]">{{
                                tokenData.dateFormatted
                            }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-[#73736c]">Scheduled Time:</span>
                            <span class="font-semibold text-emerald-700 font-mono">{{
                                tokenData.timeFormatted
                            }}</span>
                        </div>
                        <div
                            class="flex justify-between items-center pt-2 border-t border-[#dededb]"
                        >
                            <span class="text-[#73736c]">Current Status:</span>
                            <UiBadge
                                :variant="
                                    tokenData.status === 'CONFIRMED'
                                        ? 'success'
                                        : tokenData.status === 'CANCELLED'
                                          ? 'danger'
                                          : 'warning'
                                "
                            >
                                {{
                                    tokenData.status === 'CONFIRMED'
                                        ? 'Confirmed'
                                        : tokenData.status === 'CANCELLED'
                                          ? 'Cancelled'
                                          : 'Pending Confirmation'
                                }}
                            </UiBadge>
                        </div>
                    </div>

                    <!-- Fast Action Buttons -->
                    <div v-if="tokenData.status === 'PENDING'" class="mt-5 flex gap-2">
                        <UiButton
                            id="btn-cancel-attendance"
                            variant="secondary"
                            size="sm"
                            class="flex-1 text-rose-600 hover:text-rose-700"
                            icon="lucide:calendar-x"
                            @click="handleTokenCancel"
                        >
                            Cancel Appointment
                        </UiButton>
                        <UiButton
                            id="btn-confirm-attendance"
                            variant="primary"
                            size="sm"
                            class="flex-1"
                            icon="lucide:check"
                            @click="handleTokenConfirm"
                        >
                            Confirm Attendance
                        </UiButton>
                    </div>

                    <div v-else-if="tokenData.status === 'CONFIRMED'" class="mt-5">
                        <UiButton
                            id="btn-checkin-arrival"
                            variant="primary"
                            size="sm"
                            class="w-full"
                            icon="lucide:map-pin"
                            @click="handleTokenCheckin"
                        >
                            I'm at the Clinic (Check-in Now)
                        </UiButton>
                    </div>
                </UiCard>
            </section>

            <!-- 2. OTP PASSWORDLESS LOGIN VIEW -->
            <section v-else-if="activeView === 'otp-login'" class="space-y-4">
                <UiCard>
                    <div class="text-center pb-4 border-b border-[#dededb]">
                        <div
                            class="w-10 h-10 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-2 border border-blue-200"
                        >
                            <Icon icon="lucide:shield-check" class="w-5 h-5" />
                        </div>
                        <h1 class="text-sm font-semibold text-[#1d1d1a]">Patient Secure Access</h1>
                        <p class="text-xs text-[#73736c] mt-0.5">
                            Access your medical records and appointments without passwords.
                        </p>
                    </div>

                    <div
                        v-if="otpErrorMessage"
                        class="mt-4 p-2.5 bg-rose-50 text-rose-800 border border-rose-200 rounded text-xs"
                    >
                        {{ otpErrorMessage }}
                    </div>

                    <!-- Step 1: Phone Request -->
                    <div v-if="otpStep === 'phone'" class="mt-4 space-y-4">
                        <UiInput
                            id="input-phone"
                            v-model="phoneInput"
                            label="Your Phone Number"
                            placeholder="+55 11 99999-0000"
                            required
                        />

                        <UiButton
                            id="btn-send-otp"
                            variant="primary"
                            size="sm"
                            class="w-full"
                            icon="lucide:send"
                            @click="handleRequestOtp"
                        >
                            Send Verification Code
                        </UiButton>
                    </div>

                    <!-- Step 2: Code Verification -->
                    <div v-else class="mt-4 space-y-4">
                        <div
                            class="p-2.5 bg-amber-50 border border-amber-200 rounded text-xs text-amber-900"
                        >
                            Demo OTP code sent:
                            <strong class="font-mono" id="demo-otp-code">{{ generatedDemoCode }}</strong>
                        </div>

                        <UiInput
                            id="input-otp"
                            v-model="otpCodeInput"
                            label="6-Digit Verification Code"
                            placeholder="e.g. 849201"
                            required
                        />

                        <div class="flex gap-2">
                            <UiButton
                                id="btn-back-phone"
                                variant="secondary"
                                size="sm"
                                class="flex-1"
                                @click="otpStep = 'phone'"
                            >
                                Change Phone
                            </UiButton>
                            <UiButton
                                id="btn-verify-otp"
                                variant="primary"
                                size="sm"
                                class="flex-1"
                                icon="lucide:arrow-right"
                                @click="handleVerifyOtp"
                            >
                                Verify & Sign In
                            </UiButton>
                        </div>
                    </div>
                </UiCard>
            </section>

            <!-- 3. PATIENT DASHBOARD (AUTHENTICATED) -->
            <section v-else-if="activeView === 'patient-dashboard'" class="space-y-4 max-w-2xl w-full mx-auto">
                <UiCard>
                    <div class="flex items-center justify-between pb-4 border-b border-[#dededb]">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-sm">
                                CS
                            </div>
                            <div>
                                <h2 class="text-sm font-semibold text-[#1d1d1a]" id="patient-name-header">
                                    Carlos Silva
                                </h2>
                                <p class="text-xs text-[#73736c]">
                                    Tax ID (CPF): ***.456.789-** • Patient Portal Logged In
                                </p>
                            </div>
                        </div>
                        <UiButton
                            id="btn-patient-logout"
                            variant="secondary"
                            size="sm"
                            icon="lucide:log-out"
                            @click="handleLogout"
                        >
                            Sign Out
                        </UiButton>
                    </div>

                    <!-- Navigation Sub-tabs -->
                    <div class="flex border-b border-[#dededb] mt-3 gap-4 text-xs">
                        <button
                            type="button"
                            id="tab-patient-appointments"
                            class="pb-2 font-medium border-b-2 transition-colors"
                            :class="[patientSubTab === 'appointments' ? 'border-[#1d1d1a] text-[#1d1d1a]' : 'border-transparent text-[#73736c] hover:text-[#1d1d1a]']"
                            @click="patientSubTab = 'appointments'"
                        >
                            My Appointments
                        </button>
                        <button
                            type="button"
                            id="tab-patient-records"
                            class="pb-2 font-medium border-b-2 transition-colors"
                            :class="[patientSubTab === 'records' ? 'border-[#1d1d1a] text-[#1d1d1a]' : 'border-transparent text-[#73736c] hover:text-[#1d1d1a]']"
                            @click="patientSubTab = 'records'"
                        >
                            Clinical Notes & Prescriptions
                        </button>
                        <button
                            type="button"
                            id="tab-patient-files"
                            class="pb-2 font-medium border-b-2 transition-colors"
                            :class="[patientSubTab === 'files' ? 'border-[#1d1d1a] text-[#1d1d1a]' : 'border-transparent text-[#73736c] hover:text-[#1d1d1a]']"
                            @click="patientSubTab = 'files'"
                        >
                            Exams & Attachments
                        </button>
                        <button
                            type="button"
                            id="tab-patient-clinic"
                            class="pb-2 font-medium border-b-2 transition-colors"
                            :class="[patientSubTab === 'clinic' ? 'border-[#1d1d1a] text-[#1d1d1a]' : 'border-transparent text-[#73736c] hover:text-[#1d1d1a]']"
                            @click="patientSubTab = 'clinic'"
                        >
                            Clinic Info
                        </button>
                    </div>

                    <!-- Sub-tab 1: Appointments -->
                    <div v-if="patientSubTab === 'appointments'" class="mt-4 space-y-3" id="patient-appointments-panel">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-semibold text-[#1d1d1a]">Upcoming Consultations</span>
                            <UiBadge variant="success">1 Active</UiBadge>
                        </div>
                        <div class="p-3 bg-[#fbfbfa] rounded border border-[#dededb] flex items-center justify-between">
                            <div>
                                <span class="text-xs font-medium text-[#1d1d1a] block">Dr. James Wilson (Neurology)</span>
                                <span class="text-[11px] text-[#73736c]">Room 102 - Neurology Suite • October 15, 2026 at 14:00 (UTC-3)</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <UiBadge variant="success">Confirmed</UiBadge>
                            </div>
                        </div>

                        <div class="mt-4">
                            <span class="text-xs font-semibold text-[#1d1d1a] block mb-2">Package Balance</span>
                            <div class="p-3 bg-[#fbfbfa] rounded border border-[#dededb] flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-medium text-[#1d1d1a] block">Physical Therapy (10 Sessions)</span>
                                    <span class="text-[11px] text-[#73736c]">Valid until December 2026</span>
                                </div>
                                <span class="font-mono text-xs font-semibold text-emerald-700">7 credits remaining</span>
                            </div>
                        </div>
                    </div>

                    <!-- Sub-tab 2: Records & Prescriptions -->
                    <div v-else-if="patientSubTab === 'records'" class="mt-4 space-y-3" id="patient-records-panel">
                        <span class="text-xs font-semibold text-[#1d1d1a] block">Shared Clinical Prescriptions</span>
                        <div class="p-3 bg-[#fbfbfa] rounded border border-[#dededb] space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-semibold text-[#1d1d1a]">Prophylactic Migraine Prescription</span>
                                <span class="text-[10px] text-[#73736c]">2026-10-14</span>
                            </div>
                            <p class="text-xs text-[#52524e]">
                                Continuous oral medication prescribed by Dr. James Wilson. Patient advised to avoid prolonged screen exposure after 20:00.
                            </p>
                            <div class="text-[10px] text-emerald-700 font-medium">
                                Validated with digital CRM/SP 123456 signature.
                            </div>
                        </div>
                    </div>

                    <!-- Sub-tab 3: Exams & Files -->
                    <div v-else-if="patientSubTab === 'files'" class="mt-4 space-y-3" id="patient-files-panel">
                        <span class="text-xs font-semibold text-[#1d1d1a] block">Uploaded Exam Reports</span>
                        <div class="p-3 bg-[#fbfbfa] rounded border border-[#dededb] flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <Icon icon="lucide:file-text" class="w-5 h-5 text-blue-600" />
                                <div>
                                    <span class="text-xs font-medium text-[#1d1d1a] block">mri_brain_carlos_silva.pdf</span>
                                    <span class="text-[10px] text-[#73736c]">Magnetic Resonance • 2.0 MB • Uploaded on 2026-10-10</span>
                                </div>
                            </div>
                            <UiBadge variant="default">Available</UiBadge>
                        </div>
                    </div>

                    <!-- Sub-tab 4: Clinic Info -->
                    <div v-else-if="patientSubTab === 'clinic'" class="mt-4 space-y-3 text-xs" id="patient-clinic-panel">
                        <div class="p-3 bg-[#fbfbfa] rounded border border-[#dededb] space-y-2">
                            <span class="font-semibold text-[#1d1d1a] block">Clinica Santa Monica</span>
                            <div class="flex items-center gap-2 text-[#73736c]">
                                <Icon icon="lucide:map-pin" class="w-4 h-4 text-emerald-600" />
                                <span>Av. Paulista, 1000 - Bela Vista, São Paulo - SP</span>
                            </div>
                            <div class="flex items-center gap-2 text-[#73736c]">
                                <Icon icon="lucide:phone" class="w-4 h-4 text-emerald-600" />
                                <span>(11) 3344-5566 / WhatsApp: (11) 98877-6655</span>
                            </div>
                            <div class="flex items-center gap-2 text-[#73736c]">
                                <Icon icon="lucide:clock" class="w-4 h-4 text-emerald-600" />
                                <span>Monday to Friday: 08:00 - 18:00</span>
                            </div>
                        </div>
                    </div>
                </UiCard>
            </section>
        </main>
    </div>
</template>
