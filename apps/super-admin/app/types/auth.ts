export interface AuthUser {
    id: number;
    name: string;
    email: string;
    tenant_id: string | null;
    roles: string[];
    direct_permissions: string[];
    all_permissions: string[];
}

export interface LoginPayload {
    email: string;
    password: string;
}

export interface LoginResponse {
    token: string;
    user: AuthUser;
}
