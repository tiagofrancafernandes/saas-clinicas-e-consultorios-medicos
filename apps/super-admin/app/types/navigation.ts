export interface NavChildItem {
    label: string;
    to: string;
    icon?: string;
    badge?: string;
}

export interface NavItem {
    id: string;
    label: string;
    icon: string;
    to?: string;
    badge?: string;
    children?: NavChildItem[];
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface CommandAction {
    id: string;
    label: string;
    description?: string;
    group: string;
    icon: string;
    shortcut?: string;
    to?: string;
    action?: () => void;
}
