<script setup lang="ts" generic="T extends Record<string, unknown>">
import type { SortDirection, TableColumn } from '~/types/table';

interface Props {
    columns: TableColumn[];
    records: T[];
    totalCount: number;
    currentPage: number;
    perPage: number;
    totalPages: number;
    startIndex: number;
    pageNumbers: number[];
    sortBy: string;
    sortDirection: SortDirection;
    loading?: boolean;
    selectable?: boolean;
    selected?: (string | number)[];
    rowKey?: string;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
    selectable: false,
    selected: () => [],
    rowKey: 'id',
});

const emit = defineEmits<{
    sort: [key: string];
    'update:currentPage': [page: number];
    'update:perPage': [perPage: number];
    'update:selected': [ids: (string | number)[]];
}>();

function getRowId(record: T): string | number {
    return (record[props.rowKey] as string | number) ?? '';
}

const allVisibleSelected = computed(
    () => props.records.length > 0 && props.records.every((record) => props.selected.includes(getRowId(record)))
);

function toggleAll(event: Event): void {
    const checked = (event.target as HTMLInputElement).checked;
    const visibleIds = props.records.map((record) => getRowId(record));
    if (checked) {
        emit('update:selected', [...new Set([...props.selected, ...visibleIds])]);
        return;
    }
    emit(
        'update:selected',
        props.selected.filter((id) => !visibleIds.includes(id))
    );
}

function toggleRow(id: string | number): void {
    if (props.selected.includes(id)) {
        emit(
            'update:selected',
            props.selected.filter((item) => item !== id)
        );
        return;
    }
    emit('update:selected', [...props.selected, id]);
}

function onPerPageChange(event: Event): void {
    const value = Number((event.target as HTMLSelectElement).value);
    emit('update:perPage', value);
}
</script>

<template>
    <div>
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th v-if="props.selectable" class="w-10 px-4 py-2.5 lg:px-5">
                            <input
                                type="checkbox"
                                :checked="allVisibleSelected"
                                class="size-[14px] cursor-pointer accent-blue-600"
                                @change="toggleAll"
                            />
                        </th>

                        <th
                            v-for="column in props.columns"
                            :key="column.key"
                            :class="[
                                'px-4 py-2.5 text-[11px] font-medium select-none',
                                column.sortable !== false ? 'cursor-pointer hover:text-gray-900' : '',
                                column.align === 'right'
                                    ? 'text-right'
                                    : column.align === 'center'
                                      ? 'text-center'
                                      : '',
                            ]"
                            @click="column.sortable !== false && emit('sort', column.key)"
                        >
                            <div
                                :class="[
                                    'flex items-center gap-1',
                                    column.align === 'right'
                                        ? 'justify-end'
                                        : column.align === 'center'
                                          ? 'justify-center'
                                          : '',
                                ]"
                            >
                                <span>{{ column.label }}</span>
                                <Icon
                                    v-if="column.sortable !== false"
                                    :name="
                                        props.sortBy === column.key && props.sortDirection === 'asc'
                                            ? 'lucide:chevron-up'
                                            : 'lucide:chevron-down'
                                    "
                                    :class="['size-3', props.sortBy === column.key ? 'text-blue-600' : 'text-gray-300']"
                                />
                            </div>
                        </th>

                        <th v-if="$slots.actions" class="px-4 py-2.5 text-right text-[11px] font-medium lg:px-5">
                            Ações
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    <tr v-if="props.loading">
                        <td
                            :colspan="props.columns.length + (props.selectable ? 1 : 0) + ($slots.actions ? 1 : 0)"
                            class="py-12 text-center"
                        >
                            <Icon name="lucide:loader-2" class="mx-auto size-5 animate-spin text-blue-600" />
                            <div class="mt-2 text-[12px] font-light text-gray-400">Carregando registros...</div>
                        </td>
                    </tr>

                    <template v-else-if="props.records.length > 0">
                        <tr
                            v-for="record in props.records"
                            :key="String(getRowId(record))"
                            :class="[
                                'transition-colors hover:bg-gray-50/70',
                                props.selected.includes(getRowId(record)) ? 'bg-blue-50/40' : '',
                            ]"
                        >
                            <td v-if="props.selectable" class="px-4 py-2.5 lg:px-5">
                                <input
                                    type="checkbox"
                                    :checked="props.selected.includes(getRowId(record))"
                                    class="size-[14px] cursor-pointer accent-blue-600"
                                    @change="toggleRow(getRowId(record))"
                                />
                            </td>

                            <td
                                v-for="column in props.columns"
                                :key="column.key"
                                :class="[
                                    'px-4 py-2.5 text-[12px] font-light text-gray-700',
                                    column.align === 'right'
                                        ? 'text-right'
                                        : column.align === 'center'
                                          ? 'text-center'
                                          : '',
                                ]"
                            >
                                <slot :name="`cell-${column.key}`" :record="record" :value="record[column.key]">
                                    {{ record[column.key] ?? '-' }}
                                </slot>
                            </td>

                            <td v-if="$slots.actions" class="px-4 py-2.5 text-right lg:px-5">
                                <div class="flex items-center justify-end gap-1">
                                    <slot name="actions" :record="record" />
                                </div>
                            </td>
                        </tr>
                    </template>

                    <tr v-else>
                        <td
                            :colspan="props.columns.length + (props.selectable ? 1 : 0) + ($slots.actions ? 1 : 0)"
                            class="py-14 text-center"
                        >
                            <div
                                class="mx-auto mb-2.5 flex size-9 items-center justify-center rounded-full bg-gray-100 text-gray-400"
                            >
                                <Icon name="lucide:search" class="size-4" />
                            </div>
                            <div class="text-[12px] font-normal text-gray-700">Nenhum registro encontrado</div>
                            <div class="mt-0.5 text-[11px] font-light text-gray-400">
                                Tente ajustar a busca ou os filtros aplicados.
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <div
            class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-4 py-2.5 sm:flex-row lg:px-5"
        >
            <div class="text-[11px] font-light text-gray-500">
                Exibindo
                <span class="font-normal text-gray-800">{{ props.totalCount ? props.startIndex + 1 : 0 }}</span>
                a
                <span class="font-normal text-gray-800">
                    {{ Math.min(props.startIndex + props.perPage, props.totalCount) }}
                </span>
                de
                <span class="font-normal text-gray-800">{{ props.totalCount }}</span>
                resultados
                <span v-if="props.selected.length > 0" class="ml-2 text-blue-600">
                    · {{ props.selected.length }} selecionado(s)
                </span>
            </div>

            <div class="flex items-center gap-3">
                <div class="flex items-center gap-1.5">
                    <span class="text-[11px] font-light text-gray-500">Por página</span>
                    <select
                        :value="props.perPage"
                        class="h-7 rounded border border-gray-200 bg-white px-1.5 text-[11px] font-light outline-none"
                        @change="onPerPageChange"
                    >
                        <option :value="5">5</option>
                        <option :value="10">10</option>
                        <option :value="20">20</option>
                        <option :value="50">50</option>
                    </select>
                </div>

                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        :disabled="props.currentPage <= 1"
                        aria-label="Página anterior"
                        class="flex size-7 cursor-pointer items-center justify-center rounded border border-gray-200 text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="emit('update:currentPage', Math.max(1, props.currentPage - 1))"
                    >
                        <Icon name="lucide:chevron-left" class="size-3.5" />
                    </button>

                    <button
                        v-for="page in props.pageNumbers"
                        :key="page"
                        type="button"
                        :class="[
                            'size-7 cursor-pointer rounded border text-[11px] font-normal transition-colors',
                            props.currentPage === page
                                ? 'border-blue-600 bg-blue-600 text-white'
                                : 'border-gray-200 text-gray-600 hover:bg-gray-50',
                        ]"
                        @click="emit('update:currentPage', page)"
                    >
                        {{ page }}
                    </button>

                    <button
                        type="button"
                        :disabled="props.currentPage >= props.totalPages || props.totalCount === 0"
                        aria-label="Próxima página"
                        class="flex size-7 cursor-pointer items-center justify-center rounded border border-gray-200 text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="emit('update:currentPage', Math.min(props.totalPages, props.currentPage + 1))"
                    >
                        <Icon name="lucide:chevron-right" class="size-3.5" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
