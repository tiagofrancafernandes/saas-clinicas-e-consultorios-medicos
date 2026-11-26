<script setup lang="ts">
import { getOperatorsForField } from '~/composables/useDataTable';
import type { FilterCondition, FilterField } from '~/types/table';

interface Props {
    open: boolean;
    fields: FilterField[];
    draftFilters: FilterCondition[];
    appliedFilters: FilterCondition[];
    createEmptyFilter: () => FilterCondition;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'apply'): void;
    (e: 'reset'): void;
    (e: 'remove-applied', id: string): void;
    (e: 'update:draftFilters', value: FilterCondition[]): void;
}>();

const openDropdown = ref<string | null>(null);

function getField(cond: FilterCondition): FilterField | undefined {
    return props.fields.find((f) => f.key === cond.field) ?? props.fields[0];
}

function getOperatorLabel(cond: FilterCondition): string {
    const ops = getOperatorsForField(getField(cond));
    return ops.find((op) => op.value === cond.operator)?.label ?? cond.operator;
}

function getValueLabel(cond: FilterCondition): string {
    const field = getField(cond);
    if (field?.options) {
        return field.options.find((opt) => String(opt.value) === String(cond.value))?.label ?? '';
    }
    return String(cond.value ?? '');
}

function changeField(cond: FilterCondition, fieldKey: string): void {
    cond.field = fieldKey;
    const ops = getOperatorsForField(getField(cond));
    cond.operator = ops[0]?.value ?? 'contains';
    cond.value = '';
    openDropdown.value = null;
}

function addCondition(): void {
    emit('update:draftFilters', [...props.draftFilters, props.createEmptyFilter()]);
}

function removeCondition(id: string): void {
    const next = props.draftFilters.filter((f) => f.id !== id);
    emit('update:draftFilters', next.length > 0 ? next : [props.createEmptyFilter()]);
}
</script>

<template>
    <div>
        <!-- Animated Advanced Filter Builder Panel -->
        <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div v-if="open" id="advanced-filter-panel" class="relative z-20 border-b border-gray-100 bg-white p-4 lg:px-5">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <h3 class="text-[12px] font-medium text-gray-800">Filtros avançados</h3>
                        <p class="mt-0.5 text-[11px] text-gray-400">Defina condições combinadas para refinar os registros.</p>
                    </div>
                    <button
                        type="button"
                        class="flex size-6 items-center justify-center rounded text-gray-400 hover:bg-gray-100 hover:text-gray-700"
                        @click="emit('close')"
                    >
                        <Icon name="lucide:x" class="size-3.5" />
                    </button>
                </div>

                <div class="space-y-2">
                    <div
                        v-for="cond in draftFilters"
                        :key="cond.id"
                        class="flex flex-wrap items-center gap-1.5 sm:flex-nowrap"
                    >
                        <!-- Field dropdown -->
                        <div class="relative w-full shrink-0 sm:w-[155px]">
                            <button
                                type="button"
                                class="flex h-8 w-full items-center justify-between gap-2 rounded border border-gray-200 bg-white px-2.5 text-[12px] text-gray-700 hover:border-gray-300"
                                @click="openDropdown = openDropdown === `f-${cond.id}` ? null : `f-${cond.id}`"
                            >
                                <span class="truncate">{{ getField(cond)?.label }}</span>
                                <Icon name="lucide:chevron-down" class="size-3.5 text-gray-400" />
                            </button>
                            <div
                                v-if="openDropdown === `f-${cond.id}`"
                                class="absolute left-0 top-full z-50 mt-1 w-full min-w-[170px] rounded border border-gray-200 bg-white py-1 shadow-lg"
                            >
                                <button
                                    v-for="field in fields"
                                    :key="field.key"
                                    type="button"
                                    class="flex h-8 w-full items-center justify-between px-2.5 text-left text-[12px] hover:bg-gray-50"
                                    :class="cond.field === field.key ? 'text-teal-700 font-normal' : 'text-gray-700'"
                                    @click="changeField(cond, field.key)"
                                >
                                    <span>{{ field.label }}</span>
                                    <Icon v-if="cond.field === field.key" name="lucide:check" class="size-3 text-teal-600" />
                                </button>
                            </div>
                        </div>

                        <!-- Operator dropdown -->
                        <div class="relative w-full shrink-0 sm:w-[145px]">
                            <button
                                type="button"
                                class="flex h-8 w-full items-center justify-between gap-2 rounded border border-gray-200 bg-white px-2.5 text-[12px] text-gray-700 hover:border-gray-300"
                                @click="openDropdown = openDropdown === `o-${cond.id}` ? null : `o-${cond.id}`"
                            >
                                <span class="truncate">{{ getOperatorLabel(cond) }}</span>
                                <Icon name="lucide:chevron-down" class="size-3.5 text-gray-400" />
                            </button>
                            <div
                                v-if="openDropdown === `o-${cond.id}`"
                                class="absolute left-0 top-full z-50 mt-1 w-full min-w-[160px] rounded border border-gray-200 bg-white py-1 shadow-lg"
                            >
                                <button
                                    v-for="op in getOperatorsForField(getField(cond))"
                                    :key="op.value"
                                    type="button"
                                    class="flex h-8 w-full items-center justify-between px-2.5 text-left text-[12px] hover:bg-gray-50"
                                    :class="cond.operator === op.value ? 'text-teal-700 font-normal' : 'text-gray-700'"
                                    @click="cond.operator = op.value; cond.value = ''; openDropdown = null"
                                >
                                    <span>{{ op.label }}</span>
                                    <Icon v-if="cond.operator === op.value" name="lucide:check" class="size-3 text-teal-600" />
                                </button>
                            </div>
                        </div>

                        <!-- Select option dropdown -->
                        <div v-if="getField(cond)?.options" class="relative min-w-[140px] flex-1">
                            <button
                                type="button"
                                class="flex h-8 w-full items-center justify-between gap-2 rounded border border-gray-200 bg-white px-2.5 text-[12px] hover:border-gray-300"
                                @click="openDropdown = openDropdown === `v-${cond.id}` ? null : `v-${cond.id}`"
                            >
                                <span class="truncate" :class="cond.value === '' ? 'text-gray-400' : 'text-gray-800'">
                                    {{ getValueLabel(cond) || 'Selecione um valor' }}
                                </span>
                                <Icon name="lucide:chevron-down" class="size-3.5 text-gray-400" />
                            </button>
                            <div
                                v-if="openDropdown === `v-${cond.id}`"
                                class="absolute left-0 top-full z-50 mt-1 w-full rounded border border-gray-200 bg-white py-1 shadow-lg"
                            >
                                <button
                                    v-for="opt in getField(cond)?.options"
                                    :key="String(opt.value)"
                                    type="button"
                                    class="flex h-8 w-full items-center justify-between px-2.5 text-left text-[12px] hover:bg-gray-50"
                                    @click="cond.value = opt.value; openDropdown = null"
                                >
                                    <span>{{ opt.label }}</span>
                                    <Icon v-if="String(cond.value) === String(opt.value)" name="lucide:check" class="size-3 text-teal-600" />
                                </button>
                            </div>
                        </div>

                        <!-- Text / Number / Date input -->
                        <input
                            v-else-if="!['is_empty', 'is_not_empty'].includes(cond.operator)"
                            v-model="cond.value"
                            :type="getField(cond)?.type === 'date' ? 'date' : getField(cond)?.type === 'number' ? 'number' : 'text'"
                            placeholder="Digite um valor..."
                            class="h-8 min-w-[140px] flex-1 rounded border border-gray-200 bg-white px-2.5 text-[12px] text-gray-800 placeholder:text-gray-400 focus:border-gray-400"
                        />

                        <button
                            type="button"
                            title="Remover condição"
                            class="flex size-8 shrink-0 items-center justify-center rounded text-gray-400 hover:bg-rose-50 hover:text-rose-600"
                            @click="removeCondition(cond.id)"
                        >
                            <Icon name="lucide:trash-2" class="size-3.5" />
                        </button>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                    <button
                        type="button"
                        class="flex h-7 items-center gap-1.5 rounded px-2 text-[11px] text-teal-600 hover:bg-teal-50"
                        @click="addCondition"
                    >
                        <Icon name="lucide:plus" class="size-3.5" />
                        Adicionar condição
                    </button>

                    <div class="flex items-center gap-2">
                        <span class="hidden text-[10px] text-gray-400 sm:inline">Todas as condições usam E (AND)</span>
                        <button
                            type="button"
                            class="h-7 rounded px-2.5 text-[11px] text-gray-600 hover:bg-gray-100"
                            @click="emit('reset'); emit('close')"
                        >
                            Limpar
                        </button>
                        <button
                            id="btn-apply-advanced-filters"
                            type="button"
                            class="h-7 rounded bg-teal-600 px-3 text-[11px] text-white hover:bg-teal-700"
                            @click="emit('apply')"
                        >
                            Aplicar
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Applied filter pills -->
        <div
            v-if="appliedFilters.length > 0"
            class="flex flex-wrap items-center gap-1.5 border-b border-gray-100 px-4 py-2 lg:px-5"
        >
            <span class="mr-1 text-[11px] text-gray-400">Filtros:</span>
            <div
                v-for="cond in appliedFilters"
                :key="cond.id"
                class="inline-flex h-6 items-center overflow-hidden rounded border border-teal-100 bg-teal-50 text-[11px] text-teal-800"
            >
                <span class="px-2">{{ getField(cond)?.label }}</span>
                <span class="text-teal-300">|</span>
                <span class="px-1.5 text-teal-600">{{ getOperatorLabel(cond) }}</span>
                <span v-if="cond.value !== ''" class="pr-2">{{ getValueLabel(cond) }}</span>
                <button
                    type="button"
                    class="flex h-full w-6 items-center justify-center border-l border-teal-100 text-teal-400 hover:bg-rose-50 hover:text-rose-600"
                    @click="emit('remove-applied', cond.id)"
                >
                    <Icon name="lucide:x" class="size-3" />
                </button>
            </div>
            <button
                type="button"
                class="ml-1 text-[11px] text-gray-400 hover:text-rose-600"
                @click="emit('reset')"
            >
                Limpar tudo
            </button>
        </div>
    </div>
</template>
