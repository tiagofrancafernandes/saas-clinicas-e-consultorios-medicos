export type ClinicRole = 'CLINIC_ADMIN' | 'PROFESSIONAL' | 'RECEPTIONIST';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    tenant_id: string | null;
    is_active?: boolean;
    avatar_url?: string | null;
    professional_id?: string | null;
    roles: string[];
    direct_permissions?: string[];
    all_permissions: string[];
}

export interface LoginResponse {
    token: string;
    user: AuthUser;
}
