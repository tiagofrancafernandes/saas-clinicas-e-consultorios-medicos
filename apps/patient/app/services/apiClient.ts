export function useTenantApiClient() {
    const client = useSanctumClient();
    const { user } = usePatientAuth();

    function buildHeaders(extra?: HeadersInit): Record<string, string> {
        const headers: Record<string, string> = {};

        if (user.value?.tenant_id) {
            headers['X-Tenant-ID'] = user.value.tenant_id;
        }

        if (extra && !(extra instanceof Headers) && !Array.isArray(extra)) {
            Object.assign(headers, extra);
        }

        return headers;
    }

    async function request<T>(path: string, options: Parameters<typeof client<T>>[1] = {}): Promise<T> {
        return await client<T>(path, {
            ...options,
            headers: buildHeaders(options?.headers),
        });
    }

    async function fetchBlob(path: string): Promise<Blob> {
        return await client<Blob>(path, {
            method: 'GET',
            responseType: 'blob',
            headers: buildHeaders(),
        });
    }

    return {
        request,
        fetchBlob,
    };
}
