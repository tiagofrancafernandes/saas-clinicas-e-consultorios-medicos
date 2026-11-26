import tailwindcss from '@tailwindcss/vite';

const apiBase = (process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000').replace(/\/+$/, '');

export default defineNuxtConfig({
    compatibilityDate: '2025-07-15',
    devtools: {
        enabled: true,
        timeline: {
            enabled: true,
        },
    },
    modules: ['@nuxt/icon', 'nuxt-auth-sanctum', '@pinia/nuxt'],
    css: ['~/assets/css/main.css'],
    sanctum: {
        baseUrl: apiBase,
        mode: 'token',
        userStateKey: 'sanctum.user.identity',
        endpoints: {
            login: '/api/auth/login',
            logout: '/api/auth/logout',
            user: '/api/auth/me',
        },
        redirect: {
            keepRequestedRoute: true,
            onLogin: '/',
            onLogout: '/login',
            onAuthOnly: '/login',
            onGuestOnly: '/',
        },
        globalMiddleware: {
            enabled: true,
        },
    },
    runtimeConfig: {
        public: {
            apiBase,
            // Acho que não faz sentido esse defaultTenantId.
            // defaultTenantId: process.env.NUXT_PUBLIC_DEFAULT_TENANT_ID || 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
        },
    },
    vite: {
        plugins: [tailwindcss()],
    },
});
