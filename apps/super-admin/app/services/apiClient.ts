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
    const client = useSanctumClient();

    try {
        return await client<T>(path, {
            method: options.method ?? 'GET',
            body: options.body,
            query: options.query,
        });
    } catch (error: unknown) {
        const err = error as { status?: number; statusCode?: number; data?: { message?: string } };
        const status = err.status ?? err.statusCode ?? 500;
        const message = err.data?.message || 'Erro inesperado ao comunicar com o servidor.';
        throw new ApiError(message, status, err.data);
    }
}
