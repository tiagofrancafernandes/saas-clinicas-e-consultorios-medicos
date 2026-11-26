export async function apiRequest<T>(
    path: string,
    options: {
        method?: 'GET' | 'POST' | 'PUT' | 'DELETE';
        body?: Record<string, unknown>;
        token?: string | null;
        tenantId?: string | null;
    } = {},
): Promise<T> {
    const client = useSanctumClient();
    const headers: Record<string, string> = {
        Accept: 'application/json',
    };

    if (options.tenantId) {
        headers['X-Tenant-ID'] = options.tenantId;
    }

    if (options.token) {
        headers.Authorization = `Bearer ${options.token}`;
    }

    return await client<T>(path, {
        method: options.method || 'GET',
        headers,
        body: options.body,
    });
}
