const STORAGE_KEY = 'sa_sidebar_collapsed';

export function useSidebarState() {
    const mobileOpen = useState<boolean>('sa_sidebar_mobile_open', () => false);
    const collapsed = useState<boolean>('sa_sidebar_collapsed', () => false);
    const hovered = useState<boolean>('sa_sidebar_hovered', () => false);
    const initialized = useState<boolean>('sa_sidebar_initialized', () => false);

    const isVisuallyExpanded = computed(() => !collapsed.value || hovered.value || mobileOpen.value);

    function initFromStorage(): void {
        if (initialized.value || !import.meta.client) {
            return;
        }
        initialized.value = true;
        try {
            collapsed.value = localStorage.getItem(STORAGE_KEY) === 'true';
        } catch {
            collapsed.value = false;
        }
    }

    function toggleCollapsed(): void {
        collapsed.value = !collapsed.value;
        if (!collapsed.value) {
            hovered.value = false;
        }
        if (import.meta.client) {
            try {
                localStorage.setItem(STORAGE_KEY, String(collapsed.value));
            } catch {
                // Ignore storage quota/privacy mode errors
            }
        }
    }

    function setHovered(value: boolean): void {
        if (!collapsed.value) {
            hovered.value = false;
            return;
        }
        hovered.value = value;
    }

    return {
        mobileOpen,
        collapsed,
        hovered,
        isVisuallyExpanded,
        initFromStorage,
        toggleCollapsed,
        setHovered,
    };
}
