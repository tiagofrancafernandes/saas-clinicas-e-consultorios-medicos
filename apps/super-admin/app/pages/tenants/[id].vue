<script setup lang="ts">
import { SUBSCRIPTION_STATUS_META } from '~/constants/enums';
import { tenantService } from '~/services/tenantService';
import type { Tenant } from '~/types/tenant';

const route = useRoute();
const notify = useNotify();

const tenantId = computed(() => String(route.params.id));
const loading = ref(true);
const tenant = ref<Tenant | null>(null);
const activeTab = ref('overview');

const tabs = [
    { key: 'overview', label: 'Visão Geral', icon: 'lucide:info' },
    { key: 'subscription', label: 'Plano e Assinatura', icon: 'lucide:credit-card' },
    { key: 'whatsapp', label: 'WhatsApp (Evolution)', icon: 'lucide:message-circle' },
    { key: 'users', label: 'Usuários', icon: 'lucide:users' },
];

const statusMeta = computed(() => {
    const status = tenant.value?.subscriptions?.[0]?.status;
    return status ? SUBSCRIPTION_STATUS_META[status] : null;
});

async function fetchTenant(): Promise<void> {
    loading.value = true;
    try {
        tenant.value = await tenantService.getById(tenantId.value);
    } catch (error: unknown) {
        notify.error((error as Error).message || 'Erro ao carregar dados da clínica.');
    } finally {
        loading.value = false;
    }
}

onMounted(fetchTenant);
</script>

<template>
    <DetailPageTemplate
        v-model:active-tab="activeTab"
        :title="tenant?.name || 'Detalhes da Clínica'"
        :subtitle="tenant ? `Slug: ${tenant.slug} · Fuso: ${tenant.timezone}` : ''"
        back-to="/tenants"
        back-label="Voltar para clínicas"
        :tabs="tabs"
        :loading="loading"
    >
        <template #badge>
            <UiBadge v-if="statusMeta" :color="statusMeta.color">
                {{ statusMeta.label }}
            </UiBadge>
        </template>

        <template #actions>
            <UiButton variant="outline" size="sm" icon="lucide:refresh-cw" @click="fetchTenant">Atualizar</UiButton>
        </template>

        <template v-if="tenant">
            <TenantOverviewTab v-if="activeTab === 'overview'" :tenant="tenant" />
            <TenantSubscriptionTab
                v-else-if="activeTab === 'subscription'"
                :subscriptions="tenant.subscriptions ?? []"
            />
            <TenantWhatsAppTab v-else-if="activeTab === 'whatsapp'" :tenant-id="tenant.id" />
            <TenantUsersTab v-else-if="activeTab === 'users'" :tenant-id="tenant.id" />
        </template>
    </DetailPageTemplate>
</template>
