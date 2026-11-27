<script setup lang="ts">
import { toast } from 'vue3-toastify';
import type { AppointmentModalInitialData } from '~/components/appointments/AppointmentBookingModal.vue';
import type { TypeaheadOption } from '~/components/ui/UiTypeaheadSelect.vue';
import { useAuthStore } from '~/stores/auth';
import { apiRequest } from '~/utils/api';
import { formatUtcInTimezone, getDatePartsInTimezone } from '~/utils/timezone';

definePageMeta({ permission: 'appointments.view' });

type CalendarView = 'day' | 'week' | 'month';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const clinicTimezone = ref('America/Sao_Paulo');
const todayDateStr = computed(() => getDatePartsInTimezone(new Date(), clinicTimezone.value).date);
const viewMode = ref<CalendarView>(
    ['day', 'week', 'month'].includes(String(route.query.view)) ? (String(route.query.view) as CalendarView) : 'day',
);
const currentDate = ref<string>(
    typeof route.query.date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(route.query.date)
        ? route.query.date
        : getDatePartsInTimezone(new Date(), clinicTimezone.value).date,
);
const selectedProfessionalId = ref<string>(
    typeof route.query.professional_id === 'string' ? route.query.professional_id : '',
);
const selectedPatientId = ref<string>(
    typeof route.query.patient_id === 'string' ? route.query.patient_id : '',
);
const selectedStatus = ref<string>('');

const professionals = ref<Array<{ id: string; name: string; specialty?: string }>>([]);
const rooms = ref<Array<{ id: string; name: string }>>([]);
const appointments = ref<any[]>([]);
const unavailabilities = ref<any[]>([]);
const daySlots = ref<Array<{ start_time_utc: string; end_time_utc: string }>>([]);
const visibleDaySlots = computed(() => {
    if (currentDate.value !== todayDateStr.value) {
        return daySlots.value;
    }
    const nowMs = Date.now();
    return daySlots.value.filter((slot) => new Date(slot.start_time_utc).getTime() > nowMs);
});
const loading = ref(false);

const bookingModalOpen = ref(false);
const bookingInitialData = ref<AppointmentModalInitialData | null>(null);
const blockModalOpen = ref(false);
const cancelTarget = ref<any | null>(null);
const confirmTarget = ref<any | null>(null);
const notifyTarget = ref<any | null>(null);
const detailTarget = ref<any | null>(null);
const confirmingAppointment = ref(false);
const removeBlockTarget = ref<any | null>(null);

function addDays(dateStr: string, delta: number): string {
    const [y, m, d] = dateStr.split('-').map(Number);
    const dt = new Date(Date.UTC(y!, m! - 1, d! + delta));
    return dt.toISOString().slice(0, 10);
}

function addMonths(dateStr: string, delta: number): string {
    const [y, m, d] = dateStr.split('-').map(Number);
    const dt = new Date(Date.UTC(y!, m! - 1 + delta, Math.min(d!, 28)));
    return dt.toISOString().slice(0, 10);
}

const dateRange = computed<{ start: string; end: string }>(() => {
    if (viewMode.value === 'day') {
        return { start: currentDate.value, end: currentDate.value };
    }
    if (viewMode.value === 'week') {
        const [y, m, d] = currentDate.value.split('-').map(Number);
        const dt = new Date(Date.UTC(y!, m! - 1, d!));
        const dow = dt.getUTCDay();
        const start = addDays(currentDate.value, -dow);
        return { start, end: addDays(start, 6) };
    }
    const [y, m] = currentDate.value.split('-').map(Number);
    const firstOfMonth = `${y}-${String(m).padStart(2, '0')}-01`;
    const lastDay = new Date(Date.UTC(y!, m!, 0)).getUTCDate();
    const lastOfMonth = `${y}-${String(m).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
    return { start: firstOfMonth, end: lastOfMonth };
});

const weekDays = computed(() => {
    const days: string[] = [];
    for (let i = 0; i < 7; i++) {
        days.push(addDays(dateRange.value.start, i));
    }
    return days;
});

const monthCells = computed(() => {
    const [y, m] = currentDate.value.split('-').map(Number);
    const firstDt = new Date(Date.UTC(y!, m! - 1, 1));
    const startOffset = firstDt.getUTCDay();
    const totalDays = new Date(Date.UTC(y!, m!, 0)).getUTCDate();
    const cells: Array<{ date: string | null; dayNumber: number | null }> = [];
    for (let i = 0; i < startOffset; i++) {
        cells.push({ date: null, dayNumber: null });
    }
    for (let day = 1; day <= totalDays; day++) {
        cells.push({
            date: `${y}-${String(m).padStart(2, '0')}-${String(day).padStart(2, '0')}`,
            dayNumber: day,
        });
    }
    return cells;
});

const periodLabel = computed(() => {
    const [y, m, d] = currentDate.value.split('-').map(Number);
    const dt = new Date(Date.UTC(y!, m! - 1, d!));
    if (viewMode.value === 'day') {
        return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'full', timeZone: 'UTC' }).format(dt);
    }
    if (viewMode.value === 'week') {
        return `${dateRange.value.start.split('-').reverse().join('/')} – ${dateRange.value.end.split('-').reverse().join('/')}`;
    }
    return new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(dt);
});

function syncUrl(): void {
    router.replace({
        query: {
            ...route.query,
            view: viewMode.value,
            date: currentDate.value,
            ...(selectedProfessionalId.value ? { professional_id: selectedProfessionalId.value } : { professional_id: undefined }),
            ...(selectedPatientId.value ? { patient_id: selectedPatientId.value } : { patient_id: undefined }),
        },
    });
}

async function searchPatients(searchQuery: string): Promise<TypeaheadOption[]> {
    if (!auth.tenantId) return [];
    const params = new URLSearchParams({ limit: '20' });
    if (searchQuery) params.set('search', searchQuery);
    const res = await apiRequest<{ data: Array<{ id: string; name: string; phone?: string; email?: string }> }>(
        `/api/tenant/patients?${params.toString()}`,
        { tenantId: auth.tenantId },
    );
    return (res.data ?? []).map((p) => ({
        value: p.id,
        label: p.name,
        description: p.phone || p.email || undefined,
    }));
}

function navigatePeriod(direction: -1 | 0 | 1): void {
    if (direction === 0) {
        currentDate.value = getDatePartsInTimezone(new Date(), clinicTimezone.value).date;
    } else if (viewMode.value === 'day') {
        currentDate.value = addDays(currentDate.value, direction);
    } else if (viewMode.value === 'week') {
        currentDate.value = addDays(currentDate.value, direction * 7);
    } else {
        currentDate.value = addMonths(currentDate.value, direction);
    }
    syncUrl();
}

function switchView(mode: CalendarView): void {
    viewMode.value = mode;
    syncUrl();
}

function openDay(dateStr: string): void {
    currentDate.value = dateStr;
    viewMode.value = 'day';
    syncUrl();
}

async function loadMetadata(): Promise<void> {
    if (!auth.tenantId) return;
    try {
        const [clinicRes, profRes, roomRes] = await Promise.all([
            apiRequest<{ data: { timezone?: string } }>('/api/tenant/clinic', { tenantId: auth.tenantId }),
            apiRequest<{ data: Array<{ id: string; name: string; specialty?: string }> }>('/api/tenant/professionals', {
                tenantId: auth.tenantId,
            }),
            apiRequest<{ data: Array<{ id: string; name: string }> }>('/api/tenant/clinic-rooms', {
                tenantId: auth.tenantId,
            }),
        ]);
        if (clinicRes.data?.timezone) {
            clinicTimezone.value = clinicRes.data.timezone;
        }
        professionals.value = profRes.data ?? [];
        rooms.value = roomRes.data ?? [];
    } catch {
        // Ignore on transient error
    }
}

async function loadSchedule(): Promise<void> {
    if (!auth.tenantId) return;
    loading.value = true;
    try {
        const apptParams = new URLSearchParams({
            start_date: dateRange.value.start,
            end_date: dateRange.value.end,
            timezone: clinicTimezone.value,
            per_page: '200',
        });
        if (selectedProfessionalId.value) apptParams.set('professional_id', selectedProfessionalId.value);
        if (selectedPatientId.value) apptParams.set('patient_id', selectedPatientId.value);
        if (selectedStatus.value) apptParams.set('status', selectedStatus.value);

        const blockParams = new URLSearchParams({
            start_date: dateRange.value.start,
            end_date: dateRange.value.end,
            timezone: clinicTimezone.value,
        });
        if (selectedProfessionalId.value) blockParams.set('professional_id', selectedProfessionalId.value);

        const requests: Array<Promise<any>> = [
            apiRequest<{ data: any[] }>(`/api/tenant/appointments?${apptParams.toString()}`, {
                tenantId: auth.tenantId,
            }),
            apiRequest<{ data: any[] }>(`/api/tenant/unavailabilities?${blockParams.toString()}`, {
                tenantId: auth.tenantId,
            }),
        ];

        if (viewMode.value === 'day' && selectedProfessionalId.value) {
            const slotParams = new URLSearchParams({
                professional_id: selectedProfessionalId.value,
                date: currentDate.value,
            });
            requests.push(
                apiRequest<{ data: Array<{ start_time_utc: string; end_time_utc: string }> }>(
                    `/api/tenant/availability/slots?${slotParams.toString()}`,
                    { tenantId: auth.tenantId },
                ),
            );
        }

        const [apptRes, blockRes, slotRes] = await Promise.all(requests);
        appointments.value = apptRes?.data ?? [];
        unavailabilities.value = blockRes?.data ?? [];
        daySlots.value = slotRes?.data ?? [];
    } catch {
        if (import.meta.client) {
            toast.error('Erro ao carregar agenda.');
        }
    } finally {
        loading.value = false;
    }
}

watch(
    () => auth.tenantId,
    async (id) => {
        if (id) {
            await loadMetadata();
            await loadSchedule();
        }
    },
    { immediate: true },
);

watch([viewMode, currentDate, selectedProfessionalId, selectedPatientId, selectedStatus], () => {
    syncUrl();
    if (auth.tenantId) {
        loadSchedule();
    }
});

function appointmentsOnDate(dateStr: string): any[] {
    return appointments.value.filter(
        (apt) => getDatePartsInTimezone(new Date(apt.start_time_utc), clinicTimezone.value).date === dateStr,
    );
}

function blocksOnDate(dateStr: string): any[] {
    return unavailabilities.value.filter((blk) => {
        const startDate = getDatePartsInTimezone(new Date(blk.start_time_utc), clinicTimezone.value).date;
        const endDate = getDatePartsInTimezone(new Date(blk.end_time_utc), clinicTimezone.value).date;
        return dateStr >= startDate && dateStr <= endDate;
    });
}

function openCreateModal(prefill?: Partial<AppointmentModalInitialData>): void {
    bookingInitialData.value = {
        mode: 'create',
        professionalId: selectedProfessionalId.value || undefined,
        date: currentDate.value,
        ...prefill,
    };
    bookingModalOpen.value = true;
}

function openSlotBooking(slot: { start_time_utc: string; end_time_utc: string }): void {
    const parts = getDatePartsInTimezone(new Date(slot.start_time_utc), clinicTimezone.value);
    openCreateModal({
        professionalId: selectedProfessionalId.value,
        date: parts.date,
        startTime: parts.time,
    });
}

function openRescheduleModal(apt: any): void {
    const parts = getDatePartsInTimezone(new Date(apt.start_time_utc), apt.origin_timezone || clinicTimezone.value);
    bookingInitialData.value = {
        mode: 'reschedule',
        appointmentId: apt.id,
        patientId: apt.patient_id,
        patientName: apt.patient?.name,
        professionalId: apt.professional_id,
        clinicRoomId: apt.clinic_room_id,
        date: parts.date,
        startTime: parts.time,
    };
    bookingModalOpen.value = true;
}

async function confirmAppointment(): Promise<void> {
    if (!auth.tenantId || !confirmTarget.value) return;
    confirmingAppointment.value = true;
    try {
        await apiRequest(`/api/tenant/appointments/${confirmTarget.value.id}/confirm`, {
            method: 'POST',
            tenantId: auth.tenantId,
        });
        toast.success('Agendamento confirmado.');
        confirmTarget.value = null;
        await loadSchedule();
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao confirmar agendamento.');
    } finally {
        confirmingAppointment.value = false;
    }
}

async function summonPatient(apt: any): Promise<void> {
    if (!auth.tenantId) return;
    try {
        await apiRequest(`/api/tenant/appointments/${apt.id}/summon`, {
            method: 'POST',
            tenantId: auth.tenantId,
            body: { room_name: apt.clinic_room?.name || 'Consultório' },
        });
        toast.info(`Paciente ${apt.patient?.name || ''} chamado.`);
        await loadSchedule();
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao chamar paciente.');
    }
}

async function removeBlock(): Promise<void> {
    if (!auth.tenantId || !removeBlockTarget.value) return;
    try {
        await apiRequest(`/api/tenant/unavailabilities/${removeBlockTarget.value.id}`, {
            method: 'DELETE',
            tenantId: auth.tenantId,
        });
        toast.success('Bloqueio removido.');
        removeBlockTarget.value = null;
        await loadSchedule();
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao remover bloqueio.');
    }
}

function statusBadgeClass(status: string): string {
    if (status === 'CONFIRMED') return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    if (status === 'SCHEDULED') return 'border-teal-200 bg-teal-50 text-teal-700';
    if (status === 'IN_CONSULTATION') return 'border-violet-200 bg-violet-50 text-violet-700';
    if (status === 'CANCELLED') return 'border-rose-200 bg-rose-50 text-rose-700';
    return 'border-gray-200 bg-gray-50 text-gray-600';
}
</script>

<template>
    <div class="space-y-4">
        <!-- Header e Actions -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-[18px] font-normal text-gray-900">Agenda e Agendamentos</h1>
                <p class="text-[12px] text-gray-500">
                    Fuso horário da clínica: <span class="font-mono text-gray-700">{{ clinicTimezone }}</span>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-if="auth.can('appointments.create')"
                    type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded border border-amber-200 bg-amber-50 px-3 text-[12px] text-amber-800 hover:bg-amber-100"
                    @click="blockModalOpen = true"
                >
                    <Icon name="lucide:calendar-off" class="size-3.5" />
                    <span>Bloquear agenda</span>
                </button>
                <button
                    v-if="auth.can('appointments.create')"
                    type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded bg-teal-600 px-3 text-[12px] text-white hover:bg-teal-700"
                    @click="openCreateModal()"
                >
                    <Icon name="lucide:plus" class="size-3.5" />
                    <span>Novo agendamento</span>
                </button>
            </div>
        </div>

        <!-- Calendar Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-3 rounded border border-gray-200 bg-white p-3">
            <div class="flex items-center gap-2">
                <div class="inline-flex rounded border border-gray-200 bg-gray-50 p-0.5 text-[11px]">
                    <button
                        v-for="mode in (['day', 'week', 'month'] as const)"
                        :key="mode"
                        type="button"
                        class="rounded px-2.5 py-1 capitalize transition-colors"
                        :class="viewMode === mode ? 'bg-white text-gray-900 shadow-xs' : 'text-gray-500 hover:text-gray-800'"
                        @click="switchView(mode)"
                    >
                        {{ mode === 'day' ? 'Dia' : mode === 'week' ? 'Semana' : 'Mês' }}
                    </button>
                </div>

                <div class="flex items-center gap-1">
                    <button type="button" class="inline-flex size-7 items-center justify-center rounded border border-gray-200 text-gray-600 hover:bg-gray-50" @click="navigatePeriod(-1)">
                        <Icon name="lucide:chevron-left" class="size-3.5" />
                    </button>
                    <button type="button" class="h-7 rounded border border-gray-200 px-2.5 text-[11px] text-gray-700 hover:bg-gray-50" @click="navigatePeriod(0)">
                        Hoje
                    </button>
                    <button type="button" class="inline-flex size-7 items-center justify-center rounded border border-gray-200 text-gray-600 hover:bg-gray-50" @click="navigatePeriod(1)">
                        <Icon name="lucide:chevron-right" class="size-3.5" />
                    </button>
                </div>

                <span class="text-[13px] text-gray-800">{{ periodLabel }}</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="w-56">
                    <UiTypeaheadSelect
                        :model-value="selectedProfessionalId || null"
                        :options="professionals.map((prof) => ({ value: prof.id, label: prof.name, description: prof.specialty || undefined }))"
                        placeholder="Todos os profissionais"
                        empty-text="Nenhum profissional encontrado."
                        @update:model-value="selectedProfessionalId = $event ? String($event) : ''"
                    />
                </div>

                <div class="w-56">
                    <UiTypeaheadSelect
                        :model-value="selectedPatientId || null"
                        :preload="Boolean(auth.tenantId)"
                        :fetch-options="searchPatients"
                        placeholder="Todos os pacientes"
                        empty-text="Nenhum paciente encontrado."
                        @update:model-value="selectedPatientId = $event ? String($event) : ''"
                    />
                </div>

                <select
                    v-model="selectedStatus"
                    class="h-8 rounded border border-gray-200 bg-white px-2.5 text-[12px] text-gray-700"
                >
                    <option value="">Todos os status</option>
                    <option value="SCHEDULED">Agendado</option>
                    <option value="CONFIRMED">Confirmado</option>
                    <option value="IN_CONSULTATION">Em consulta</option>
                    <option value="COMPLETED">Concluído</option>
                    <option value="CANCELLED">Cancelado</option>
                </select>
            </div>
        </div>

        <!-- DAY VIEW -->
        <div v-if="viewMode === 'day'" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <!-- Left 2 cols: Appointments e Blocks -->
            <div class="space-y-3 lg:col-span-2">
                <div v-if="blocksOnDate(currentDate).length > 0" class="space-y-1.5">
                    <div
                        v-for="blk in blocksOnDate(currentDate)"
                        :key="blk.id"
                        class="flex items-center justify-between rounded border border-amber-200 bg-amber-50 px-3 py-2 text-[12px] text-amber-900"
                    >
                        <div class="flex items-center gap-2">
                            <Icon name="lucide:calendar-off" class="size-4 text-amber-700" />
                            <span>
                                <strong>{{ blk.professional?.name || 'Profissional' }}</strong> · Bloqueio: {{ blk.reason }}
                                ({{ formatUtcInTimezone(blk.start_time_utc, clinicTimezone) }} – {{ formatUtcInTimezone(blk.end_time_utc, clinicTimezone) }})
                            </span>
                        </div>
                        <button
                            v-if="auth.can('appointments.cancel')"
                            type="button"
                            class="text-[11px] text-amber-800 underline hover:text-amber-950"
                            @click="removeBlockTarget = blk"
                        >
                            Remover
                        </button>
                    </div>
                </div>

                <div class="rounded border border-gray-200 bg-white p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-[13px] font-normal text-gray-900">Consultas do dia ({{ appointments.length }})</h2>
                        <Icon v-if="loading" name="lucide:loader-2" class="size-3.5 animate-spin text-gray-400" />
                    </div>

                    <div v-if="appointments.length === 0" class="py-8 text-center text-[12px] text-gray-400">
                        Nenhum agendamento encontrado para este dia.
                    </div>

                    <div v-else class="divide-y divide-gray-100">
                        <div
                            v-for="apt in appointments"
                            :key="apt.id"
                            class="flex flex-wrap items-center justify-between gap-2 py-2.5 text-[12px]"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-[12px] text-gray-900">
                                        {{ formatUtcInTimezone(apt.start_time_utc, clinicTimezone) }} – {{ formatUtcInTimezone(apt.end_time_utc, clinicTimezone) }}
                                    </span>
                                    <NuxtLink
                                        v-if="apt.patient_id"
                                        :to="`/patients/${apt.patient_id}`"
                                        class="text-teal-700 hover:underline"
                                    >
                                        {{ apt.patient?.name || 'Paciente' }}
                                    </NuxtLink>
                                    <span class="rounded border px-2 py-0.5 text-[10px]" :class="statusBadgeClass(apt.status)">
                                        {{ apt.status }}
                                    </span>
                                </div>
                                <div class="mt-0.5 text-[11px] text-gray-500">
                                    {{ apt.professional?.name || 'Profissional' }}
                                    <span v-if="apt.clinic_room?.name"> · {{ apt.clinic_room.name }}</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-1" @click.stop>
                                <button
                                    type="button"
                                    class="inline-flex h-7 items-center gap-1 rounded border border-gray-200 bg-white px-2 text-[11px] text-gray-700 hover:bg-gray-50"
                                    @click="detailTarget = apt"
                                >
                                    <Icon name="lucide:panel-right-open" class="size-3" />
                                    <span>Detalhes</span>
                                </button>
                                <button
                                    v-if="apt.status === 'SCHEDULED' && auth.can('appointments.create')"
                                    type="button"
                                    class="h-7 rounded border border-emerald-200 bg-emerald-50 px-2 text-[11px] text-emerald-700 hover:bg-emerald-100"
                                    @click="confirmTarget = apt"
                                >
                                    Confirmar
                                </button>
                                <button
                                    v-if="apt.status !== 'CANCELLED' && apt.status !== 'COMPLETED' && new Date(apt.start_time_utc).getTime() > Date.now()"
                                    type="button"
                                    class="inline-flex h-7 items-center gap-1 rounded border border-blue-200 bg-blue-50 px-2 text-[11px] text-blue-700 hover:bg-blue-100"
                                    @click="notifyTarget = apt"
                                >
                                    <Icon name="lucide:bell" class="size-3" />
                                    <span>Notificar</span>
                                </button>
                                <button
                                    v-if="apt.status !== 'CANCELLED' && apt.status !== 'COMPLETED' && auth.can('appointments.create')"
                                    type="button"
                                    class="h-7 rounded border border-gray-200 bg-white px-2 text-[11px] text-gray-700 hover:bg-gray-50"
                                    @click="openRescheduleModal(apt)"
                                >
                                    Remarcar
                                </button>
                                <button
                                    v-if="apt.status !== 'CANCELLED' && auth.can('appointments.summon')"
                                    type="button"
                                    title="Chamar à sala"
                                    class="inline-flex size-7 items-center justify-center rounded border border-gray-200 text-gray-500 hover:bg-teal-50 hover:text-teal-600"
                                    @click="summonPatient(apt)"
                                >
                                    <Icon name="lucide:volume-2" class="size-3.5" />
                                </button>
                                <button
                                    v-if="apt.status !== 'CANCELLED' && apt.status !== 'COMPLETED' && auth.can('appointments.cancel')"
                                    type="button"
                                    class="h-7 rounded border border-rose-200 bg-rose-50 px-2 text-[11px] text-rose-700 hover:bg-rose-100"
                                    @click="cancelTarget = apt"
                                >
                                    Cancelar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right col: Available Slots -->
            <div class="rounded border border-gray-200 bg-white p-4">
                <h2 class="mb-2 text-[13px] font-normal text-gray-900">Slots Disponíveis</h2>
                <p v-if="!selectedProfessionalId" class="py-6 text-[12px] text-gray-400">
                    Selecione um profissional no filtro acima para visualizar e clicar nos slots livres deste dia.
                </p>
                <div v-else-if="visibleDaySlots.length === 0" class="py-6 text-[12px] text-gray-400">
                    Nenhum horário disponível para este profissional nesta data.
                </div>
                <div v-else class="grid grid-cols-2 gap-1.5">
                    <button
                        v-for="slot in visibleDaySlots"
                        :key="slot.start_time_utc"
                        type="button"
                        class="flex items-center justify-between rounded border border-emerald-200 bg-emerald-50/50 px-2.5 py-1.5 font-mono text-[11px] text-emerald-800 hover:bg-emerald-100"
                        @click="openSlotBooking(slot)"
                    >
                        <span>{{ formatUtcInTimezone(slot.start_time_utc, clinicTimezone) }}</span>
                        <Icon name="lucide:plus" class="size-3" />
                    </button>
                </div>
            </div>
        </div>

        <!-- WEEK VIEW -->
        <div v-else-if="viewMode === 'week'" class="grid grid-cols-1 gap-2 md:grid-cols-7">
            <div
                v-for="day in weekDays"
                :key="day"
                class="flex min-h-44 cursor-pointer flex-col rounded border p-2.5 transition-opacity"
                :class="
                    day === todayDateStr
                        ? 'border-teal-500 bg-teal-50/30 ring-1 ring-teal-500/20 hover:border-teal-600'
                        : day < todayDateStr
                            ? 'border-gray-200 bg-gray-50/80 opacity-65 hover:border-gray-300 hover:opacity-95'
                            : 'border-gray-200 bg-white hover:border-teal-300'
                "
                @click="openDay(day)"
            >
                <button
                    type="button"
                    class="mb-2 flex items-center justify-between border-b border-gray-100 pb-1 text-left text-[11px] font-medium hover:text-teal-700"
                    :class="
                        day === todayDateStr
                            ? 'text-teal-800'
                            : day < todayDateStr
                                ? 'text-gray-400'
                                : 'text-gray-700'
                    "
                    @click="openDay(day)"
                >
                    <span class="flex items-center gap-1.5">
                        <span>{{ day.split('-').reverse().slice(0, 2).join('/') }}</span>
                        <span v-if="day === todayDateStr" class="rounded bg-teal-600 px-1.5 py-0.5 text-[9px] font-normal text-white">Hoje</span>
                    </span>
                    <span v-if="blocksOnDate(day).length > 0" class="rounded bg-amber-100 px-1 text-[9px] text-amber-800">Bloq.</span>
                </button>

                <div class="space-y-1 text-[11px]">
                    <div
                        v-for="apt in appointmentsOnDate(day)"
                        :key="apt.id"
                        class="cursor-pointer rounded border px-1.5 py-1"
                        :class="statusBadgeClass(apt.status)"
                        @click="openDay(day)"
                    >
                        <div class="font-mono text-[10px]">{{ formatUtcInTimezone(apt.start_time_utc, clinicTimezone) }}</div>
                        <div class="truncate">{{ apt.patient?.name || 'Paciente' }}</div>
                    </div>
                    <div v-if="appointmentsOnDate(day).length === 0 && blocksOnDate(day).length === 0" class="text-[10px] text-gray-300">
                        Livre
                    </div>
                </div>
            </div>
        </div>

        <!-- MONTH VIEW -->
        <div v-else class="rounded border border-gray-200 bg-white p-3">
            <div class="mb-2 grid grid-cols-7 gap-1 text-center text-[11px] text-gray-400">
                <div>Dom</div>
                <div>Seg</div>
                <div>Ter</div>
                <div>Qua</div>
                <div>Qui</div>
                <div>Sex</div>
                <div>Sáb</div>
            </div>
            <div class="grid grid-cols-7 gap-1">
                <div
                    v-for="(cell, idx) in monthCells"
                    :key="idx"
                    class="min-h-20 rounded border p-1.5 text-[11px] transition-opacity"
                    :class="
                        !cell.date
                            ? 'border-transparent bg-gray-50/40'
                            : cell.date === todayDateStr
                                ? 'cursor-pointer border-teal-500 bg-teal-50/35 ring-1 ring-teal-500/20 hover:border-teal-600'
                                : cell.date < todayDateStr
                                    ? 'cursor-pointer border-gray-200 bg-gray-50/80 opacity-65 hover:border-gray-300 hover:opacity-95'
                                    : 'cursor-pointer border-gray-200 bg-white hover:border-teal-300'
                    "
                    @click="cell.date && openDay(cell.date)"
                >
                    <template v-if="cell.date">
                        <div class="flex items-center justify-between">
                            <span
                                class="flex items-center gap-1"
                                :class="
                                    cell.date === todayDateStr
                                        ? 'font-medium text-teal-800'
                                        : cell.date < todayDateStr
                                            ? 'text-gray-400'
                                            : 'text-gray-700'
                                "
                            >
                                <span>{{ cell.dayNumber }}</span>
                                <span v-if="cell.date === todayDateStr" class="rounded bg-teal-600 px-1 py-0.2 text-[9px] font-normal text-white">Hoje</span>
                            </span>
                            <span v-if="blocksOnDate(cell.date).length > 0" class="rounded bg-amber-100 px-1 text-[9px] text-amber-800">Bloq.</span>
                        </div>
                        <div class="mt-1 space-y-0.5">
                            <div
                                v-for="apt in appointmentsOnDate(cell.date).slice(0, 2)"
                                :key="apt.id"
                                class="truncate rounded bg-teal-50 px-1 text-[10px] text-teal-700"
                            >
                                {{ formatUtcInTimezone(apt.start_time_utc, clinicTimezone) }} {{ apt.patient?.name }}
                            </div>
                            <div v-if="appointmentsOnDate(cell.date).length > 2" class="text-[10px] text-gray-400">
                                +{{ appointmentsOnDate(cell.date).length - 2 }} mais
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <AppointmentsAppointmentDetailModal
            :open="Boolean(detailTarget)"
            :appointment="detailTarget"
            :default-timezone="clinicTimezone"
            @close="detailTarget = null"
            @updated="loadSchedule"
            @reschedule="openRescheduleModal($event)"
            @notify="notifyTarget = $event"
            @cancel="cancelTarget = $event"
        />

        <AppointmentsAppointmentNotifyModal
            :open="Boolean(notifyTarget)"
            :appointment="notifyTarget"
            :default-timezone="clinicTimezone"
            @close="notifyTarget = null"
        />
        <AppointmentsAppointmentBookingModal
            :open="bookingModalOpen"
            :professionals="professionals"
            :rooms="rooms"
            :default-timezone="clinicTimezone"
            :initial-data="bookingInitialData"
            @close="bookingModalOpen = false"
            @saved="loadSchedule"
        />

        <AppointmentsAppointmentCancelModal
            :open="Boolean(cancelTarget)"
            :appointment="cancelTarget"
            :default-timezone="clinicTimezone"
            @close="cancelTarget = null"
            @cancelled="loadSchedule"
        />

        <AppointmentsScheduleBlockModal
            :open="blockModalOpen"
            :professionals="professionals"
            :default-timezone="clinicTimezone"
            :default-professional-id="selectedProfessionalId || undefined"
            :default-date="currentDate"
            @close="blockModalOpen = false"
            @created="loadSchedule"
        />

        <UiConfirmModal
            :open="Boolean(confirmTarget)"
            title="Confirmar agendamento"
            :message="`Confirmar consulta de ${confirmTarget?.patient?.name || 'Paciente'}?`"
            confirm-label="Confirmar"
            @close="confirmTarget = null"
            @confirm="confirmAppointment"
        />

        <UiConfirmModal
            :open="Boolean(removeBlockTarget)"
            title="Remover bloqueio de agenda"
            :message="`Remover bloqueio (${removeBlockTarget?.reason || ''})?`"
            confirm-label="Remover"
            @close="removeBlockTarget = null"
            @confirm="removeBlock"
        />
    </div>
</template>
