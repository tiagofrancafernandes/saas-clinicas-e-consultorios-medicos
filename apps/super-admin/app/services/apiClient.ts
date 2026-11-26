export class ApiError extends Error {
    status: number;
    data: unknown;

    constructor(message: string, status: number, data?: unknown) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.data = data;
    }
}

export async function apiRequest<T>(
    path: string,
    options: {
        method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
        body?: Record<string, unknown> | unknown[];
        query?: Record<string, string | number | boolean | undefined>;
    } = {}
): Promise<T> {
    const config = useRuntimeConfig();
    const token = useCookie<string | null>('sa_token');

    const headers: Record<string, string> = {
        Accept: 'application/json',
    };

    if (token.value) {
        headers.Authorization = `Bearer ${token.value}`;
    }

    try {
        return await $fetch<T>(path, {
            baseURL: config.public.apiBase,
            method: options.method ?? 'GET',
            body: options.body,
            query: options.query,
            headers,
        });
    } catch (error: unknown) {
        const err = error as { status?: number; statusCode?: number; data?: { message?: string } };
        const status = err.status ?? err.statusCode ?? 500;
        if (status === 401 && import.meta.client) {
            token.value = null;
            useState('sa_user').value = null;
            if (useRoute().path !== '/login') {
                await navigateTo('/login');
            }
        }
        const message = err.data?.message || 'Erro inesperado ao comunicar com o servidor.';
        throw new ApiError(message, status, err.data);
    }
}
