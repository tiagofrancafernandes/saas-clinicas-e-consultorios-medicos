<script setup lang="ts">
import type { FilterField, FilterRule } from '~/types/filter';

interface Props {
    open: boolean;
    fields: FilterField[];
    modelValue: FilterRule[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
    close: [];
    apply: [rules: FilterRule[]];
    reset: [];
    'update:modelValue': [rules: FilterRule[]];
}>();

const localRules = ref<FilterRule[]>([]);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            localRules.value = props.modelValue.length
                ? props.modelValue.map((item) => ({ ...item }))
                : [createEmptyRule()];
        }
    },
    { immediate: true }
);

function createEmptyRule(): FilterRule {
    const firstField = props.fields[0];
    return {
        id: `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
        field: firstField?.key ?? '',
        operator: firstField?.operators[0]?.value ?? 'contains',
        value: '',
    };
}

function getField(rule: FilterRule): FilterField | undefined {
    return props.fields.find((item) => item.key === rule.field) ?? props.fields[0];
}

function changeField(rule: FilterRule, fieldKey: string): void {
    const field = props.fields.find((item) => item.key === fieldKey);
    if (!field) {
        return;
    }
    rule.field = fieldKey;
    rule.operator = field.operators[0]?.value ?? 'equals';
    rule.value = '';
}

function addRule(): void {
    localRules.value.push(createEmptyRule());
}

function removeRule(id: string): void {
    localRules.value = localRules.value.filter((item) => item.id !== id);
}

function handleClearAll(): void {
    localRules.value = [];
    emit('update:modelValue', []);
    emit('reset');
    emit('close');
}

function handleApply(): void {
    const hasInvalid = localRules.value.some(
        (rule) => !['is_empty', 'is_not_empty'].includes(rule.operator) && rule.value === ''
    );
    if (hasInvalid) {
        return;
    }
    emit('update:modelValue', localRules.value);
    emit('apply', localRules.value);
}
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-150"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-100"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-1"
    >
        <div v-show="props.open" class="relative z-20 border-b border-gray-100 bg-white">
            <div class="p-4 lg:px-5">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <h3 class="text-[12px] font-medium text-gray-800">Filtros Avançados</h3>
                        <p class="mt-0.5 text-[11px] font-light text-gray-400">
                            Defina condições combinadas para refinar os registros.
                        </p>
                    </div>
                    <button
                        type="button"
                        aria-label="Fechar filtros"
                        class="flex size-7 cursor-pointer items-center justify-center rounded text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700"
                        @click="emit('close')"
                    >
                        <Icon name="lucide:x" class="size-3.5" />
                    </button>
                </div>

                <div class="space-y-2">
                    <div
                        v-for="rule in localRules"
                        :key="rule.id"
                        class="flex flex-wrap items-center gap-1.5 sm:flex-nowrap"
                    >
                        <FilterDropdown
                            :model-value="rule.field"
                            :options="props.fields.map((f) => ({ value: f.key, label: f.label }))"
                            width-class="w-full shrink-0 sm:w-[155px]"
                            @update:model-value="changeField(rule, $event)"
                        />

                        <FilterDropdown
                            :model-value="rule.operator"
                            :options="getField(rule)?.operators ?? []"
                            width-class="w-full shrink-0 sm:w-[165px]"
                            @update:model-value="
                                rule.operator = $event;
                                rule.value = '';
                            "
                        />

                        <FilterDropdown
                            v-if="getField(rule)?.options"
                            v-model="rule.value"
                            :options="getField(rule)?.options ?? []"
                            placeholder="Selecione um valor"
                            width-class="min-w-[140px] flex-1"
                        />

                        <input
                            v-else-if="!['is_empty', 'is_not_empty'].includes(rule.operator)"
                            v-model="rule.value"
                            :type="
                                getField(rule)?.type === 'date'
                                    ? 'date'
                                    : getField(rule)?.type === 'number'
                                      ? 'number'
                                      : 'text'
                            "
                            :placeholder="
                                getField(rule)?.type === 'number'
                                    ? 'Digite um número'
                                    : getField(rule)?.type === 'date'
                                      ? 'Selecione a data'
                                      : 'Digite um valor'
                            "
                            class="h-8 min-w-[140px] flex-1 rounded-md border border-gray-200 px-2.5 text-[12px] font-light text-gray-900 placeholder:text-gray-400 transition-colors outline-none hover:border-gray-300 focus:border-gray-400"
                        />

                        <button
                            type="button"
                            title="Remover condição"
                            class="flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600"
                            @click="removeRule(rule.id)"
                        >
                            <Icon name="lucide:trash-2" class="size-3.5" />
                        </button>
                    </div>
                </div>

                <div class="mt-3.5 flex flex-wrap items-center justify-between gap-3">
                    <UiButton
                        variant="ghost"
                        size="xs"
                        icon="lucide:plus"
                        class="text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                        @click="addRule"
                    >
                        Adicionar filtro
                    </UiButton>

                    <div class="flex items-center gap-2">
                        <span class="hidden text-[11px] font-light text-gray-400 sm:inline">
                            Condições combinadas com AND
                        </span>
                        <UiButton variant="ghost" size="xs" @click="handleClearAll">Limpar tudo</UiButton>
                        <UiButton variant="primary" size="xs" @click="handleApply">Aplicar</UiButton>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>
