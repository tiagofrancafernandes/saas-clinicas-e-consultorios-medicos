export default defineNuxtRouteMiddleware(async (to) => {
    if (to.path === '/login') {
        return;
    }

    const { isAuthenticated, isAllowedPatient, user, fetchMe, logout } = usePatientAuth();

    if (!isAuthenticated.value) {
        return;
    }

    if (!user.value) {
        await fetchMe();
    }

    if (!isAllowedPatient.value) {
        await logout();
        return navigateTo('/login');
    }
});
