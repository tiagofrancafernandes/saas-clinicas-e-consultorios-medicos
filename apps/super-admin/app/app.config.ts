import type { NuxtApp } from '#app';

const COOKIE_KEY = 'super_admin_sanctum_token';

export default defineAppConfig({
    sanctum: {
        tokenStorage: {
            get: async (app: NuxtApp) =>
                app.runWithContext(() => useCookie<string | null>(COOKIE_KEY).value ?? undefined),
            set: async (app: NuxtApp, token?: string) =>
                app.runWithContext(() => {
                    useCookie<string | null>(COOKIE_KEY).value = token ?? null;
                }),
        },
    },
});
