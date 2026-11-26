import type { FilterCondition, FilterField, FilterOperator } from '~/types/table';

const TEXT_OPERATORS: FilterOperator[] = [
    { value: 'contains', label: 'contém' },
    { value: 'equals', label: 'igual a' },
    { value: 'starts_with', label: 'começa com' },
    { value: 'is_empty', label: 'está vazio' },
    { value: 'is_not_empty', label: 'não está vazio' },
];

const SELECT_OPERATORS: FilterOperator[] = [
    { value: 'equals', label: 'igual a' },
    { value: 'not_equals', label: 'diferente de' },
];

const NUMBER_DATE_OPERATORS: FilterOperator[] = [
    { value: 'equals', label: 'igual a' },
    { value: 'gt', label: 'maior que' },
    { value: 'lt', label: 'menor que' },
];

export function getOperatorsForField(field?: FilterField): FilterOperator[] {
    if (!field) return TEXT_OPERATORS;
    if (field.type === 'select' || field.type === 'boolean') return SELECT_OPERATORS;
    if (field.type === 'number' || field.type === 'date') return NUMBER_DATE_OPERATORS;
    return TEXT_OPERATORS;
}

export function useDataTable<T extends Record<string, any>>(
    source: Ref<T[]>,
    fields: FilterField[],
    defaultSortKey: string,
) {
    const search = ref('');
    const sortBy = ref(defaultSortKey);
    const sortDirection = ref<'asc' | 'desc'>('asc');
    const currentPage = ref(1);
    const perPage = ref(10);
    const selectedIds = ref<Array<string | number>>([]);

    const filterPanelOpen = ref(false);
    const draftFilters = ref<FilterCondition[]>([]);
    const appliedFilters = ref<FilterCondition[]>([]);

    function createEmptyFilter(): FilterCondition {
        const firstField = fields[0];
        const operators = getOperatorsForField(firstField);

        return {
            id: `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
            field: firstField?.key ?? '',
            operator: operators[0]?.value ?? 'contains',
            value: '',
        };
    }

    function toggleFilterPanel(): void {
        filterPanelOpen.value = !filterPanelOpen.value;
        if (filterPanelOpen.value && draftFilters.value.length === 0 && fields.length > 0) {
            draftFilters.value = [createEmptyFilter()];
        }
    }

    function applyFilters(): void {
        appliedFilters.value = draftFilters.value
            .filter((f) => ['is_empty', 'is_not_empty'].includes(f.operator) || String(f.value).trim() !== '')
            .map((f) => ({ ...f }));
        currentPage.value = 1;
        filterPanelOpen.value = false;
    }

    function resetFilters(): void {
        draftFilters.value = fields.length > 0 ? [createEmptyFilter()] : [];
        appliedFilters.value = [];
        currentPage.value = 1;
    }

    function removeAppliedFilter(id: string): void {
        appliedFilters.value = appliedFilters.value.filter((f) => f.id !== id);
        draftFilters.value = draftFilters.value.filter((f) => f.id !== id);
        currentPage.value = 1;
    }

    function sort(key: string): void {
        if (sortBy.value === key) {
            sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
        } else {
            sortBy.value = key;
            sortDirection.value = 'asc';
        }
        currentPage.value = 1;
    }

    const filteredRecords = computed(() => {
        const term = search.value.trim().toLowerCase();

        const matched = source.value.filter((item) => {
            if (term) {
                const matchesSearch = Object.values(item).some((val) =>
                    String(val ?? '').toLowerCase().includes(term),
                );
                if (!matchesSearch) return false;
            }

            return appliedFilters.value.every((cond) => {
                const rawVal = item[cond.field];
                const itemStr = String(rawVal ?? '').toLowerCase();
                const condStr = String(cond.value ?? '').toLowerCase();

                switch (cond.operator) {
                    case 'contains':
                        return itemStr.includes(condStr);
                    case 'equals':
                        return itemStr === condStr;
                    case 'not_equals':
                        return itemStr !== condStr;
                    case 'starts_with':
                        return itemStr.startsWith(condStr);
                    case 'is_empty':
                        return rawVal === null || rawVal === undefined || String(rawVal).trim() === '';
                    case 'is_not_empty':
                        return rawVal !== null && rawVal !== undefined && String(rawVal).trim() !== '';
                    case 'gt':
                        return String(rawVal ?? '') > String(cond.value ?? '');
                    case 'lt':
                        return String(rawVal ?? '') < String(cond.value ?? '');
                    default:
                        return true;
                }
            });
        });

        return [...matched].sort((a, b) => {
            const valA = String(a[sortBy.value] ?? '').toLowerCase();
            const valB = String(b[sortBy.value] ?? '').toLowerCase();
            if (valA === valB) return 0;
            const cmp = valA > valB ? 1 : -1;
            return sortDirection.value === 'asc' ? cmp : -cmp;
        });
    });

    const totalPages = computed(() => Math.max(1, Math.ceil(filteredRecords.value.length / perPage.value)));
    const startIndex = computed(() => (currentPage.value - 1) * perPage.value);
    const paginatedRecords = computed(() =>
        filteredRecords.value.slice(startIndex.value, startIndex.value + perPage.value),
    );

    const pageNumbers = computed(() =>
        Array.from({ length: totalPages.value }, (_, index) => index + 1),
    );

    function toggleSelection(id: string | number): void {
        const index = selectedIds.value.indexOf(id);
        if (index === -1) {
            selectedIds.value.push(id);
        } else {
            selectedIds.value.splice(index, 1);
        }
    }

    function toggleAllOnPage(checked: boolean): void {
        const pageIds = paginatedRecords.value.map((r) => r.id);
        if (checked) {
            const merged = new Set([...selectedIds.value, ...pageIds]);
            selectedIds.value = Array.from(merged);
        } else {
            selectedIds.value = selectedIds.value.filter((id) => !pageIds.includes(id));
        }
    }

    return {
        search,
        sortBy,
        sortDirection,
        currentPage,
        perPage,
        selectedIds,
        filterPanelOpen,
        draftFilters,
        appliedFilters,
        filteredRecords,
        paginatedRecords,
        totalPages,
        startIndex,
        pageNumbers,
        createEmptyFilter,
        toggleFilterPanel,
        applyFilters,
        resetFilters,
        removeAppliedFilter,
        sort,
        toggleSelection,
        toggleAllOnPage,
    };
}
