<script setup lang="ts">
definePageMeta({
    layout: 'auth',
});

const { login, loading } = useAuth();
const notify = useNotify();

const form = reactive({
    email: '',
    password: '',
});

async function handleLogin(): Promise<void> {
    try {
        await login({
            email: form.email,
            password: form.password,
        });
        notify.success('Login realizado com sucesso.');
        await navigateTo('/');
    } catch (error: unknown) {
        notify.error((error as Error).message || 'Credenciais inválidas.');
    }
}
</script>

<template>
    <div class="w-full max-w-[380px] rounded-md border border-gray-200 bg-white p-6">
        <div class="mb-5 flex items-center gap-2.5">
            <div class="flex size-8 items-center justify-center rounded bg-blue-600 text-[12px] font-medium text-white">
                SA
            </div>
            <div>
                <h1 class="text-[15px] font-medium text-gray-900">Super Admin</h1>
                <p class="text-[11px] font-light text-gray-400">Acesso restrito à gestão central</p>
            </div>
        </div>

        <form class="space-y-3.5" @submit.prevent="handleLogin">
            <UiInput
                v-model="form.email"
                name="email"
                autocomplete="email"
                label="E-mail"
                type="email"
                placeholder="superadmin@plataforma.com"
                required
            />
            <UiInput
                v-model="form.password"
                name="password"
                autocomplete="current-password"
                label="Senha"
                type="password"
                placeholder="••••••••"
                required
            />
            <div class="pt-1">
                <UiButton type="submit" variant="primary" size="sm" :loading="loading" class="w-full">
                    Entrar no painel
                </UiButton>
            </div>
        </form>
    </div>
</template>
