<script setup lang="ts">
import { SUBSCRIPTION_STATUS_META } from '~/constants/enums';
import type { Subscription } from '~/types/tenant';
import { formatDateBR } from '~/utils/formatters';

interface Props {
    subscriptions: Subscription[];
}

const props = defineProps<Props>();

const activeSubscription = computed(() => props.subscriptions[0] ?? null);
</script>

<template>
    <div class="space-y-4">
        <UiCard v-if="activeSubscription">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="text-[13px] font-medium text-gray-900">Plano e Assinatura Ativa</h3>
                    <p class="mt-0.5 text-[11px] font-light text-gray-400">
                        Ciclo de faturamento e permissões de escrita da clínica.
                    </p>
                </div>
                <UiBadge :color="SUBSCRIPTION_STATUS_META[activeSubscription.status]?.color ?? 'gray'">
                    {{ SUBSCRIPTION_STATUS_META[activeSubscription.status]?.label ?? activeSubscription.status }}
                </UiBadge>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <div class="rounded border border-gray-100 bg-gray-50/70 p-3">
                    <div class="text-[11px] font-light text-gray-500">Intervalo</div>
                    <div class="mt-1 text-[13px] font-normal text-gray-900">
                        {{ activeSubscription.billing_interval }}
                    </div>
                </div>
                <div class="rounded border border-gray-100 bg-gray-50/70 p-3">
                    <div class="text-[11px] font-light text-gray-500">Início do período</div>
                    <div class="mt-1 text-[13px] font-normal tabular-nums text-gray-900">
                        {{ formatDateBR(activeSubscription.current_period_start_utc) }}
                    </div>
                </div>
                <div class="rounded border border-gray-100 bg-gray-50/70 p-3">
                    <div class="text-[11px] font-light text-gray-500">Fim do período</div>
                    <div class="mt-1 text-[13px] font-normal tabular-nums text-gray-900">
                        {{ formatDateBR(activeSubscription.current_period_end_utc) }}
                    </div>
                </div>
                <div class="rounded border border-gray-100 bg-gray-50/70 p-3">
                    <div class="text-[11px] font-light text-gray-500">Isento (Cortesia)</div>
                    <div class="mt-1 text-[13px] font-normal text-gray-900">
                        {{ activeSubscription.is_exempt ? 'Sim' : 'Não' }}
                    </div>
                </div>
            </div>
        </UiCard>

        <UiCard v-else>
            <div class="py-8 text-center text-[12px] font-light text-gray-400">
                Nenhuma assinatura registrada para esta clínica.
            </div>
        </UiCard>
    </div>
</template>
