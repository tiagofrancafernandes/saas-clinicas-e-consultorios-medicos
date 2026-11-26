import { useAuthStore } from '~/stores/auth';

export default defineNuxtRouteMiddleware((to) => {
    if (to.path.startsWith('/@')) {
        return;
    }

    const auth = useAuthStore();

    if (!auth.isAuthenticated) {
        return;
    }

    const requiredPermission = to.meta.permission as string | undefined;
    if (requiredPermission && !auth.can(requiredPermission)) {
        return navigateTo('/');
    }
});
