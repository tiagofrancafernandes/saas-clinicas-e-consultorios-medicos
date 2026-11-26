export type FilterFieldType = 'text' | 'number' | 'date' | 'select' | 'boolean';

export interface FilterOption {
    label: string;
    value: string | boolean;
}

export interface FilterField {
    key: string;
    label: string;
    type: FilterFieldType;
    options?: FilterOption[];
}

export interface FilterOperator {
    value: string;
    label: string;
}

export interface FilterCondition {
    id: string;
    field: string;
    operator: string;
    value: string | boolean;
    valueEnd?: string;
}

export interface TableColumn {
    key: string;
    label: string;
    sortable?: boolean;
    align?: 'left' | 'right';
}

export interface SummaryStat {
    label: string;
    value: string | number;
    change?: string;
    positive?: boolean;
}
