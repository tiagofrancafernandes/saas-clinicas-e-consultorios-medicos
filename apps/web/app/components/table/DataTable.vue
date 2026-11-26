<script setup lang="ts" generic="T extends Record<string, any>">
import type { TableColumn } from '~/types/table';

interface Props {
    columns: TableColumn[];
    records: T[];
    totalCount: number;
    sortBy: string;
    sortDirection: 'asc' | 'desc';
    currentPage: number;
    perPage: number;
    totalPages: number;
    startIndex: number;
    pageNumbers: number[];
    selectedIds: Array<string | number>;
}

defineProps<Props>();
const emit = defineEmits<{
    (e: 'sort', key: string): void;
    (e: 'toggle-all', checked: boolean): void;
    (e: 'toggle-row', id: string | number): void;
    (e: 'update:currentPage', page: number): void;
    (e: 'update:perPage', perPage: number): void;
}>();
</script>

<template>
    <div>
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap text-left">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="w-10 px-4 py-2.5 lg:px-5">
                            <input
                                type="checkbox"
                                :checked="records.length > 0 && records.every((r) => selectedIds.includes(r.id))"
                                class="size-3.5 cursor-pointer accent-teal-600"
                                @change="emit('toggle-all', ($event.target as HTMLInputElement).checked)"
                            />
                        </th>
                        <th
                            v-for="col in columns"
                            :key="col.key"
                            class="px-4 py-2.5 text-[11px] font-normal select-none"
                            :class="[
                                col.sortable !== false ? 'cursor-pointer hover:text-gray-900' : '',
                                col.align === 'right' ? 'text-right' : '',
                            ]"
                            @click="col.sortable !== false && emit('sort', col.key)"
                        >
                            <div class="inline-flex items-center gap-1">
                                <span>{{ col.label }}</span>
                                <Icon
                                    v-if="col.sortable !== false"
                                    :name="sortBy === col.key && sortDirection === 'asc' ? 'lucide:chevron-up' : 'lucide:chevron-down'"
                                    class="size-3"
                                    :class="sortBy === col.key ? 'text-teal-600' : 'text-gray-300'"
                                />
                            </div>
                        </th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-normal lg:px-5">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr
                        v-for="record in records"
                        :key="record.id"
                        class="transition-colors hover:bg-gray-50/70"
                        :class="selectedIds.includes(record.id) ? 'bg-teal-50/30' : ''"
                    >
                        <td class="px-4 py-2.5 lg:px-5">
                            <input
                                type="checkbox"
                                :checked="selectedIds.includes(record.id)"
                                class="size-3.5 cursor-pointer accent-teal-600"
                                @change="emit('toggle-row', record.id)"
                            />
                        </td>
                        <slot name="row" :record="record" />
                        <td class="px-4 py-2.5 text-right lg:px-5">
                            <slot name="actions" :record="record" />
                        </td>
                    </tr>

                    <tr v-if="records.length === 0">
                        <td :colspan="columns.length + 2" class="py-12 text-center">
                            <div class="mx-auto mb-2 flex size-9 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                <Icon name="lucide:search" class="size-4" />
                            </div>
                            <div class="text-[12px] text-gray-600">Nenhum registro encontrado</div>
                            <div class="mt-0.5 text-[11px] text-gray-400">Ajuste a busca ou os filtros aplicados.</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <div class="flex flex-col items-center justify-between gap-3 border-t border-gray-100 px-4 py-2.5 sm:flex-row lg:px-5">
            <div class="text-[11px] text-gray-500">
                Mostrando <span class="text-gray-800">{{ totalCount ? startIndex + 1 : 0 }}</span>
                a <span class="text-gray-800">{{ Math.min(startIndex + perPage, totalCount) }}</span>
                de <span class="text-gray-800">{{ totalCount }}</span> registros
                <span v-if="selectedIds.length" class="ml-2 text-teal-600">· {{ selectedIds.length }} selecionado(s)</span>
            </div>

            <div class="flex items-center gap-3">
                <div class="flex items-center gap-1.5">
                    <span class="text-[11px] text-gray-400">Por página</span>
                    <select
                        :value="perPage"
                        class="h-7 rounded border border-gray-200 bg-white px-1.5 text-[11px] text-gray-700"
                        @change="emit('update:perPage', Number(($event.target as HTMLSelectElement).value)); emit('update:currentPage', 1)"
                    >
                        <option :value="5">5</option>
                        <option :value="10">10</option>
                        <option :value="20">20</option>
                    </select>
                </div>

                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        :disabled="currentPage === 1"
                        class="flex size-7 items-center justify-center rounded border border-gray-200 text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="emit('update:currentPage', Math.max(1, currentPage - 1))"
                    >
                        <Icon name="lucide:chevron-left" class="size-3.5" />
                    </button>

                    <button
                        v-for="page in pageNumbers"
                        :key="page"
                        type="button"
                        class="size-7 rounded border text-[11px] transition-colors"
                        :class="currentPage === page ? 'border-teal-600 bg-teal-600 text-white' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                        @click="emit('update:currentPage', page)"
                    >
                        {{ page }}
                    </button>

                    <button
                        type="button"
                        :disabled="currentPage >= totalPages"
                        class="flex size-7 items-center justify-center rounded border border-gray-200 text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="emit('update:currentPage', Math.min(totalPages, currentPage + 1))"
                    >
                        <Icon name="lucide:chevron-right" class="size-3.5" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
