<script setup lang="ts">
import { usePortalSummaryStore } from '~/stores/usePortalSummaryStore';

const { user } = usePatientAuth();
const summaryStore = usePortalSummaryStore();

const phone = ref('');
const email = ref('');

onMounted(async () => {
    await summaryStore.fetchSummary();
    phone.value = summaryStore.summary?.patient?.phone || '';
    email.value = summaryStore.summary?.patient?.email || user.value?.email || '';
});

watch(
    () => summaryStore.summary?.patient,
    (patient) => {
        if (patient) {
            phone.value = patient.phone || '';
            email.value = patient.email || '';
        }
    }
);

async function handleSave(): Promise<void> {
    await summaryStore.updateProfileOptimistic({
        phone: phone.value.trim(),
        email: email.value.trim() || null,
    });
}
</script>

<template>
    <div class="space-y-5 max-w-2xl">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Meu Perfil</h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Mantenha seus dados de contato atualizados para receber lembretes da clínica.
            </p>
        </div>

        <UiCard v-if="summaryStore.loading" class="space-y-4">
            <UiSkeleton width-class="w-40" height-class="h-5" />
            <UiSkeleton width-class="w-full" height-class="h-9" />
            <UiSkeleton width-class="w-full" height-class="h-9" />
        </UiCard>

        <UiCard v-else>
            <form class="space-y-4" @submit.prevent="handleSave">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-medium text-slate-700 mb-1.5">Nome completo</p>
                        <div
                            class="h-9 px-3 rounded-lg border border-slate-200 bg-slate-50 text-sm text-slate-600 flex items-center"
                        >
                            {{ summaryStore.summary?.patient?.name || user?.name }}
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-slate-700 mb-1.5">Data de nascimento</p>
                        <div
                            class="h-9 px-3 rounded-lg border border-slate-200 bg-slate-50 text-sm text-slate-600 flex items-center"
                        >
                            {{ summaryStore.summary?.patient?.birth_date || 'Não informada' }}
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <UiInput
                        v-model="phone"
                        label="WhatsApp / Telefone"
                        placeholder="(41) 99999-9999"
                        icon="heroicons:phone"
                        :disabled="!summaryStore.summary?.patient"
                    />

                    <UiInput
                        v-model="email"
                        label="E-mail de contato"
                        type="email"
                        placeholder="seu.email@exemplo.com"
                        icon="heroicons:envelope"
                        :disabled="!summaryStore.summary?.patient"
                    />
                </div>

                <div v-if="summaryStore.summary?.patient" class="flex justify-end pt-2">
                    <UiButton type="submit" size="sm" icon="heroicons:check" :loading="summaryStore.savingProfile">
                        Salvar alterações
                    </UiButton>
                </div>
            </form>
        </UiCard>

        <UiCard>
            <h2 class="text-sm font-semibold text-slate-900">Política da Clínica</h2>
            <p class="text-xs text-slate-600 mt-1">
                Cancelamentos pelo portal são permitidos com até
                <strong class="font-medium text-slate-900">
                    {{ summaryStore.summary?.clinic?.cancellation_window_hours ?? 4 }} horas
                </strong>
                de antecedência do horário marcado.
            </p>
        </UiCard>
    </div>
</template>
