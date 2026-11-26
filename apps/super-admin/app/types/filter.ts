export type FilterFieldType = 'text' | 'enum' | 'boolean' | 'date' | 'number';

export interface FilterOperator {
    value: string;
    label: string;
}

export interface FilterOption {
    value: string;
    label: string;
}

export interface FilterField {
    key: string;
    label: string;
    type: FilterFieldType;
    operators: FilterOperator[];
    options?: FilterOption[];
}

export interface FilterRule {
    id: string;
    field: string;
    operator: string;
    value: string;
}
