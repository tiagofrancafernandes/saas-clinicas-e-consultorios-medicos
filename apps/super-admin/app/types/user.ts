export type UserRole = 'SUPER_ADMIN' | 'CLINIC_ADMIN' | 'PROFESSIONAL' | 'RECEPTIONIST' | 'PATIENT';

export interface CentralUser {
    id: number;
    name: string;
    email: string;
    tenant_id: string | null;
    roles: string[];
    role_permissions: string[];
    extra_permissions: string[];
    all_permissions: string[];
}

export interface CreateUserPayload {
    name: string;
    email: string;
    password: string;
    role: UserRole;
    tenant_id?: string | null;
    extra_permissions?: string[];
}

export interface UpdatePermissionsPayload {
    role?: UserRole;
    extra_permissions: string[];
}
