<script setup lang="ts">
import { whatsappService } from '~/services/whatsappService';
import type { WhatsAppInstance } from '~/types/tenant';

interface Props {
    tenantId: string;
}

const props = defineProps<Props>();

const notify = useNotify();
const { confirm } = useConfirmModal();

const loading = ref(true);
const testingId = ref<string | null>(null);
const instances = ref<WhatsAppInstance[]>([]);

const modalOpen = ref(false);
const editingInstance = ref<WhatsAppInstance | null>(null);
const sendTestModalOpen = ref(false);
const sendTestTarget = ref<WhatsAppInstance | null>(null);

async function fetchInstances(): Promise<void> {
    loading.value = true;
    try {
        instances.value = await whatsappService.list(props.tenantId);
    } catch (error: unknown) {
        notify.error((error as Error).message || 'Erro ao carregar instâncias WhatsApp.');
    } finally {
        loading.value = false;
    }
}

function openCreate(): void {
    editingInstance.value = null;
    modalOpen.value = true;
}

function openEdit(instance: WhatsAppInstance): void {
    editingInstance.value = instance;
    modalOpen.value = true;
}

function openSendTest(instance: WhatsAppInstance): void {
    sendTestTarget.value = instance;
    sendTestModalOpen.value = true;
}

async function handleTestConnection(instance: WhatsAppInstance): Promise<void> {
    testingId.value = instance.id;
    try {
        const res = await whatsappService.testConnection(props.tenantId, instance.id);
        notify.success(res.message || 'Conexão verificada com sucesso.');
    } catch (error: unknown) {
        notify.error((error as Error).message || 'Falha ao testar conexão.');
    } finally {
        testingId.value = null;
    }
}

async function handleDelete(instance: WhatsAppInstance): Promise<void> {
    const confirmed = await confirm({
        title: 'Excluir instância WhatsApp',
        message: `Tem certeza que deseja excluir a instância "${instance.label}" (${instance.instance_name})? Esta ação não pode ser desfeita.`,
        confirmLabel: 'Excluir instância',
        variant: 'danger',
    });

    if (!confirmed) {
        return;
    }

    try {
        await whatsappService.remove(props.tenantId, instance.id);
        notify.success('Instância removida.');
        await fetchInstances();
    } catch (error: unknown) {
        notify.error((error as Error).message || 'Erro ao remover instância.');
    }
}

onMounted(fetchInstances);
</script>

<template>
    <UiCard :padding="false">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
            <div>
                <h3 class="text-[13px] font-medium text-gray-900">Instâncias WhatsApp (Evolution API)</h3>
                <p class="mt-0.5 text-[11px] font-light text-gray-400">
                    Gerencie números conectados, categorias de envio e testes de conexão.
                </p>
            </div>
            <UiButton variant="primary" size="xs" icon="lucide:plus" @click="openCreate">Nova instância</UiButton>
        </div>

        <div v-if="loading" class="py-12 text-center">
            <Icon name="lucide:loader-2" class="mx-auto size-5 animate-spin text-blue-600" />
        </div>

        <div v-else-if="instances.length === 0" class="py-12 text-center">
            <Icon name="lucide:message-square-off" class="mx-auto mb-2 size-6 text-gray-300" />
            <div class="text-[12px] font-normal text-gray-700">Nenhuma instância configurada</div>
            <div class="mt-0.5 text-[11px] font-light text-gray-400">
                Adicione uma instância da Evolution API para esta clínica.
            </div>
        </div>

        <div v-else class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-4 py-2.5 text-[11px] font-medium">Rótulo</th>
                        <th class="px-4 py-2.5 text-[11px] font-medium">Instância</th>
                        <th class="px-4 py-2.5 text-[11px] font-medium">Telefone</th>
                        <th class="px-4 py-2.5 text-[11px] font-medium">Categoria</th>
                        <th class="px-4 py-2.5 text-[11px] font-medium">Status</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-medium">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-[12px] font-light">
                    <tr v-for="inst in instances" :key="inst.id" class="hover:bg-gray-50/70">
                        <td class="px-4 py-2.5 font-normal text-gray-900">
                            {{ inst.label }}
                            <span
                                v-if="inst.is_default"
                                class="ml-1.5 rounded bg-blue-50 px-1.5 py-0.5 text-[10px] text-blue-700"
                            >
                                Padrão
                            </span>
                        </td>
                        <td class="px-4 py-2.5 font-mono text-[11px] text-gray-600">{{ inst.instance_name }}</td>
                        <td class="px-4 py-2.5 tabular-nums text-gray-600">{{ inst.phone_number }}</td>
                        <td class="px-4 py-2.5">
                            <UiBadge color="gray" :dot="false">{{ inst.category }}</UiBadge>
                        </td>
                        <td class="px-4 py-2.5">
                            <UiBadge :color="inst.active ? 'green' : 'red'">
                                {{ inst.active ? 'Ativa' : 'Inativa' }}
                            </UiBadge>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <UiButton
                                    variant="ghost"
                                    size="xs"
                                    icon="lucide:wifi"
                                    :loading="testingId === inst.id"
                                    title="Testar conexão"
                                    @click="handleTestConnection(inst)"
                                />
                                <UiButton
                                    variant="ghost"
                                    size="xs"
                                    icon="lucide:send"
                                    title="Enviar mensagem teste"
                                    @click="openSendTest(inst)"
                                />
                                <UiButton
                                    variant="ghost"
                                    size="xs"
                                    icon="lucide:pencil"
                                    title="Editar"
                                    @click="openEdit(inst)"
                                />
                                <UiButton
                                    variant="ghost"
                                    size="xs"
                                    icon="lucide:trash-2"
                                    class="hover:bg-red-50 hover:text-red-600"
                                    title="Excluir"
                                    @click="handleDelete(inst)"
                                />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <WhatsAppInstanceModal
            :open="modalOpen"
            :tenant-id="props.tenantId"
            :instance="editingInstance"
            @close="modalOpen = false"
            @saved="fetchInstances"
        />

        <WhatsAppSendTestModal
            :open="sendTestModalOpen"
            :tenant-id="props.tenantId"
            :instance="sendTestTarget"
            @close="sendTestModalOpen = false"
        />
    </UiCard>
</template>
