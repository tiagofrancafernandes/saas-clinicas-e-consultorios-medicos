import type { AuthUser, LoginPayload } from '~/types/auth';

type SanctumUserPayload = ({ user?: AuthUser } & Partial<AuthUser>) | null;

const ALLOWED_ROLE = 'PATIENT';

export function isUserAllowedInPatientPortal(user: AuthUser | null): boolean {
    if (!user) {
        return false;
    }

    const roles = Array.isArray(user.roles) ? user.roles : [];
    return roles.length === 0 || roles.includes(ALLOWED_ROLE);
}

export function usePatientAuth() {
    const { isAuthenticated, login: sanctumLogin, logout: sanctumLogout, refreshIdentity } = useSanctumAuth();
    const sanctumUser = useSanctumUser<SanctumUserPayload>();
    const loading = useState<boolean>('pt_auth_loading', () => false);

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

    const isAllowedPatient = computed(() => isUserAllowedInPatientPortal(user.value));

    async function login(payload: LoginPayload): Promise<void> {
        loading.value = true;
        try {
            await sanctumLogin(payload as unknown as Record<string, unknown>);
            if (!user.value) {
                await refreshIdentity();
            }
            if (!isAllowedPatient.value) {
                await sanctumLogout();
                throw new Error('Acesso exclusivo para pacientes ou contas sem perfil administrativo.');
            }
        } catch (error: unknown) {
            const err = error as { data?: { message?: string }; message?: string };
            throw new Error(err.data?.message || err.message || 'E-mail ou senha inválidos.');
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
        isAllowedPatient,
        login,
        fetchMe,
        logout,
    };
}
