<script setup lang="ts">
import type { Tenant } from '~/types/tenant';
import { formatDateTimeBR } from '~/utils/formatters';

interface Props {
    tenant: Tenant;
}

const props = defineProps<Props>();
</script>

<template>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <UiCard>
            <h3 class="mb-3 text-[13px] font-medium text-gray-900">Dados Cadastrais</h3>
            <dl class="divide-y divide-gray-100 text-[12px]">
                <div class="flex justify-between py-2">
                    <dt class="font-light text-gray-500">ID (UUID)</dt>
                    <dd class="font-mono text-[11px] text-gray-800">{{ props.tenant.id }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="font-light text-gray-500">Nome</dt>
                    <dd class="font-normal text-gray-900">{{ props.tenant.name }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="font-light text-gray-500">Slug</dt>
                    <dd class="font-mono text-[11px] text-gray-700">{{ props.tenant.slug }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="font-light text-gray-500">CNPJ / Tax ID</dt>
                    <dd class="font-light text-gray-700">{{ props.tenant.tax_id || 'Não informado' }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="font-light text-gray-500">Fuso horário</dt>
                    <dd class="font-light text-gray-700">{{ props.tenant.timezone }}</dd>
                </div>
                <div class="flex justify-between py-2">
                    <dt class="font-light text-gray-500">Criada em</dt>
                    <dd class="tabular-nums font-light text-gray-700">
                        {{ formatDateTimeBR(props.tenant.created_at) }}
                    </dd>
                </div>
            </dl>
        </UiCard>

        <UiCard>
            <h3 class="mb-3 text-[13px] font-medium text-gray-900">Configurações (Settings JSON)</h3>
            <pre
                class="max-h-[260px] overflow-auto rounded border border-gray-100 bg-gray-50 p-3 text-[11px] font-light text-gray-700"
                >{{ JSON.stringify(props.tenant.settings ?? {}, null, 4) }}</pre>
        </UiCard>
    </div>
</template>
