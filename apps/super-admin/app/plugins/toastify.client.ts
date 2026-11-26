import { defineNuxtPlugin } from '#app';
import Vue3Toastify, { type ToastContainerOptions } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

export default defineNuxtPlugin((nuxtApp) => {
    nuxtApp.vueApp.use(Vue3Toastify, {
        autoClose: 3000,
        position: 'top-right',
        theme: 'auto',
        clearOnUrlChange: false,
    } as ToastContainerOptions);
});
