<script setup lang="ts">
import { toast } from 'vue3-toastify';
import { useAuthStore } from '~/stores/auth';
import { apiRequest } from '~/utils/api';

const auth = useAuthStore();

interface ClinicData {
    id: string;
    name: string;
    slug: string;
    timezone: string;
    tax_id?: string | null;
    can_manage: boolean;
    subscription: {
        status: string;
        billing_interval: string;
        current_period_end_utc?: string | null;
    };
    settings?: {
        reminder_notice_hours: number;
        cancellation_window_hours: number;
        contact_phone: string;
        address: string;
    };
}

const clinic = ref<ClinicData | null>(null);
const saving = ref(false);

const form = ref({
    name: '',
    timezone: 'America/Sao_Paulo',
    reminder_notice_hours: 24,
    cancellation_window_hours: 4,
    contact_phone: '',
    address: '',
});

async function loadClinic(): Promise<void> {
    if (!auth.tenantId) return;
    try {
        const res = await apiRequest<{ data: ClinicData }>('/api/tenant/clinic', {
            tenantId: auth.tenantId,
        });
        clinic.value = res.data;
        form.value = {
            name: res.data.name,
            timezone: res.data.timezone,
            reminder_notice_hours: res.data.settings?.reminder_notice_hours ?? 24,
            cancellation_window_hours: res.data.settings?.cancellation_window_hours ?? 4,
            contact_phone: res.data.settings?.contact_phone ?? '',
            address: res.data.settings?.address ?? '',
        };
    } catch {
        toast.error('Erro ao carregar dados da clínica.');
    }
}

onMounted(loadClinic);

async function handleSave(): Promise<void> {
    if (!auth.tenantId || !auth.can('clinic.settings.manage')) return;
    saving.value = true;
    try {
        await apiRequest('/api/tenant/clinic', {
            method: 'PUT',
            tenantId: auth.tenantId,
            body: {
                name: form.value.name,
                timezone: form.value.timezone,
                settings: {
                    reminder_notice_hours: Number(form.value.reminder_notice_hours),
                    cancellation_window_hours: Number(form.value.cancellation_window_hours),
                    contact_phone: form.value.contact_phone,
                    address: form.value.address,
                },
            },
        });
        toast.success('Configurações da clínica atualizadas.');
        await loadClinic();
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao salvar configurações.');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div>
                <h1 class="text-[19px] font-normal tracking-tight text-gray-900">Dados da Clínica</h1>
                <p class="mt-0.5 text-[12px] text-gray-500">
                    Informações gerais visíveis à equipe e configurações restritas à administração.
                </p>
            </div>
            <NuxtLink
                v-if="auth.can('clinic.settings.manage')"
                to="/whatsapp"
                class="inline-flex h-8 items-center gap-1.5 rounded border border-gray-200 bg-white px-3 text-[12px] text-gray-700 hover:bg-gray-50"
            >
                <Icon name="lucide:message-circle" class="size-3.5 text-emerald-600" />
                <span>Administrar WhatsApp</span>
            </NuxtLink>
        </div>

        <!-- Open Clinic Info (Visible to all clinic staff) -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded border border-gray-200 bg-white p-4">
                <div class="text-[11px] text-gray-400">Clínica</div>
                <div class="mt-1.5 text-[15px] font-normal text-gray-900">
                    {{ clinic?.name || 'Carregando...' }}
                </div>
                <p class="mt-1 font-mono text-[10px] text-gray-400">
                    Slug: {{ clinic?.slug || '—' }} · {{ clinic?.timezone || '—' }}
                </p>
            </div>

            <div class="rounded border border-gray-200 bg-white p-4">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] text-gray-400">Plano Ativo</span>
                    <span class="rounded bg-emerald-50 px-2 py-0.5 text-[10px] text-emerald-700">
                        {{ clinic?.subscription?.status || 'ACTIVE' }}
                    </span>
                </div>
                <div class="mt-1.5 text-[15px] font-normal text-gray-900">Clinic Pro Enterprise</div>
                <p class="mt-1 text-[11px] text-gray-400">
                    Ciclo: {{ clinic?.subscription?.billing_interval || 'MONTHLY' }} (gerenciado pelo Super Admin)
                </p>
            </div>

            <div class="rounded border border-gray-200 bg-white p-4">
                <div class="text-[11px] text-gray-400">Seu Acesso</div>
                <div class="mt-1.5 text-[15px] font-normal text-gray-900">{{ auth.primaryRole }}</div>
                <p class="mt-1 text-[11px] text-gray-400">
                    {{ auth.can('clinic.settings.manage') ? 'Permissão de edição habilitada' : 'Visualização de dados abertos' }}
                </p>
            </div>
        </div>

        <!-- Admin-Only Editable Settings -->
        <section
            v-if="auth.can('clinic.settings.manage')"
            id="clinic-admin-settings-section"
            class="rounded border border-gray-200 bg-white p-4 lg:p-5"
        >
            <div class="mb-4 border-b border-gray-100 pb-3">
                <h2 class="text-[13px] font-normal text-gray-900">Configurações da Clínica</h2>
                <p class="mt-0.5 text-[11px] text-gray-400">
                    Campos como slug, CNPJ e plano são bloqueados para edição e controlados apenas pelo Super Admin.
                </p>
            </div>

            <form class="space-y-3 text-[12px]" @submit.prevent="handleSave">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-[11px] text-gray-600">Nome da Clínica</label>
                        <input
                            id="input-clinic-name"
                            v-model="form.name"
                            type="text"
                            required
                            class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] text-gray-600">Fuso Horário (Timezone)</label>
                        <select v-model="form.timezone" class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px]">
                            <option value="America/Sao_Paulo">America/Sao_Paulo</option>
                            <option value="America/Manaus">America/Manaus</option>
                            <option value="America/Fortaleza">America/Fortaleza</option>
                            <option value="UTC">UTC</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-[11px] text-gray-600">Antecedência de Lembrete (horas)</label>
                        <input
                            v-model.number="form.reminder_notice_hours"
                            type="number"
                            min="1"
                            max="168"
                            class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] text-gray-600">Janela de Cancelamento (horas)</label>
                        <input
                            v-model.number="form.cancellation_window_hours"
                            type="number"
                            min="0"
                            max="72"
                            class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-[11px] text-gray-600">Telefone de Contato</label>
                        <input v-model="form.contact_phone" type="text" class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] text-gray-600">Endereço</label>
                        <input v-model="form.address" type="text" class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
                    </div>
                </div>

                <div class="flex justify-end border-t border-gray-100 pt-3">
                    <button
                        id="btn-save-clinic-settings"
                        type="submit"
                        :disabled="saving"
                        class="flex h-8 items-center gap-1.5 rounded bg-teal-600 px-3 text-[12px] text-white hover:bg-teal-700 disabled:opacity-50"
                    >
                        <Icon name="lucide:check" class="size-3.5" />
                        <span>{{ saving ? 'Salvando...' : 'Salvar configurações' }}</span>
                    </button>
                </div>
            </form>
        </section>
    </div>
</template>
