import type { CreateTenantPayload, Tenant } from '~/types/tenant';
import { apiRequest } from '~/services/apiClient';

export const tenantService = {
    async list(): Promise<Tenant[]> {
        const response = await apiRequest<{ data: Tenant[] }>('/central/tenants');
        return response.data;
    },

    async getById(id: string): Promise<Tenant> {
        const response = await apiRequest<{ data: Tenant }>(`/central/tenants/${id}`);
        return response.data;
    },

    async create(payload: CreateTenantPayload): Promise<{ message: string; data: Tenant }> {
        return apiRequest<{ message: string; data: Tenant }>('/central/tenants', {
            method: 'POST',
            body: payload as unknown as Record<string, unknown>,
        });
    },
};
