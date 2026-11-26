<script setup lang="ts">
import type { FilterField, FilterRule } from '~/types/filter';
import type { SortDirection, StatItem, TableColumn } from '~/types/table';

interface Props {
    title: string;
    subtitle?: string;
    sectionTitle?: string;
    sectionSubtitle?: string;
    searchPlaceholder?: string;
    stats?: StatItem[];
    columns: TableColumn[];
    filterFields: FilterField[];
    records: Record<string, unknown>[];
    totalCount: number;
    currentPage: number;
    perPage: number;
    totalPages: number;
    startIndex: number;
    pageNumbers: number[];
    sortBy: string;
    sortDirection: SortDirection;
    search: string;
    filterPanelOpen: boolean;
    draftFilters: FilterRule[];
    appliedFilters: FilterRule[];
    loading?: boolean;
    selectable?: boolean;
    selected?: (string | number)[];
    rowKey?: string;
}

const props = withDefaults(defineProps<Props>(), {
    subtitle: undefined,
    sectionTitle: undefined,
    sectionSubtitle: undefined,
    searchPlaceholder: 'Buscar...',
    stats: () => [],
    loading: false,
    selectable: false,
    selected: () => [],
    rowKey: 'id',
});

const emit = defineEmits<{
    'update:search': [value: string];
    'update:filterPanelOpen': [value: boolean];
    'update:draftFilters': [rules: FilterRule[]];
    'update:currentPage': [page: number];
    'update:perPage': [perPage: number];
    'update:selected': [ids: (string | number)[]];
    applyFilters: [rules: FilterRule[]];
    removeAppliedFilter: [id: string];
    resetFilters: [];
    resetAll: [];
    sort: [key: string];
}>();

const sortLabel = computed(() => {
    const column = props.columns.find((item) => item.key === props.sortBy);
    return `${column?.label ?? props.sortBy} (${props.sortDirection === 'asc' ? 'Crescente' : 'Decrescente'})`;
});
</script>

<template>
    <div>
        <!-- Page Heading & Top Actions -->
        <div class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div>
                <h1 class="text-[20px] font-medium tracking-tight text-gray-900">{{ props.title }}</h1>
                <p v-if="props.subtitle" class="mt-0.5 text-[12px] font-light text-gray-500">
                    {{ props.subtitle }}
                </p>
            </div>
            <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
                <slot name="actions" />
            </div>
        </div>

        <!-- Optional Summary Stats -->
        <div v-if="props.stats.length > 0" class="mb-5 grid grid-cols-2 gap-3 xl:grid-cols-4">
            <div
                v-for="stat in props.stats"
                :key="stat.label"
                class="rounded-md border border-gray-200 bg-white px-4 py-3"
            >
                <div class="text-[11px] font-light text-gray-500">{{ stat.label }}</div>
                <div class="mt-1.5 flex items-end justify-between">
                    <div class="text-[21px] font-normal tracking-tight tabular-nums text-gray-900">
                        {{ stat.value }}
                    </div>
                    <span
                        v-if="stat.change"
                        :class="['mb-0.5 text-[11px] font-normal', stat.positive ? 'text-green-600' : 'text-gray-400']"
                    >
                        {{ stat.change }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <section class="rounded-md border border-gray-200 bg-white">
            <div v-if="props.sectionTitle" class="border-b border-gray-100 px-4 py-3.5 lg:px-5">
                <h2 class="text-[13px] font-medium text-gray-900">{{ props.sectionTitle }}</h2>
                <p v-if="props.sectionSubtitle" class="mt-0.5 text-[11px] font-light text-gray-400">
                    {{ props.sectionSubtitle }}
                </p>
            </div>

            <!-- Toolbar -->
            <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-4 py-2.5 lg:px-5">
                <div class="relative min-w-[180px] max-w-[320px] flex-1">
                    <Icon
                        name="lucide:search"
                        class="absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-gray-400"
                    />
                    <input
                        :value="props.search"
                        type="search"
                        :placeholder="props.searchPlaceholder"
                        class="h-8 w-full rounded-md border border-gray-200 bg-white pl-8 pr-3 text-[12px] font-light text-gray-900 placeholder:text-gray-400 outline-none hover:border-gray-300 focus:border-gray-400"
                        @input="emit('update:search', ($event.target as HTMLInputElement).value)"
                    />
                </div>

                <UiButton
                    v-if="props.filterFields.length > 0"
                    :variant="props.filterPanelOpen || props.appliedFilters.length ? 'outline' : 'outline'"
                    size="sm"
                    icon="lucide:filter"
                    :class="
                        props.filterPanelOpen || props.appliedFilters.length
                            ? 'border-blue-300 bg-blue-50 text-blue-700 hover:bg-blue-50'
                            : ''
                    "
                    @click="emit('update:filterPanelOpen', !props.filterPanelOpen)"
                >
                    Filtros
                    <span
                        v-if="props.appliedFilters.length > 0"
                        class="rounded bg-blue-100 px-1.5 text-[10px] text-blue-700"
                    >
                        {{ props.appliedFilters.length }}
                    </span>
                </UiButton>

                <button
                    type="button"
                    title="Resetar filtros e ordenação"
                    class="flex size-8 cursor-pointer items-center justify-center rounded-md border border-gray-200 text-gray-500 transition-colors hover:bg-gray-50"
                    @click="emit('resetAll')"
                >
                    <Icon name="lucide:rotate-ccw" class="size-3.5" />
                </button>

                <div class="ml-auto hidden items-center gap-1.5 text-[11px] font-light text-gray-400 md:flex">
                    <span>Ordenação:</span>
                    <span class="font-normal text-gray-600">{{ sortLabel }}</span>
                </div>
            </div>

            <!-- Advanced Filter Panel -->
            <AdvancedFilterBuilder
                v-if="props.filterFields.length > 0"
                :open="props.filterPanelOpen"
                :fields="props.filterFields"
                :model-value="props.draftFilters"
                @update:model-value="emit('update:draftFilters', $event)"
                @close="emit('update:filterPanelOpen', false)"
                @apply="emit('applyFilters', $event)"
                @reset="emit('resetFilters')"
            />

            <!-- Applied Filter Pills -->
            <AppliedFilterPills
                :filters="props.appliedFilters"
                :fields="props.filterFields"
                @remove="emit('removeAppliedFilter', $event)"
                @reset="emit('resetFilters')"
            />

            <!-- DataTable -->
            <DataTable
                :columns="props.columns"
                :records="props.records"
                :total-count="props.totalCount"
                :current-page="props.currentPage"
                :per-page="props.perPage"
                :total-pages="props.totalPages"
                :start-index="props.startIndex"
                :page-numbers="props.pageNumbers"
                :sort-by="props.sortBy"
                :sort-direction="props.sortDirection"
                :loading="props.loading"
                :selectable="props.selectable"
                :selected="props.selected"
                :row-key="props.rowKey"
                @sort="emit('sort', $event)"
                @update:current-page="emit('update:currentPage', $event)"
                @update:per-page="emit('update:perPage', $event)"
                @update:selected="emit('update:selected', $event)"
            >
                <template v-for="col in props.columns" :key="col.key" #[`cell-${col.key}`]="slotProps">
                    <slot :name="`cell-${col.key}`" v-bind="slotProps">
                        {{ slotProps.value ?? '-' }}
                    </slot>
                </template>

                <template v-if="$slots['row-actions']" #actions="slotProps">
                    <slot name="row-actions" v-bind="slotProps" />
                </template>
            </DataTable>
        </section>
    </div>
</template>
