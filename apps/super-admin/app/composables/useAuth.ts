import type { AuthUser, LoginPayload } from '~/types/auth';

type SanctumUserPayload = ({ user?: AuthUser } e Partial<AuthUser>) | null;

export function useAuth() {
    const { isAuthenticated, login: sanctumLogin, logout: sanctumLogout, refreshIdentity } = useSanctumAuth();
    const sanctumUser = useSanctumUser<SanctumUserPayload>();
    const loading = useState<boolean>('sa_auth_loading', () => false);

    const user = computed<AuthUser | null>(() => {
        const raw = sanctumUser.value;
        if (!raw) {
            return null;
        }
        if ('user' in raw && raw.user) {
            return raw.user;
        }
        if ('id' in raw && 'roles' in raw) {
            return raw as AuthUser;
        }
        return null;
    });

    const isSuperAdmin = computed(() => user.value?.roles?.includes('SUPER_ADMIN') ?? false);

    async function login(payload: LoginPayload): Promise<void> {
        loading.value = true;
        try {
            await sanctumLogin(payload as unknown as Record<string, unknown>);
            if (!user.value) {
                await refreshIdentity();
            }
            if (!isSuperAdmin.value) {
                await sanctumLogout();
                throw new Error('Acesso restrito a Super Administradores.');
            }
        } catch (error: unknown) {
            const err = error as { data?: { message?: string }; message?: string };
            throw new Error(err.data?.message || err.message || 'Credenciais inválidas.');
        } finally {
            loading.value = false;
        }
    }

    async function fetchMe(): Promise<AuthUser | null> {
        if (!isAuthenticated.value) {
            return null;
        }
        await refreshIdentity();
        return user.value;
    }

    async function logout(): Promise<void> {
        await sanctumLogout();
    }

    return {
        user,
        loading,
        isAuthenticated,
        isSuperAdmin,
        login,
        fetchMe,
        logout,
    };
}
