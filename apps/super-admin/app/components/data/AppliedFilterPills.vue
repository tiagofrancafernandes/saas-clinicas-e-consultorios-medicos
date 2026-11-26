<script setup lang="ts">
import type { FilterField, FilterRule } from '~/types/filter';

interface Props {
    filters: FilterRule[];
    fields: FilterField[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
    remove: [id: string];
    reset: [];
}>();

function getField(rule: FilterRule): FilterField | undefined {
    return props.fields.find((item) => item.key === rule.field);
}

function getOperatorLabel(rule: FilterRule): string {
    return getField(rule)?.operators.find((op) => op.value === rule.operator)?.label ?? rule.operator;
}

function getValueLabel(rule: FilterRule): string {
    const field = getField(rule);
    if (!field || rule.value === '') {
        return '';
    }
    if (field.options) {
        return field.options.find((opt) => String(opt.value) === String(rule.value))?.label ?? rule.value;
    }
    return rule.value;
}
</script>

<template>
    <div
        v-if="props.filters.length > 0"
        class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-4 py-2.5 lg:px-5"
    >
        <span class="mr-0.5 text-[11px] font-light text-gray-400">Filtros:</span>

        <div
            v-for="rule in props.filters"
            :key="rule.id"
            class="inline-flex h-6 items-center overflow-hidden rounded border border-blue-100 bg-blue-50 text-[11px] text-blue-800"
        >
            <span class="px-2 font-normal">{{ getField(rule)?.label ?? rule.field }}</span>
            <span class="text-blue-300">|</span>
            <span class="px-1.5 font-light text-blue-600">{{ getOperatorLabel(rule) }}</span>
            <span v-if="rule.value !== ''" class="pr-2 font-normal">{{ getValueLabel(rule) }}</span>
            <button
                type="button"
                title="Remover filtro"
                class="flex h-full w-6 cursor-pointer items-center justify-center border-l border-blue-100 text-blue-400 transition-colors hover:bg-red-50 hover:text-red-600"
                @click="emit('remove', rule.id)"
            >
                <Icon name="lucide:x" class="size-3" />
            </button>
        </div>

        <button
            type="button"
            class="ml-1 cursor-pointer text-[11px] font-light text-gray-400 transition-colors hover:text-red-600"
            @click="emit('reset')"
        >
            Limpar todos
        </button>
    </div>
</template>
