<script setup lang="ts">
import { toast } from 'vue3-toastify';
import { useAuthStore } from '~/stores/auth';
import { apiRequest } from '~/utils/api';

definePageMeta({ permission: 'patients.view' });

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

type PatientTabId = 'appointments' | 'records' | 'files' | 'messages' | 'internal-note';
const VALID_TABS: PatientTabId[] = ['appointments', 'records', 'files', 'messages', 'internal-note'];

function resolveTabFromQuery(rawTab: unknown): PatientTabId {
    const candidate = typeof rawTab === 'string' ? rawTab : '';
    return VALID_TABS.includes(candidate as PatientTabId) ? (candidate as PatientTabId) : 'appointments';
}

const patientId = computed(() => String(route.params.id));
const activeTab = ref<PatientTabId>(resolveTabFromQuery(route.query.tab));

const patient = ref<any>(null);
const medicalRecords = ref<any[]>([]);
const messages = ref<any[]>([]);
const messagesPage = ref(1);
const hasMoreMessages = ref(false);
const totalMessages = ref(0);
const loadingMessages = ref(false);
const loadingMoreMessages = ref(false);
const messagesLoaded = ref(false);
const hasInternalNote = ref(false);
const clinicTimezone = ref('America/Sao_Paulo');
const loading = ref(true);

const messageDropdownOpen = ref(false);
const sendMessageModalOpen = ref(false);
const confirmMessageAction = ref<{ type: 'cancel' | 'send-now'; message: any } | null>(null);
const processingMessageAction = ref(false);

const tabs = computed(() =>
    [
        { id: 'appointments', label: 'Histórico de Atendimentos', icon: 'lucide:calendar-days', visible: true },
        { id: 'records', label: 'Prontuário SOAP', icon: 'lucide:file-text', visible: auth.can('medical_records.view') },
        { id: 'files', label: 'Arquivos & Exames', icon: 'lucide:folder', visible: auth.can('files.view') },
        { id: 'messages', label: 'Mensagens', icon: 'lucide:message-circle', visible: true },
        { id: 'internal-note', label: 'Nota Interna', icon: 'lucide:lock', visible: true },
    ].filter((t) => t.visible),
);

async function executeMessageAction(): Promise<void> {
    if (!auth.tenantId || !confirmMessageAction.value) return;
    const { type, message } = confirmMessageAction.value;
    processingMessageAction.value = true;
    try {
        await apiRequest(`/api/tenant/patients/${patientId.value}/messages/${message.id}/${type}`, {
            method: 'POST',
            tenantId: auth.tenantId,
        });
        toast.success(type === 'send-now' ? 'Mensagem enviada com sucesso.' : 'Agendamento cancelado com sucesso.');
        confirmMessageAction.value = null;
        await loadMessages();
    } catch (err: any) {
        toast.error(
            err?.data?.message
            || (type === 'send-now'
                ? 'Não foi possível enviar a mensagem agora.'
                : 'Não foi possível cancelar a mensagem agendada.'),
        );
        if (type === 'send-now') {
            confirmMessageAction.value = null;
            await loadMessages();
        }
    } finally {
        processingMessageAction.value = false;
    }
}

function statusBadgeClass(status: string): string {
    if (status === 'SENT') return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    if (status === 'SCHEDULED') return 'border-amber-200 bg-amber-50 text-amber-700';
    if (status === 'PROCESSING') return 'border-teal-200 bg-teal-50 text-teal-700';
    if (status === 'FAILED') return 'border-rose-200 bg-rose-50 text-rose-700';
    return 'border-gray-200 bg-gray-100 text-gray-500';
}

function statusLabel(status: string): string {
    if (status === 'SENT') return 'Enviada';
    if (status === 'SCHEDULED') return 'Agendada';
    if (status === 'PROCESSING') return 'Enviando';
    if (status === 'FAILED') return 'Falhou';
    if (status === 'CANCELLED') return 'Cancelada';
    if (status === 'EXPIRED') return 'Expirada';
    return status;
}

async function loadMessages(page = 1, append = false): Promise<void> {
    if (!auth.tenantId) return;
    if (append) {
        loadingMoreMessages.value = true;
    } else {
        loadingMessages.value = true;
    }
    try {
        const res = await apiRequest<{
            data: any[];
            meta?: { current_page: number; has_more: boolean; total: number };
        }>(`/api/tenant/patients/${patientId.value}/messages?per_page=20&page=${page}`, {
            tenantId: auth.tenantId,
        });
        const items = res.data ?? [];
        messages.value = append ? [...messages.value, ...items] : items;
        messagesPage.value = res.meta?.current_page ?? page;
        hasMoreMessages.value = Boolean(res.meta?.has_more);
        totalMessages.value = res.meta?.total ?? messages.value.length;
        messagesLoaded.value = true;
    } catch {
        // Ignore when offline
    } finally {
        loadingMessages.value = false;
        loadingMoreMessages.value = false;
    }
}

function selectTab(tabId: PatientTabId): void {
    activeTab.value = tabId;
    if (route.query.tab !== tabId) {
        router.replace({ query: { ...route.query, tab: tabId } });
    }
}

function handleMessageSent(): void {
    if (activeTab.value === 'messages') {
        loadMessages(1, false);
        return;
    }
    messagesLoaded.value = false;
}

watch(
    () => route.query.tab,
    (tabQuery) => {
        const resolved = resolveTabFromQuery(tabQuery);
        if (activeTab.value !== resolved) {
            activeTab.value = resolved;
        }
    },
);

watch(
    [activeTab, () => auth.tenantId],
    ([tab, tenantId]) => {
        if (tab === 'messages' && tenantId && !messagesLoaded.value && !loadingMessages.value) {
            loadMessages(1, false);
        }
    },
    { immediate: true },
);

async function loadClinicTimezone(): Promise<void> {
    if (!auth.tenantId) return;
    try {
        const res = await apiRequest<{ data: { timezone?: string } }>('/api/tenant/clinic', {
            tenantId: auth.tenantId,
        });
        if (res.data?.timezone) {
            clinicTimezone.value = res.data.timezone;
        }
    } catch {
        // Fallback to America/Sao_Paulo
    }
}

async function loadPatient(): Promise<void> {
    if (!auth.tenantId) return;
    loading.value = true;
    try {
        const [res] = await Promise.all([
            apiRequest<{ data: any; medical_records: any[]; has_internal_note: boolean }>(
                `/api/tenant/patients/${patientId.value}`,
                { tenantId: auth.tenantId },
            ),
            loadClinicTimezone(),
        ]);
        patient.value = res.data;
        medicalRecords.value = res.medical_records ?? [];
        hasInternalNote.value = Boolean(res.has_internal_note);
    } catch {
        toast.error('Paciente não encontrado.');
    } finally {
        loading.value = false;
    }
}

onMounted(loadPatient);
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <NuxtLink
                to="/patients"
                class="inline-flex items-center gap-1.5 text-[12px] text-gray-500 hover:text-gray-800"
            >
                <Icon name="lucide:arrow-left" class="size-3.5" />
                <span>Voltar para pacientes</span>
            </NuxtLink>

            <!-- Top Action Buttons: Edit + Send Message Dropdown -->
            <div v-if="patient" class="flex items-center gap-2">
                <button
                    v-if="auth.can('patients.update')"
                    type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded border border-gray-200 bg-white px-2.5 text-[12px] text-gray-700 hover:bg-gray-50"
                >
                    <Icon name="lucide:pencil" class="size-3.5" />
                    <span>Editar</span>
                </button>

                <div class="relative">
                    <button
                        id="btn-send-message-dropdown"
                        type="button"
                        class="inline-flex h-8 items-center gap-1.5 rounded bg-teal-600 px-3 text-[12px] text-white hover:bg-teal-700"
                        @click="messageDropdownOpen = !messageDropdownOpen"
                    >
                        <Icon name="lucide:send" class="size-3.5" />
                        <span>Enviar mensagem</span>
                        <Icon name="lucide:chevron-down" class="size-3.5" />
                    </button>

                    <div
                        v-if="messageDropdownOpen"
                        class="absolute right-0 top-full z-40 mt-1 w-48 rounded border border-gray-200 bg-white py-1 shadow-lg"
                    >
                        <button
                            id="btn-channel-whatsapp"
                            type="button"
                            class="flex h-8 w-full items-center justify-between px-3 text-left text-[12px] text-gray-700 hover:bg-gray-50"
                            @click="messageDropdownOpen = false; sendMessageModalOpen = true"
                        >
                            <span class="flex items-center gap-2">
                                <Icon name="lucide:message-circle" class="size-3.5 text-emerald-600" />
                                <span>WhatsApp</span>
                            </span>
                        </button>

                        <button
                            type="button"
                            disabled
                            class="flex h-8 w-full cursor-not-allowed items-center justify-between px-3 text-left text-[12px] text-gray-400"
                        >
                            <span class="flex items-center gap-2">
                                <Icon name="lucide:send" class="size-3.5" />
                                <span>Telegram</span>
                            </span>
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[9px] text-gray-500">Em breve</span>
                        </button>

                        <button
                            type="button"
                            disabled
                            class="flex h-8 w-full cursor-not-allowed items-center justify-between px-3 text-left text-[12px] text-gray-400"
                        >
                            <span class="flex items-center gap-2">
                                <Icon name="lucide:message-square" class="size-3.5" />
                                <span>SMS</span>
                            </span>
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[9px] text-gray-500">Em breve</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Basic Patient Card -->
        <div class="rounded border border-gray-200 bg-white p-4 lg:p-5">
            <div v-if="loading" class="text-[12px] text-gray-400">Carregando dados do paciente...</div>
            <div v-else-if="patient" class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-[18px] font-normal text-gray-900">{{ patient.name }}</h1>
                        <span
                            class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px]"
                            :class="patient.metadata?.whatsapp_verified ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                        >
                            <Icon :name="patient.metadata?.whatsapp_verified ? 'lucide:check-circle-2' : 'lucide:minus-circle'" class="size-3" />
                            {{ patient.metadata?.whatsapp_verified ? 'WhatsApp Verificado' : 'Não verificado' }}
                        </span>
                    </div>
                    <div class="mt-1.5 flex flex-wrap items-center gap-4 text-[11px] text-gray-500">
                        <span>Telefone: <strong class="font-normal text-gray-700">{{ patient.phone }}</strong></span>
                        <span>E-mail: <strong class="font-normal text-gray-700">{{ patient.email || '—' }}</strong></span>
                        <span>CPF: <strong class="font-normal text-gray-700">{{ patient.tax_id || '—' }}</strong></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="flex flex-wrap gap-1 border-b border-gray-200">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                class="inline-flex h-9 items-center gap-1.5 border-b-2 px-3 text-[12px] transition-colors"
                :class="
                    activeTab === tab.id
                        ? 'border-teal-600 text-teal-700'
                        : 'border-transparent text-gray-500 hover:text-gray-800'
                "
                @click="selectTab(tab.id as PatientTabId)"
            >
                <Icon :name="tab.icon" class="size-3.5" />
                <span>{{ tab.label }}</span>
            </button>
        </div>

        <!-- Tab: Appointments History -->
        <div v-if="activeTab === 'appointments'" class="rounded border border-gray-200 bg-white p-4">
            <h2 class="mb-3 text-[13px] font-normal text-gray-900">Histórico de Atendimentos</h2>
            <div v-if="!patient?.appointments?.length" class="py-8 text-center text-[12px] text-gray-400">
                Nenhum atendimento registrado para este paciente.
            </div>
            <div v-else class="divide-y divide-gray-100 text-[12px]">
                <div
                    v-for="apt in patient.appointments"
                    :key="apt.id"
                    class="flex items-center justify-between py-2.5"
                >
                    <div>
                        <div class="text-gray-800">{{ apt.professional?.name || 'Profissional' }} · {{ apt.room?.name || 'Consultório' }}</div>
                        <div class="font-mono text-[10px] text-gray-400">{{ apt.start_time_utc }}</div>
                    </div>
                    <span class="rounded border border-gray-200 bg-gray-50 px-2 py-0.5 text-[10px] text-gray-700">{{ apt.status }}</span>
                </div>
            </div>
        </div>

        <!-- Tab: SOAP Medical Records -->
        <div v-else-if="activeTab === 'records'" class="rounded border border-gray-200 bg-white p-4">
            <h2 class="mb-3 text-[13px] font-normal text-gray-900">Prontuário SOAP</h2>
            <div v-if="medicalRecords.length === 0" class="py-8 text-center text-[12px] text-gray-400">
                Nenhuma evolução clínica registrada.
            </div>
            <div v-else class="divide-y divide-gray-100 text-[12px]">
                <div v-for="rec in medicalRecords" :key="rec.id" class="py-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-900">{{ rec.title }}</span>
                        <span class="rounded border border-gray-200 bg-gray-50 px-2 py-0.5 text-[10px] text-gray-600">{{ rec.visibility }}</span>
                    </div>
                    <div class="mt-0.5 text-[11px] text-gray-400">Autor: {{ rec.author?.name || 'Profissional' }}</div>
                </div>
            </div>
        </div>

        <!-- Tab: Files -->
        <div v-else-if="activeTab === 'files'" class="rounded border border-gray-200 bg-white p-4 text-[12px] text-gray-500">
            Nenhum arquivo ou exame anexado recentemente.
        </div>

        <!-- Tab: Messages History -->
        <div v-else-if="activeTab === 'messages'" class="rounded border border-gray-200 bg-white p-4">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="text-[13px] font-normal text-gray-900">
                    Histórico de Mensagens
                    <span v-if="messagesLoaded && totalMessages > 0" class="ml-1 text-[11px] text-gray-400">
                        ({{ messages.length }} de {{ totalMessages }})
                    </span>
                </h2>
                <button
                    id="btn-refresh-patient-messages"
                    type="button"
                    :disabled="loadingMessages || loadingMoreMessages"
                    class="inline-flex h-7 items-center gap-1.5 rounded border border-gray-200 bg-white px-2.5 text-[11px] text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                    @click="loadMessages(1, false)"
                >
                    <Icon name="lucide:refresh-cw" class="size-3" :class="loadingMessages ? 'animate-spin' : ''" />
                    <span>Atualizar</span>
                </button>
            </div>

            <div v-if="loadingMessages && messages.length === 0" class="py-8 text-center text-[12px] text-gray-400">
                Carregando mensagens...
            </div>
            <div v-else-if="messages.length === 0" class="py-8 text-center text-[12px] text-gray-400">
                Nenhuma mensagem enviada ou agendada para este paciente.
            </div>
            <div v-else class="divide-y divide-gray-100 text-[12px]">
                <div v-for="msg in messages" :key="msg.id" class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] text-emerald-700">{{ msg.channel }}</span>
                            <span class="text-gray-900">{{ msg.payload?.text || 'Mensagem' }}</span>
                        </div>
                        <div class="mt-0.5 font-mono text-[10px] text-gray-400">
                            Para: {{ msg.recipient }}
                            <span v-if="msg.scheduled_at_utc"> · Agendado (UTC): {{ msg.scheduled_at_utc }}</span>
                            <span v-if="msg.sent_at_utc"> · Enviado (UTC): {{ msg.sent_at_utc }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <template v-if="msg.status === 'SCHEDULED'">
                            <button
                                type="button"
                                class="inline-flex h-7 items-center gap-1 rounded border border-teal-200 bg-teal-50 px-2 text-[11px] text-teal-700 hover:bg-teal-100"
                                @click="confirmMessageAction = { type: 'send-now', message: msg }"
                            >
                                <Icon name="lucide:send" class="size-3" />
                                <span>Enviar agora</span>
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-7 items-center gap-1 rounded border border-rose-200 bg-rose-50 px-2 text-[11px] text-rose-700 hover:bg-rose-100"
                                @click="confirmMessageAction = { type: 'cancel', message: msg }"
                            >
                                <Icon name="lucide:x" class="size-3" />
                                <span>Cancelar</span>
                            </button>
                        </template>
                        <span
                            class="rounded border px-2 py-0.5 text-[10px]"
                            :class="statusBadgeClass(msg.status)"
                        >
                            {{ statusLabel(msg.status) }}
                        </span>
                    </div>
                </div>
            </div>

            <div v-if="hasMoreMessages" class="mt-3 flex justify-center border-t border-gray-100 pt-3">
                <button
                    type="button"
                    :disabled="loadingMoreMessages"
                    class="inline-flex h-8 items-center gap-1.5 rounded border border-gray-200 bg-white px-3 text-[11px] text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                    @click="loadMessages(messagesPage + 1, true)"
                >
                    <Icon v-if="loadingMoreMessages" name="lucide:loader-2" class="size-3 animate-spin" />
                    <span>{{ loadingMoreMessages ? 'Carregando...' : 'Ver mais mensagens antigas' }}</span>
                </button>
            </div>
        </div>

        <!-- Tab: Restricted Internal Note -->
        <PatientsPatientInternalNoteTab
            v-else-if="activeTab === 'internal-note'"
            :patient-id="patientId"
            :has-internal-note="hasInternalNote"
            @updated="hasInternalNote = $event"
        />

        <PatientsPatientSendMessageModal
            v-if="patient"
            :open="sendMessageModalOpen"
            :patient-id="patientId"
            :default-phone="patient.phone"
            :patient-name="patient.name"
            :default-timezone="clinicTimezone"
            @close="sendMessageModalOpen = false"
            @sent="handleMessageSent"
        />

        <UiModal
            :open="Boolean(confirmMessageAction)"
            :title="confirmMessageAction?.type === 'send-now' ? 'Enviar mensagem agora?' : 'Cancelar agendamento?'"
            :description="
                confirmMessageAction?.type === 'send-now'
                    ? 'A mensagem será enviada imediatamente para o WhatsApp do paciente.'
                    : 'A mensagem agendada não será enviada para o paciente.'
            "
            max-width="sm"
            @close="!processingMessageAction && (confirmMessageAction = null)"
        >
            <div v-if="confirmMessageAction" class="space-y-3 text-[12px]">
                <div class="rounded border border-gray-200 bg-gray-50 p-2.5 text-gray-700">
                    <div class="text-[11px] text-gray-500">Para: {{ confirmMessageAction.message.recipient }}</div>
                    <div class="mt-1 whitespace-pre-wrap text-gray-900">
                        {{ confirmMessageAction.message.payload?.text || 'Mensagem' }}
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                    <button
                        type="button"
                        :disabled="processingMessageAction"
                        class="h-7 rounded border border-gray-200 px-2.5 text-[11px] text-gray-600 disabled:opacity-50"
                        @click="confirmMessageAction = null"
                    >
                        Voltar
                    </button>
                    <button
                        type="button"
                        :disabled="processingMessageAction"
                        class="inline-flex h-7 items-center gap-1.5 rounded px-3 text-[11px] text-white disabled:opacity-50"
                        :class="
                            confirmMessageAction.type === 'send-now'
                                ? 'bg-teal-600 hover:bg-teal-700'
                                : 'bg-rose-600 hover:bg-rose-700'
                        "
                        @click="executeMessageAction"
                    >
                        <Icon v-if="processingMessageAction" name="lucide:loader-2" class="size-3 animate-spin" />
                        <span>
                            {{
                                confirmMessageAction.type === 'send-now'
                                    ? 'Confirmar envio'
                                    : 'Confirmar cancelamento'
                            }}
                        </span>
                    </button>
                </div>
            </div>
        </UiModal>
    </div>
</template>
