import { defineStore } from 'pinia';
import type { AuthUser } from '~/types/auth';

const ALLOWED_CLINIC_ROLES = ['CLINIC_ADMIN', 'PROFESSIONAL', 'RECEPTIONIST'];

export const useAuthStore = defineStore('clinic-auth', () => {
    const { isAuthenticated, login: sanctumLogin, logout: sanctumLogout } = useSanctumAuth();
    const sanctumUser = useSanctumUser<AuthUser>();

    const user = computed(() => sanctumUser.value ?? null);
    const token = computed(() => useCookie<string | null>('clinic_sanctum_token').value ?? null);
    const tenantId = computed(() => user.value?.tenant_id ?? null);
    const permissions = computed(() => user.value?.all_permissions ?? []);
    const primaryRole = computed(() => user.value?.roles?.[0] ?? '');

    function can(permission?: string): boolean {
        if (!permission) return true;
        return permissions.value.includes(permission);
    }

    function canAny(required: string[]): boolean {
        if (required.length === 0) return true;
        return required.some((perm) => can(perm));
    }

    function isAllowedClinicUser(candidate: AuthUser | null): boolean {
        if (!candidate) return false;
        return candidate.roles.some((role) => ALLOWED_CLINIC_ROLES.includes(role));
    }

    async function login(email: string, password: string): Promise<void> {
        await sanctumLogin({ email, password });

        if (!isAllowedClinicUser(user.value)) {
            await sanctumLogout();
            throw new Error('Acesso restrito a administradores e equipe da clínica. Pacientes e Super Admins possuem portais próprios.');
        }
    }

    async function logout(): Promise<void> {
        await sanctumLogout();
    }

    return {
        token,
        user,
        tenantId,
        isAuthenticated,
        permissions,
        primaryRole,
        can,
        canAny,
        login,
        logout,
    };
});
