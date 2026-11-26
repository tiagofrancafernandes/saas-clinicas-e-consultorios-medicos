<script setup lang="ts">
const config = useRuntimeConfig();
const notify = useNotify();

const apiBase = ref(config.public.apiBase);

function handleSave(): void {
    notify.info('As configurações de ambiente são gerenciadas via variáveis NUXT_PUBLIC_API_BASE.');
}
</script>

<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-[20px] font-medium tracking-tight text-gray-900">Configurações</h1>
            <p class="mt-0.5 text-[12px] font-light text-gray-500">Parâmetros gerais da aplicação Super Admin.</p>
        </div>

        <UiCard class="max-w-[600px]">
            <h2 class="mb-3 text-[13px] font-medium text-gray-900">Conexão com API Central</h2>
            <div class="space-y-3">
                <UiInput
                    v-model="apiBase"
                    label="Base URL da API"
                    readonly
                    helper="Definido por NUXT_PUBLIC_API_BASE no ambiente."
                />
                <div class="flex justify-end pt-1">
                    <UiButton variant="outline" size="xs" @click="handleSave">Verificar status</UiButton>
                </div>
            </div>
        </UiCard>
    </div>
</template>
