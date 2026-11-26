<script setup lang="ts">
import { SUBSCRIPTION_STATUS_META } from '~/constants/enums';
import { tenantService } from '~/services/tenantService';
import { userService } from '~/services/userService';
import type { Tenant } from '~/types/tenant';
import type { CentralUser } from '~/types/user';
import { formatDateBR } from '~/utils/formatters';

const notify = useNotify();
const loading = ref(true);
const tenants = ref<Tenant[]>([]);
const users = ref<CentralUser[]>([]);

async function loadDashboard(): Promise<void> {
    loading.value = true;
    try {
        const [tenantsData, usersData] = await Promise.all([tenantService.list(), userService.list()]);
        tenants.value = tenantsData;
        users.value = usersData;
    } catch (error: unknown) {
        notify.error((error as Error).message || 'Erro ao carregar dados do dashboard.');
    } finally {
        loading.value = false;
    }
}

const activeTenantsCount = computed(
    () => tenants.value.filter((t) => t.subscriptions?.[0]?.status === 'ACTIVE').length
);

const courtesyCount = computed(() => tenants.value.filter((t) => t.subscriptions?.[0]?.is_exempt).length);

const recentTenants = computed(() => tenants.value.slice(0, 5));

onMounted(loadDashboard);
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div>
                <h1 class="text-[20px] font-medium tracking-tight text-gray-900">Dashboard</h1>
                <p class="mt-0.5 text-[12px] font-light text-gray-500">
                    Visão geral das clínicas, assinaturas e usuários da plataforma.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <UiButton variant="outline" size="sm" icon="lucide:building-2" @click="navigateTo('/tenants')">
                    Listar clínicas
                </UiButton>
                <UiButton variant="primary" size="sm" icon="lucide:plus" @click="navigateTo('/tenants?create=1')">
                    Nova clínica
                </UiButton>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
            <div class="rounded-md border border-gray-200 bg-white px-4 py-3">
                <div class="text-[11px] font-light text-gray-500">Total de Clínicas</div>
                <div class="mt-1.5 text-[22px] font-normal tabular-nums text-gray-900">
                    {{ loading ? '-' : tenants.length }}
                </div>
            </div>
            <div class="rounded-md border border-gray-200 bg-white px-4 py-3">
                <div class="text-[11px] font-light text-gray-500">Assinaturas Ativas</div>
                <div class="mt-1.5 text-[22px] font-normal tabular-nums text-green-600">
                    {{ loading ? '-' : activeTenantsCount }}
                </div>
            </div>
            <div class="rounded-md border border-gray-200 bg-white px-4 py-3">
                <div class="text-[11px] font-light text-gray-500">Clínicas Cortesia</div>
                <div class="mt-1.5 text-[22px] font-normal tabular-nums text-blue-600">
                    {{ loading ? '-' : courtesyCount }}
                </div>
            </div>
            <div class="rounded-md border border-gray-200 bg-white px-4 py-3">
                <div class="text-[11px] font-light text-gray-500">Usuários Cadastrados</div>
                <div class="mt-1.5 text-[22px] font-normal tabular-nums text-gray-900">
                    {{ loading ? '-' : users.length }}
                </div>
            </div>
        </div>

        <!-- Recent Tenants -->
        <UiCard :padding="false">
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                <div>
                    <h2 class="text-[13px] font-medium text-gray-900">Clínicas Recentes</h2>
                    <p class="mt-0.5 text-[11px] font-light text-gray-400">
                        Últimos estabelecimentos provisionados na plataforma.
                    </p>
                </div>
                <NuxtLink to="/tenants" class="text-[12px] font-normal text-blue-600 hover:text-blue-700">
                    Ver todas
                </NuxtLink>
            </div>

            <div v-if="loading" class="py-10 text-center">
                <Icon name="lucide:loader-2" class="mx-auto size-5 animate-spin text-blue-600" />
            </div>

            <div v-else-if="recentTenants.length === 0" class="py-10 text-center text-[12px] font-light text-gray-400">
                Nenhuma clínica cadastrada ainda.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full whitespace-nowrap text-left">
                    <thead class="bg-gray-50 text-gray-500">
                        <tr>
                            <th class="px-4 py-2.5 text-[11px] font-medium">Clínica</th>
                            <th class="px-4 py-2.5 text-[11px] font-medium">Slug</th>
                            <th class="px-4 py-2.5 text-[11px] font-medium">Plano</th>
                            <th class="px-4 py-2.5 text-[11px] font-medium">Criada em</th>
                            <th class="px-4 py-2.5 text-right text-[11px] font-medium">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-[12px] font-light">
                        <tr v-for="t in recentTenants" :key="t.id" class="hover:bg-gray-50/70">
                            <td class="px-4 py-2.5 font-normal text-gray-900">{{ t.name }}</td>
                            <td class="px-4 py-2.5 font-mono text-[11px] text-gray-500">{{ t.slug }}</td>
                            <td class="px-4 py-2.5">
                                <UiBadge
                                    v-if="t.subscriptions?.[0]"
                                    :color="SUBSCRIPTION_STATUS_META[t.subscriptions[0].status]?.color ?? 'gray'"
                                >
                                    {{
                                        SUBSCRIPTION_STATUS_META[t.subscriptions[0].status]?.label ??
                                        t.subscriptions[0].status
                                    }}
                                </UiBadge>
                                <span v-else class="text-gray-400">-</span>
                            </td>
                            <td class="px-4 py-2.5 tabular-nums text-gray-500">{{ formatDateBR(t.created_at) }}</td>
                            <td class="px-4 py-2.5 text-right">
                                <UiButton
                                    variant="ghost"
                                    size="xs"
                                    icon="lucide:arrow-right"
                                    @click="navigateTo(`/tenants/${t.id}`)"
                                >
                                    Gerenciar
                                </UiButton>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </UiCard>
    </div>
</template>
