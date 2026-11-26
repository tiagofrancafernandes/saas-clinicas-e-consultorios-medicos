import type { AuthUser, LoginPayload, LoginResponse } from '~/types/auth';
import { apiRequest } from '~/services/apiClient';

export const authService = {
    login(payload: LoginPayload): Promise<LoginResponse> {
        return apiRequest<LoginResponse>('/auth/login', {
            method: 'POST',
            body: payload as unknown as Record<string, unknown>,
        });
    },

    me(): Promise<{ user: AuthUser }> {
        return apiRequest<{ user: AuthUser }>('/auth/me');
    },

    logout(): Promise<{ message: string }> {
        return apiRequest<{ message: string }>('/auth/logout', {
            method: 'POST',
        });
    },
};
