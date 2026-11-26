import type { AuthUser, LoginPayload } from '~/types/auth';
import { authService } from '~/services/authService';

export function useAuth() {
    const token = useCookie<string | null>('sa_token', {
        default: () => null,
        sameSite: 'lax',
    });

    const user = useState<AuthUser | null>('sa_user', () => null);
    const loading = useState<boolean>('sa_auth_loading', () => false);

    const isAuthenticated = computed(() => Boolean(token.value));
    const isSuperAdmin = computed(() => user.value?.roles.includes('SUPER_ADMIN') ?? false);

    async function login(payload: LoginPayload): Promise<void> {
        loading.value = true;
        try {
            const response = await authService.login(payload);
            if (!response.user.roles.includes('SUPER_ADMIN')) {
                throw new Error('Acesso restrito a Super Administradores.');
            }
            token.value = response.token;
            user.value = response.user;
        } finally {
            loading.value = false;
        }
    }

    async function fetchMe(): Promise<AuthUser | null> {
        if (!token.value) {
            user.value = null;
            return null;
        }

        try {
            const response = await authService.me();
            user.value = response.user;
            return response.user;
        } catch {
            token.value = null;
            user.value = null;
            return null;
        }
    }

    async function logout(): Promise<void> {
        try {
            if (token.value) {
                await authService.logout();
            }
        } catch {
            // Clear local session even if remote token revocation fails
        } finally {
            token.value = null;
            user.value = null;
            await navigateTo('/login');
        }
    }

    return {
        token,
        user,
        loading,
        isAuthenticated,
        isSuperAdmin,
        login,
        fetchMe,
        logout,
    };
}
