export default defineNuxtRouteMiddleware(async (to) => {
    if (to.path === '/login') {
        return;
    }

    const { isAuthenticated, isSuperAdmin, user, fetchMe, logout } = useAuth();

    if (!isAuthenticated.value) {
        return;
    }

    if (!user.value) {
        await fetchMe();
    }

    if (!isSuperAdmin.value) {
        await logout();
        return navigateTo('/login');
    }
});
