import type { FilterField, FilterRule } from '~/types/filter';
import type { SortDirection } from '~/types/table';

interface UseDataTableOptions<T extends Record<string, unknown>> {
    records: Ref<T[]>;
    searchKeys: (keyof T | string)[];
    filterFields: FilterField[];
    defaultSortBy?: string;
    defaultSortDirection?: SortDirection;
    initialPerPage?: number;
}

function getNestedValue(record: Record<string, unknown>, path: string): unknown {
    return path.split('.').reduce<unknown>((acc, part) => {
        if (acc && typeof acc === 'object' && part in acc) {
            return (acc as Record<string, unknown>)[part];
        }
        return undefined;
    }, record);
}

export function useDataTable<T extends Record<string, unknown>>(options: UseDataTableOptions<T>) {
    const search = ref('');
    const currentPage = ref(1);
    const perPage = ref(options.initialPerPage ?? 10);
    const sortBy = ref(options.defaultSortBy ?? 'created_at');
    const sortDirection = ref<SortDirection>(options.defaultSortDirection ?? 'desc');
    const selected = ref<(string | number)[]>([]);
    const filterPanelOpen = ref(false);
    const draftFilters = ref<FilterRule[]>([]);
    const appliedFilters = ref<FilterRule[]>([]);

    function getField(fieldKey: string): FilterField | undefined {
        return options.filterFields.find((item) => item.key === fieldKey) ?? options.filterFields[0];
    }

    function matchesFilter(record: T, rule: FilterRule): boolean {
        const field = getField(rule.field);
        if (!field) {
            return true;
        }

        const actual = getNestedValue(record, rule.field);
        const expected = rule.value;

        if (rule.operator === 'is_empty') {
            return actual === null || actual === undefined || actual === '';
        }
        if (rule.operator === 'is_not_empty') {
            return actual !== null && actual !== undefined && actual !== '';
        }
        if (expected === '') {
            return true;
        }

        if (field.type === 'text') {
            const first = String(actual ?? '').toLowerCase();
            const second = String(expected).toLowerCase();
            const map: Record<string, boolean> = {
                contains: first.includes(second),
                not_contains: !first.includes(second),
                equals: first === second,
                not_equals: first !== second,
                starts_with: first.startsWith(second),
                ends_with: first.endsWith(second),
            };
            return map[rule.operator] ?? true;
        }

        if (field.type === 'boolean') {
            return Boolean(actual) === (expected === 'true');
        }

        if (field.type === 'number') {
            const first = Number(actual);
            const second = Number(expected);
            const map: Record<string, boolean> = {
                equals: first === second,
                gt: first > second,
                gte: first >= second,
                lt: first < second,
                lte: first <= second,
            };
            return map[rule.operator] ?? true;
        }

        const first = String(actual ?? '');
        const map: Record<string, boolean> = {
            equals: first === expected,
            not_equals: first !== expected,
            gt: first > expected,
            gte: first >= expected,
            lt: first < expected,
            lte: first <= expected,
        };
        return map[rule.operator] ?? true;
    }

    const filteredRecords = computed<T[]>(() => {
        const term = search.value.toLowerCase().trim();

        return [...options.records.value]
            .filter((record) => {
                if (!term) {
                    return true;
                }
                return options.searchKeys.some((key) => {
                    const value = getNestedValue(record, String(key));
                    return String(value ?? '')
                        .toLowerCase()
                        .includes(term);
                });
            })
            .filter((record) => appliedFilters.value.every((rule) => matchesFilter(record, rule)))
            .sort((a, b) => {
                const first = getNestedValue(a, sortBy.value);
                const second = getNestedValue(b, sortBy.value);
                const comparison =
                    typeof first === 'number' && typeof second === 'number'
                        ? first - second
                        : String(first ?? '').localeCompare(String(second ?? ''));
                return sortDirection.value === 'asc' ? comparison : -comparison;
            });
    });

    const totalPages = computed(() => Math.max(1, Math.ceil(filteredRecords.value.length / perPage.value)));
    const startIndex = computed(() => (currentPage.value - 1) * perPage.value);
    const paginatedRecords = computed(() =>
        filteredRecords.value.slice(startIndex.value, startIndex.value + perPage.value)
    );

    const pageNumbers = computed(() => {
        const total = Math.ceil(filteredRecords.value.length / perPage.value);
        if (total <= 0) {
            return [];
        }
        if (total <= 5) {
            return Array.from({ length: total }, (_, idx) => idx + 1);
        }

        let start = Math.max(1, currentPage.value - 2);
        const end = Math.min(total, start + 4);
        start = Math.max(1, end - 4);
        return Array.from({ length: end - start + 1 }, (_, idx) => start + idx);
    });

    function toggleSort(columnKey: string): void {
        if (sortBy.value === columnKey) {
            sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
        } else {
            sortBy.value = columnKey;
            sortDirection.value = 'asc';
        }
        currentPage.value = 1;
    }

    function applyFilters(rules: FilterRule[]): void {
        appliedFilters.value = rules.map((rule) => ({ ...rule }));
        draftFilters.value = rules.map((rule) => ({ ...rule }));
        currentPage.value = 1;
        filterPanelOpen.value = false;
    }

    function removeAppliedFilter(id: string): void {
        appliedFilters.value = appliedFilters.value.filter((rule) => rule.id !== id);
        draftFilters.value = draftFilters.value.filter((rule) => rule.id !== id);
        currentPage.value = 1;
    }

    function resetFilters(): void {
        appliedFilters.value = [];
        draftFilters.value = [];
        currentPage.value = 1;
    }

    function resetAll(): void {
        resetFilters();
        search.value = '';
        sortBy.value = options.defaultSortBy ?? 'created_at';
        sortDirection.value = options.defaultSortDirection ?? 'desc';
        currentPage.value = 1;
        selected.value = [];
    }

    watch(search, () => {
        currentPage.value = 1;
    });

    watch(perPage, () => {
        currentPage.value = 1;
    });

    return {
        search,
        currentPage,
        perPage,
        sortBy,
        sortDirection,
        selected,
        filterPanelOpen,
        draftFilters,
        appliedFilters,
        filteredRecords,
        totalPages,
        startIndex,
        paginatedRecords,
        pageNumbers,
        toggleSort,
        applyFilters,
        removeAppliedFilter,
        resetFilters,
        resetAll,
    };
}
