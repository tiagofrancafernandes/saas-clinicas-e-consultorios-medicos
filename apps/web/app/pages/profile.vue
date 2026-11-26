<script setup lang="ts">
import { toast } from 'vue3-toastify';
import { useAuthStore } from '~/stores/auth';
import { apiRequest } from '~/utils/api';

const auth = useAuthStore();
const { refreshIdentity } = useSanctumAuth();

const canEditIdentity = computed(
    () =>
        auth.can('profile.identity.update')
        || auth.primaryRole === 'CLINIC_ADMIN'
        || auth.primaryRole === 'SUPER_ADMIN',
);

const form = reactive({
    name: '',
    email: '',
});

const selectedAvatar = ref<File | null>(null);
const previewUrl = ref<string | null>(null);
const savingProfile = ref(false);

const passwordForm = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
});
const savingPassword = ref(false);

watch(
    () => auth.user,
    (u) => {
        if (!u) return;
        form.name = u.name ?? '';
        form.email = u.email ?? '';
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }
});

function onAvatarSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    if (!file) return;

    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }
    selectedAvatar.value = file;
    previewUrl.value = URL.createObjectURL(file);
}

async function saveProfile(): Promise<void> {
    const body = new FormData();
    if (canEditIdentity.value) {
        body.append('name', form.name);
        body.append('email', form.email);
    }
    if (selectedAvatar.value) {
        body.append('avatar', selectedAvatar.value);
    }

    savingProfile.value = true;
    try {
        await apiRequest('/api/auth/profile', {
            method: 'POST',
            body,
        });
        selectedAvatar.value = null;
        if (previewUrl.value) {
            URL.revokeObjectURL(previewUrl.value);
            previewUrl.value = null;
        }
        await refreshIdentity();
        toast.success('Perfil atualizado com sucesso.');
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao atualizar perfil.');
    } finally {
        savingProfile.value = false;
    }
}

async function removeAvatar(): Promise<void> {
    const body = new FormData();
    body.append('remove_avatar', '1');

    savingProfile.value = true;
    try {
        await apiRequest('/api/auth/profile', {
            method: 'POST',
            body,
        });
        selectedAvatar.value = null;
        if (previewUrl.value) {
            URL.revokeObjectURL(previewUrl.value);
            previewUrl.value = null;
        }
        await refreshIdentity();
        toast.success('Foto de perfil removida.');
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao remover foto.');
    } finally {
        savingProfile.value = false;
    }
}

async function savePassword(): Promise<void> {
    if (passwordForm.password !== passwordForm.password_confirmation) {
        toast.error('A confirmação da nova senha não confere.');
        return;
    }

    savingPassword.value = true;
    try {
        await apiRequest('/api/auth/profile/password', {
            method: 'PUT',
            body: {
                current_password: passwordForm.current_password,
                password: passwordForm.password,
                password_confirmation: passwordForm.password_confirmation,
            },
        });
        passwordForm.current_password = '';
        passwordForm.password = '';
        passwordForm.password_confirmation = '';
        toast.success('Senha alterada com sucesso.');
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao alterar senha.');
    } finally {
        savingPassword.value = false;
    }
}

const displayAvatarUrl = computed(() => previewUrl.value || auth.user?.avatar_url || null);
const initials = computed(() => {
    const name = auth.user?.name?.trim() || 'U';
    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((n) => n[0]?.toUpperCase() ?? '')
        .join('');
});
</script>

<template>
    <div class="mx-auto max-w-3xl space-y-5">
        <div>
            <h1 class="text-[18px] font-normal text-gray-900">Meu Perfil</h1>
            <p class="text-[12px] text-gray-500">Gerencie sua foto de perfil, dados cadastrais e senha de acesso.</p>
        </div>

        <!-- Avatar & Identity Card -->
        <form class="space-y-4 rounded border border-gray-200 bg-white p-5 text-[12px]" @submit.prevent="saveProfile">
            <div class="flex flex-wrap items-center gap-4 border-b border-gray-100 pb-4">
                <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-full border border-gray-200 bg-teal-50 text-[16px] font-medium text-teal-700">
                    <img v-if="displayAvatarUrl" :src="displayAvatarUrl" alt="Avatar" class="size-full object-cover" />
                    <span v-else>{{ initials }}</span>
                </div>

                <div class="space-y-1.5">
                    <div class="text-[13px] text-gray-900">Foto de perfil</div>
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="inline-flex h-7 cursor-pointer items-center gap-1.5 rounded border border-gray-200 bg-white px-2.5 text-[11px] text-gray-700 hover:bg-gray-50">
                            <Icon name="lucide:upload" class="size-3" />
                            <span>Escolher imagem</span>
                            <input type="file" accept="image/*" class="hidden" @change="onAvatarSelected" />
                        </label>
                        <button
                            v-if="auth.user?.avatar_url || previewUrl"
                            type="button"
                            :disabled="savingProfile"
                            class="inline-flex h-7 items-center gap-1 rounded border border-rose-200 bg-rose-50 px-2.5 text-[11px] text-rose-700 hover:bg-rose-100 disabled:opacity-50"
                            @click="removeAvatar"
                        >
                            <Icon name="lucide:trash-2" class="size-3" />
                            <span>Remover foto</span>
                        </button>
                    </div>
                    <p class="text-[10px] text-gray-400">JPG, PNG ou WebP até 5 MB. Ao substituir, a foto anterior é removida automaticamente.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Nome</label>
                    <input
                        v-model="form.name"
                        type="text"
                        :disabled="!canEditIdentity"
                        required
                        class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px] disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">E-mail</label>
                    <input
                        v-model="form.email"
                        type="email"
                        :disabled="!canEditIdentity"
                        required
                        class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px] disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500"
                    />
                </div>
            </div>

            <p v-if="!canEditIdentity" class="text-[11px] text-amber-700">
                Alteração de nome e e-mail requer permissão específica ou solicitação ao administrador da clínica.
            </p>

            <div class="flex justify-end border-t border-gray-100 pt-3">
                <button
                    type="submit"
                    :disabled="savingProfile"
                    class="inline-flex h-8 items-center gap-1.5 rounded bg-teal-600 px-3 text-[12px] text-white hover:bg-teal-700 disabled:opacity-50"
                >
                    <Icon v-if="savingProfile" name="lucide:loader-2" class="size-3.5 animate-spin" />
                    <span>Salvar perfil</span>
                </button>
            </div>
        </form>

        <!-- Password Card -->
        <form class="space-y-3 rounded border border-gray-200 bg-white p-5 text-[12px]" @submit.prevent="savePassword">
            <h2 class="text-[13px] font-normal text-gray-900">Alterar Senha</h2>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Senha atual</label>
                    <input v-model="passwordForm.current_password" type="password" required class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
                </div>
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Nova senha</label>
                    <input v-model="passwordForm.password" type="password" minlength="6" required class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
                </div>
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Confirmar nova senha</label>
                    <input v-model="passwordForm.password_confirmation" type="password" minlength="6" required class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
                </div>
            </div>

            <div class="flex justify-end border-t border-gray-100 pt-3">
                <button
                    type="submit"
                    :disabled="savingPassword"
                    class="inline-flex h-8 items-center gap-1.5 rounded border border-gray-200 bg-white px-3 text-[12px] text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                >
                    <Icon v-if="savingPassword" name="lucide:loader-2" class="size-3.5 animate-spin" />
                    <span>Atualizar senha</span>
                </button>
            </div>
        </form>
    </div>
</template>
