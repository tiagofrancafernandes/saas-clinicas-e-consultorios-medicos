<script setup lang="ts">
import { toast } from 'vue3-toastify';
import { useAuthStore } from '~/stores/auth';
import { apiRequest } from '~/utils/api';
import { normalizePhoneDigits } from '~/utils/phone';

interface Props {
    open: boolean;
    patient: any;
}

const props = defineProps<Props>();
const emit = defineEmits<{ (e: 'close'): void; (e: 'saved'): void }>();

const auth = useAuthStore();
const saving = ref(false);

const form = reactive({
    name: '',
    phone: '',
    secondary_phone: '',
    email: '',
    tax_id: '',
    birth_date: '',
    gender: 'UNINFORMED',
    address: {
        zip_code: '',
        street: '',
        number: '',
        complement: '',
        neighborhood: '',
        city: '',
        state: '',
    },
});

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen || !props.patient) return;
        const p = props.patient;
        const addr = p.metadata?.address ?? {};
        form.name = p.name ?? '';
        form.phone = p.phone ?? '';
        form.secondary_phone = p.metadata?.secondary_phone ?? '';
        form.email = p.email ?? '';
        form.tax_id = p.tax_id ?? '';
        form.birth_date = p.birth_date ? String(p.birth_date).slice(0, 10) : '';
        form.gender = p.metadata?.gender ?? 'UNINFORMED';
        form.address.zip_code = addr.zip_code ?? '';
        form.address.street = addr.street ?? '';
        form.address.number = addr.number ?? '';
        form.address.complement = addr.complement ?? '';
        form.address.neighborhood = addr.neighborhood ?? '';
        form.address.city = addr.city ?? '';
        form.address.state = addr.state ?? '';
    },
);

async function handleSubmit(): Promise<void> {
    if (!auth.tenantId || !props.patient?.id) return;

    saving.value = true;
    try {
        await apiRequest(`/api/tenant/patients/${props.patient.id}`, {
            method: 'PUT',
            tenantId: auth.tenantId,
            body: {
                name: form.name,
                phone: normalizePhoneDigits(form.phone),
                secondary_phone: form.secondary_phone ? normalizePhoneDigits(form.secondary_phone) : null,
                email: form.email || null,
                tax_id: form.tax_id || null,
                birth_date: form.birth_date || null,
                gender: form.gender,
                address: { ...form.address },
            },
        });
        toast.success('Dados do paciente atualizados com sucesso.');
        emit('saved');
        emit('close');
    } catch (err: any) {
        toast.error(err?.data?.message || 'Erro ao atualizar dados do paciente.');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <UiModal
        :open="props.open"
        title="Editar dados do paciente"
        :description="props.patient?.name"
        max-width="2xl"
        @close="emit('close')"
    >
        <form class="space-y-3 text-[12px]" @submit.prevent="handleSubmit">
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-[11px] text-gray-600">Nome completo</label>
                    <input v-model="form.name" type="text" required class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
                </div>

                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Telefone / WhatsApp</label>
                    <input v-model="form.phone" type="text" required class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
                </div>

                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Telefone secundário</label>
                    <input v-model="form.secondary_phone" type="text" class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
                </div>

                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">E-mail</label>
                    <input v-model="form.email" type="email" class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
                </div>

                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">CPF</label>
                    <input v-model="form.tax_id" type="text" placeholder="000.000.000-00" class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
                </div>

                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Data de nascimento</label>
                    <input v-model="form.birth_date" type="date" class="h-8 w-full rounded border border-gray-200 px-2.5 text-[12px]" />
                </div>

                <div>
                    <label class="mb-1 block text-[11px] text-gray-600">Sexo</label>
                    <select v-model="form.gender" class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px]">
                        <option value="UNINFORMED">Não informado</option>
                        <option value="F">Feminino</option>
                        <option value="M">Masculino</option>
                    </select>
                </div>
            </div>

            <div class="space-y-2 rounded border border-gray-200 bg-gray-50/70 p-3">
                <div class="text-[11px] font-medium text-gray-700">Endereço</div>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-6">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-[10px] text-gray-500">CEP</label>
                        <input v-model="form.address.zip_code" type="text" class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px]" />
                    </div>
                    <div class="sm:col-span-3">
                        <label class="mb-1 block text-[10px] text-gray-500">Logradouro</label>
                        <input v-model="form.address.street" type="text" class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px]" />
                    </div>
                    <div class="sm:col-span-1">
                        <label class="mb-1 block text-[10px] text-gray-500">Número</label>
                        <input v-model="form.address.number" type="text" class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px]" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-[10px] text-gray-500">Complemento</label>
                        <input v-model="form.address.complement" type="text" class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px]" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-[10px] text-gray-500">Bairro</label>
                        <input v-model="form.address.neighborhood" type="text" class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px]" />
                    </div>
                    <div class="sm:col-span-1">
                        <label class="mb-1 block text-[10px] text-gray-500">Cidade</label>
                        <input v-model="form.address.city" type="text" class="h-8 w-full rounded border border-gray-200 bg-white px-2 text-[12px]" />
                    </div>
                    <div class="sm:col-span-1">
                        <label class="mb-1 block text-[10px] text-gray-500">UF</label>
                        <input v-model="form.address.state" type="text" maxlength="2" class="h-8 w-full rounded border border-gray-200 bg-white px-2 uppercase text-[12px]" />
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                <button type="button" class="h-7 rounded border border-gray-200 px-2.5 text-[11px] text-gray-600" @click="emit('close')">
                    Cancelar
                </button>
                <button
                    type="submit"
                    :disabled="saving"
                    class="inline-flex h-7 items-center gap-1.5 rounded bg-teal-600 px-3 text-[11px] text-white hover:bg-teal-700 disabled:opacity-50"
                >
                    <Icon v-if="saving" name="lucide:loader-2" class="size-3 animate-spin" />
                    <span>Salvar alterações</span>
                </button>
            </div>
        </form>
    </UiModal>
</template>
