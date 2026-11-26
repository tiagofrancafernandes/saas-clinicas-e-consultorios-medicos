import type { CentralUser, CreateUserPayload, UpdatePermissionsPayload } from '~/types/user';
import { apiRequest } from '~/services/apiClient';

export const userService = {
    async list(tenantId?: string): Promise<CentralUser[]> {
        const response = await apiRequest<{ data: CentralUser[] }>('/central/users', {
            query: tenantId ? { tenant_id: tenantId } : undefined,
        });
        return response.data;
    },

    async create(payload: CreateUserPayload): Promise<{ message: string; data: CentralUser }> {
        return apiRequest<{ message: string; data: CentralUser }>('/central/users', {
            method: 'POST',
            body: payload as unknown as Record<string, unknown>,
        });
    },

    async updatePermissions(
        userId: number,
        payload: UpdatePermissionsPayload
    ): Promise<{ message: string; data: CentralUser }> {
        return apiRequest<{ message: string; data: CentralUser }>(`/central/users/${userId}/permissions`, {
            method: 'PUT',
            body: payload as unknown as Record<string, unknown>,
        });
    },
};
