<script setup lang="ts">
import { toast } from 'vue3-toastify';
import { useAuthStore } from '~/stores/auth';
import { apiRequest } from '~/utils/api';

interface Props {
    patientId: string;
    hasInternalNote: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{ (e: 'updated', hasNote: boolean): void }>();

const auth = useAuthStore();

const unlocked = ref(false);
const passwordModalOpen = ref(false);
const passwordInput = ref('');
const noteContent = ref('');
const loading = ref(false);
const saving = ref(false);

async function handleReveal(): Promise<void> {
    if (!auth.tenantId || !passwordInput.value) return;

    loading.value = true;
    try {
        const res = await apiRequest<{ internal_note: string }>(
            `/api/tenant/patients/${props.patientId}/internal-note/reveal`,
            {
                method: 'POST',
                tenantId: auth.tenantId,
                body: { password: passwordInput.value },
            },
        );
        noteContent.value = res.internal_note ?? '';
        unlocked.value = true;
        passwordModalOpen.value = false;
        toast.success('Nota interna desbloqueada.');
    } catch (err: any) {
        toast.error(err?.data?.message || 'Senha incorreta. Acesso negado.');
    } finally {
        loading.value = false;
    }
}

async function handleSave(): Promise<void> {
    if (!auth.tenantId || !passwordInput.value) {
        passwordModalOpen.value = true;
        return;
    }

    saving.value = true;
    try {
        const res = await apiRequest<{ has_internal_note: boolean }>(
            `/api/tenant/patients/${props.patientId}/internal-note`,
            {
                method: 'PUT',
                tenantId: auth.tenantId,
                body: {
                    password: passwordInput.value,
                    internal_note: noteContent.value,
                },
            },
        );
        emit('updated', res.has_internal_note);
        toast.success('Nota interna salva com segurança.');
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao salvar nota interna.');
    } finally {
        saving.value = false;
    }
}

function lockNote(): void {
    unlocked.value = false;
    noteContent.value = '';
    passwordInput.value = '';
}
</script>

<template>
    <div class="rounded border border-gray-200 bg-white p-4 lg:p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-3">
            <div>
                <h2 class="flex items-center gap-1.5 text-[13px] font-normal text-gray-900">
                    <Icon name="lucide:lock" class="size-3.5 text-amber-600" />
                    <span>Nota Interna Restrita</span>
                </h2>
                <p class="mt-0.5 text-[11px] text-gray-400">
                    Confidencial: nunca é exportada em relatórios e exige sua senha para visualização ou edição.
                </p>
            </div>

            <button
                v-if="unlocked"
                type="button"
                class="flex h-7 items-center gap-1.5 rounded border border-gray-200 px-2.5 text-[11px] text-gray-600 hover:bg-gray-50"
                @click="lockNote"
            >
                <Icon name="lucide:lock" class="size-3" />
                <span>Bloquear novamente</span>
            </button>
        </div>

        <div v-if="!unlocked" class="py-10 text-center">
            <div class="mx-auto mb-2.5 flex size-9 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                <Icon name="lucide:shield-alert" class="size-4" />
            </div>
            <div class="text-[12px] text-gray-700">
                {{ hasInternalNote ? 'Este paciente possui uma nota interna protegida.' : 'Nenhuma nota interna registrada ainda.' }}
            </div>
            <p class="mt-0.5 text-[11px] text-gray-400">
                Confirme sua senha de usuário para visualizar ou editar.
            </p>
            <button
                id="btn-unlock-internal-note"
                type="button"
                class="mt-3 inline-flex h-8 items-center gap-1.5 rounded bg-teal-600 px-3 text-[11px] text-white hover:bg-teal-700"
                @click="passwordModalOpen = true"
            >
                <Icon name="lucide:key-round" class="size-3.5" />
                <span>Desbloquear com senha</span>
            </button>
        </div>

        <div v-else class="mt-4 space-y-3">
            <textarea
                id="textarea-internal-note"
                v-model="noteContent"
                rows="5"
                :readonly="!auth.can('patients.update')"
                placeholder="Escreva observações internas confidenciais sobre este paciente..."
                class="w-full rounded border border-gray-200 p-2.5 text-[12px] text-gray-800"
            />
            <div v-if="auth.can('patients.update')" class="flex justify-end">
                <button
                    id="btn-save-internal-note"
                    type="button"
                    :disabled="saving"
                    class="flex h-8 items-center gap-1.5 rounded bg-teal-600 px-3 text-[11px] text-white hover:bg-teal-700 disabled:opacity-50"
                    @click="handleSave"
                >
                    <Icon name="lucide:check" class="size-3.5" />
                    <span>{{ saving ? 'Salvando...' : 'Salvar nota interna' }}</span>
                </button>
            </div>
        </div>

        <UiModal
            :open="passwordModalOpen"
            title="Confirmar Senha"
            description="Informe sua senha atual para acessar a nota interna confidencial."
            max-width="sm"
            @close="passwordModalOpen = false"
        >
            <form class="space-y-3 text-[12px]" @submit.prevent="handleReveal">
                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Sua senha</label>
                    <input
                        id="input-internal-note-password"
                        v-model="passwordInput"
                        type="password"
                        required
                        placeholder="••••••••"
                        class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]"
                    />
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                    <button
                        type="button"
                        class="h-7 rounded border border-gray-200 px-2.5 text-[11px] text-gray-600"
                        @click="passwordModalOpen = false"
                    >
                        Cancelar
                    </button>
                    <button
                        id="btn-confirm-reveal-note"
                        type="submit"
                        :disabled="loading"
                        class="h-7 rounded bg-teal-600 px-3 text-[11px] text-white hover:bg-teal-700 disabled:opacity-50"
                    >
                        {{ loading ? 'Validando...' : 'Desbloquear' }}
                    </button>
                </div>
            </form>
        </UiModal>
    </div>
</template>
