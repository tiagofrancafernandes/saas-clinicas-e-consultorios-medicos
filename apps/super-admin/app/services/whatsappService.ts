import type { CreateWhatsAppInstancePayload, UpdateWhatsAppInstancePayload, WhatsAppInstance } from '~/types/tenant';
import { apiRequest } from '~/services/apiClient';

export const whatsappService = {
    async list(tenantId: string): Promise<WhatsAppInstance[]> {
        const response = await apiRequest<{ data: WhatsAppInstance[] }>(
            `/central/tenants/${tenantId}/whatsapp-instances`
        );
        return response.data;
    },

    async create(
        tenantId: string,
        payload: CreateWhatsAppInstancePayload
    ): Promise<{ message: string; data: WhatsAppInstance }> {
        return apiRequest<{ message: string; data: WhatsAppInstance }>(
            `/central/tenants/${tenantId}/whatsapp-instances`,
            {
                method: 'POST',
                body: payload as unknown as Record<string, unknown>,
            }
        );
    },

    async update(
        tenantId: string,
        instanceId: string,
        payload: UpdateWhatsAppInstancePayload
    ): Promise<{ message: string; data: WhatsAppInstance }> {
        return apiRequest<{ message: string; data: WhatsAppInstance }>(
            `/central/tenants/${tenantId}/whatsapp-instances/${instanceId}`,
            {
                method: 'PUT',
                body: payload as unknown as Record<string, unknown>,
            }
        );
    },

    async remove(tenantId: string, instanceId: string): Promise<{ message: string }> {
        return apiRequest<{ message: string }>(`/central/tenants/${tenantId}/whatsapp-instances/${instanceId}`, {
            method: 'DELETE',
        });
    },

    async testConnection(
        tenantId: string,
        instanceId: string
    ): Promise<{ message?: string; connected?: boolean; data?: unknown }> {
        return apiRequest<{ message?: string; connected?: boolean; data?: unknown }>(
            `/central/tenants/${tenantId}/whatsapp-instances/${instanceId}/test-connection`,
            {
                method: 'POST',
            }
        );
    },

    async sendTest(
        tenantId: string,
        instanceId: string,
        payload: { number: string; text: string }
    ): Promise<{ message: string }> {
        return apiRequest<{ message: string }>(
            `/central/tenants/${tenantId}/whatsapp-instances/${instanceId}/send-test`,
            {
                method: 'POST',
                body: payload,
            }
        );
    },
};
