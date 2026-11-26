<script setup lang="ts">
import { toast } from 'vue3-toastify';
import { useAuthStore } from '~/stores/auth';

definePageMeta({
    layout: false,
    sanctum: {
        guestOnly: true,
    },
});

const auth = useAuthStore();

const email = ref('');
const password = ref('');
const loading = ref(false);

async function handleLogin(): Promise<void> {
    if (!email.value || !password.value) {
        toast.warning('Informe seu e-mail e senha.');
        return;
    }

    loading.value = true;
    try {
        await auth.login(email.value, password.value);
        toast.success(`Bem-vindo(a), ${auth.user?.name}!`);
    } catch (err: any) {
        const message = err?.data?.message || err?.message || 'Credenciais inválidas.';
        toast.error(message);
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-[#f9fafb] px-4">
        <div class="w-full max-w-sm rounded-md border border-gray-200 bg-white p-6">
            <div class="mb-5 flex items-center gap-2.5">
                <div class="flex size-8 items-center justify-center rounded bg-blue-600 text-white">
                    <Icon name="lucide:heart-pulse" class="size-4" />
                </div>
                <div>
                    <h1 class="text-[15px] font-normal text-gray-900">Portal da Clínica</h1>
                    <p class="text-[11px] text-gray-400">Acesso exclusivo para equipe e administração</p>
                </div>
            </div>

            <form class="space-y-3" @submit.prevent="handleLogin">
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">E-mail corporativo</label>
                    <input
                        id="input-login-email"
                        v-model="email"
                        type="email"
                        placeholder="admin@clinica1.com"
                        required
                        class="h-8 w-full rounded border border-gray-200 bg-white px-2.5 text-[12px] text-gray-800 placeholder:text-gray-400 focus:border-gray-400"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Senha</label>
                    <input
                        id="input-login-password"
                        v-model="password"
                        type="password"
                        placeholder="••••••••"
                        required
                        class="h-8 w-full rounded border border-gray-200 bg-white px-2.5 text-[12px] text-gray-800 placeholder:text-gray-400 focus:border-gray-400"
                    />
                </div>

                <button
                    id="btn-login-submit"
                    type="submit"
                    :disabled="loading"
                    class="flex h-8 w-full items-center justify-center gap-1.5 rounded bg-blue-600 text-[12px] font-normal text-white transition-colors hover:bg-blue-700 disabled:opacity-50"
                >
                    <Icon v-if="loading" name="lucide:loader-2" class="size-3.5 animate-spin" />
                    <span>{{ loading ? 'Autenticando...' : 'Entrar no Portal' }}</span>
                </button>
            </form>

            <div class="mt-4 border-t border-gray-100 pt-3 text-[10px] text-gray-400">
                Pacientes e Super Admins utilizam seus portais dedicados.
            </div>
        </div>
    </div>
</template>
