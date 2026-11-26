export default defineNuxtRouteMiddleware(async (to) => {
    if (to.path.startsWith('/@')) {
        return;
    }

    const { isAuthenticated, isSuperAdmin, token, user, fetchMe } = useAuth();

    if (to.path === '/login') {
        if (isAuthenticated.value && !user.value) {
            await fetchMe();
        }
        if (isAuthenticated.value && isSuperAdmin.value) {
            return navigateTo('/');
        }
        return;
    }

    if (!isAuthenticated.value) {
        return navigateTo('/login');
    }

    if (!user.value) {
        await fetchMe();
    }

    if (!user.value || !isSuperAdmin.value) {
        token.value = null;
        user.value = null;
        return navigateTo('/login');
    }
});
