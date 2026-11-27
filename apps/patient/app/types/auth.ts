export interface AuthUser {
    id: string | number;
    name: string;
    email: string;
    tenant_id: string | null;
    is_active: boolean;
    avatar_url: string | null;
    roles: string[];
}

export interface LoginPayload {
    email: string;
    password: string;
}
