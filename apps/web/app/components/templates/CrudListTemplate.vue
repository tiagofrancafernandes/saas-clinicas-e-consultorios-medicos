<script setup lang="ts">
import type { SummaryStat } from '~/types/table';

interface Props {
    title: string;
    description: string;
    tableTitle: string;
    tableDescription: string;
    searchPlaceholder?: string;
    stats?: SummaryStat[];
    table: any;
    fields: any[];
}

withDefaults(defineProps<Props>(), {
    searchPlaceholder: 'Buscar registros...',
    stats: () => [],
});
</script>

<template>
    <div class="space-y-5">
        <!-- Top Page Header with Action Buttons -->
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div>
                <h1 class="text-[19px] font-normal tracking-tight text-gray-900">{{ title }}</h1>
                <p class="mt-0.5 text-[12px] text-gray-500">{{ description }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <slot name="header-actions" />
            </div>
        </div>

        <!-- Optional Summary Stats -->
        <div v-if="stats.length > 0" class="grid grid-cols-2 gap-3 xl:grid-cols-4">
            <div
                v-for="stat in stats"
                :key="stat.label"
                class="rounded border border-gray-200 bg-white px-3.5 py-3"
            >
                <div class="text-[11px] text-gray-500">{{ stat.label }}</div>
                <div class="mt-1.5 flex items-end justify-between">
                    <div class="text-[20px] font-normal tracking-tight tabular-nums text-gray-900">
                        {{ stat.value }}
                    </div>
                    <span
                        v-if="stat.change"
                        class="text-[10px]"
                        :class="stat.positive ? 'text-emerald-600' : 'text-gray-400'"
                    >
                        {{ stat.change }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <section class="rounded border border-gray-200 bg-white">
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 lg:px-5">
                <div>
                    <h2 class="text-[13px] font-normal text-gray-900">{{ tableTitle }}</h2>
                    <p class="mt-0.5 text-[11px] text-gray-400">{{ tableDescription }}</p>
                </div>
                <slot name="card-extra" />
            </div>

            <!-- Search e Filter Toolbar -->
            <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-4 py-2.5 lg:px-5">
                <div class="relative min-w-[180px] max-w-[320px] flex-1">
                    <Icon name="lucide:search" class="absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-gray-400" />
                    <input
                        v-model="table.search.value"
                        type="search"
                        :placeholder="searchPlaceholder"
                        class="h-8 w-full rounded border border-gray-200 bg-white pl-8 pr-2.5 text-[12px] text-gray-800 placeholder:text-gray-400 focus:border-gray-400"
                        @input="table.currentPage.value = 1"
                    />
                </div>

                <button
                    id="btn-toggle-advanced-filters"
                    type="button"
                    class="flex h-8 items-center gap-1.5 rounded border px-2.5 text-[12px] transition-colors"
                    :class="
                        table.filterPanelOpen.value || table.appliedFilters.value.length
                            ? 'border-teal-300 bg-teal-50 text-teal-700'
                            : 'border-gray-200 text-gray-600 hover:bg-gray-50'
                    "
                    @click="table.toggleFilterPanel()"
                >
                    <Icon name="lucide:filter" class="size-3.5" />
                    <span>Filtros</span>
                    <span
                        v-if="table.appliedFilters.value.length"
                        class="rounded bg-teal-100 px-1.5 text-[10px] text-teal-700"
                    >
                        {{ table.appliedFilters.value.length }}
                    </span>
                </button>

                <button
                    type="button"
                    title="Resetar filtros"
                    class="flex size-8 items-center justify-center rounded border border-gray-200 text-gray-500 hover:bg-gray-50"
                    @click="table.search.value = ''; table.resetFilters()"
                >
                    <Icon name="lucide:rotate-ccw" class="size-3.5" />
                </button>

                <div class="ml-auto hidden items-center gap-1.5 text-[11px] text-gray-400 md:flex">
                    <span>Ordenação:</span>
                    <span class="text-gray-600">
                        {{ table.sortBy.value }} ({{ table.sortDirection.value === 'asc' ? 'A-Z' : 'Z-A' }})
                    </span>
                </div>
            </div>

            <!-- Advanced Filter Builder -->
            <TableAdvancedFilterBuilder
                :open="table.filterPanelOpen.value"
                :fields="fields"
                :draft-filters="table.draftFilters.value"
                :applied-filters="table.appliedFilters.value"
                :create-empty-filter="table.createEmptyFilter"
                @update:draft-filters="table.draftFilters.value = $event"
                @close="table.filterPanelOpen.value = false"
                @apply="table.applyFilters()"
                @reset="table.resetFilters()"
                @remove-applied="table.removeAppliedFilter($event)"
            />

            <!-- Table Body Slot -->
            <slot />
        </section>
    </div>
</template>
