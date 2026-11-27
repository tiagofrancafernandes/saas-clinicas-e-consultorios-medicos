<script setup lang="ts">
definePageMeta({
    layout: 'auth',
});

const { login, loading } = usePatientAuth();
const notify = useNotify();

const email = ref('');
const password = ref('');
const errorMessage = ref('');

async function handleSubmit(): Promise<void> {
    errorMessage.value = '';
    try {
        await login({
            email: email.value.trim(),
            password: password.value,
        });
        notify.success('Bem-vindo ao Portal do Paciente!');
        await navigateTo('/');
    } catch (error: unknown) {
        const message = error instanceof Error ? error.message : 'Não foi possível entrar.';
        errorMessage.value = message;
        notify.error(message);
    }
}
</script>

<template>
    <UiCard class="p-6 sm:p-8">
        <div class="text-center mb-6">
            <div class="mx-auto w-11 h-11 rounded-xl bg-sky-600 text-white flex items-center justify-center mb-3">
                <Icon name="heroicons:heart" class="w-5 h-5" />
            </div>
            <h1 class="text-lg font-semibold text-slate-900">Portal do Paciente</h1>
            <p class="text-xs text-slate-500 mt-1">
                Acompanhe suas consultas, confirme horários e acesse seus documentos.
            </p>
        </div>

        <div
            v-if="errorMessage"
            class="mb-4 rounded-lg bg-rose-50 border border-rose-200 px-3 py-2.5 text-xs text-rose-700 flex items-start gap-2"
        >
            <Icon name="heroicons:exclamation-circle" class="w-4 h-4 shrink-0 mt-0.5" />
            <span>{{ errorMessage }}</span>
        </div>

        <form class="space-y-4" @submit.prevent="handleSubmit">
            <UiInput
                v-model="email"
                label="E-mail"
                type="email"
                placeholder="seu.email@exemplo.com"
                autocomplete="email"
                icon="heroicons:envelope"
                required
            />

            <UiInput
                v-model="password"
                label="Senha"
                type="password"
                placeholder="••••••••"
                autocomplete="current-password"
                icon="heroicons:lock-closed"
                required
            />

            <UiButton type="submit" class="w-full" :loading="loading" icon="heroicons:arrow-right-end-on-rectangle">
                Entrar no portal
            </UiButton>
        </form>

        <p class="mt-5 text-center text-[11px] text-slate-400">
            Acesso exclusivo para pacientes cadastrados na clínica.
        </p>
    </UiCard>
</template>
