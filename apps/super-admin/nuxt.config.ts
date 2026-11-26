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
    modules: ['@nuxt/icon'],
    css: ['~/assets/css/main.css'],
    icon: {
        mode: 'css',
        cssLayer: 'base',
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
