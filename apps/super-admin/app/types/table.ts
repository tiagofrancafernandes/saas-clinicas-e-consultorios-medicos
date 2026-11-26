export type SortDirection = 'asc' | 'desc';

export interface TableColumn {
    key: string;
    label: string;
    sortable?: boolean;
    align?: 'left' | 'right' | 'center';
}

export interface SortState {
    by: string;
    direction: SortDirection;
}

export interface StatItem {
    label: string;
    value: string | number;
    change?: string;
    positive?: boolean;
}
