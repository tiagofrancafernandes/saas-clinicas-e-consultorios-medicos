import tailwindcss from '@tailwindcss/vite';

export default defineNuxtConfig({
    compatibilityDate: '2025-07-15',
    devtools: {
        enabled: true,
        timeline: {
            enabled: true,
        },
    },
    components: [{ path: '~/components', pathPrefix: false }],
    modules: ['@nuxt/icon', 'nuxt-auth-sanctum'],
    css: ['~/assets/css/main.css'],
    icon: {
        mode: 'css',
        cssLayer: 'base',
    },
    sanctum: {
        baseUrl: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000/api',
        mode: 'token',
        endpoints: {
            login: '/auth/login',
            logout: '/auth/logout',
            user: '/auth/me',
        },
        redirectIfAuthenticated: true,
        redirectIfUnauthenticated: true,
        redirect: {
            keepRequestedRoute: true,
            onLogin: '/',
            onLogout: '/login',
            onAuthOnly: '/login',
            onGuestOnly: '/',
        },
        globalMiddleware: {
            enabled: true,
            prepend: true,
            allow404WithoutAuth: true,
        },
    },
    runtimeConfig: {
        public: {
            apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000/api',
        },
    },
    vite: {
        plugins: [tailwindcss()],
    },
});
