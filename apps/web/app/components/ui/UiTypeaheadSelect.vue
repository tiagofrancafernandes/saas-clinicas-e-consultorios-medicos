<script setup lang="ts">
export interface TypeaheadOption {
    value: string | number;
    label: string;
    description?: string;
    disabled?: boolean;
    raw?: unknown;
}

interface Props {
    modelValue: string | number | null;
    options?: TypeaheadOption[];
    fetchOptions?: (query: string) => Promise<TypeaheadOption[]>;
    initialOption?: TypeaheadOption | null;
    preload?: boolean;
    placeholder?: string;
    emptyText?: string;
    debounceMs?: number;
    minChars?: number;
    clearable?: boolean;
    disabled?: boolean;
    required?: boolean;
    inputId?: string;
}

const props = withDefaults(defineProps<Props>(), {
    options: () => [],
    fetchOptions: undefined,
    initialOption: null,
    preload: false,
    placeholder: 'Selecione ou digite para buscar...',
    emptyText: 'Nenhum resultado encontrado.',
    debounceMs: 250,
    minChars: 0,
    clearable: true,
    disabled: false,
    required: false,
    inputId: undefined,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string | number | null): void;
    (e: 'select', option: TypeaheadOption | null): void;
    (e: 'clear'): void;
}>();

const containerRef = ref<HTMLElement | null>(null);
const inputRef = ref<HTMLInputElement | null>(null);

const isOpen = ref(false);
const isLoading = ref(false);
const hasUserTyped = ref(false);
const query = ref('');
const highlightedIndex = ref(-1);

const remoteOptions = ref<TypeaheadOption[]>([]);
const preloaded = ref(false);

let debounceTimer: ReturnType<typeof setTimeout> | null = null;
let requestSeq = 0;

function mergeOptions(lists: TypeaheadOption[][]): TypeaheadOption[] {
    const map = new Map<string, TypeaheadOption>();
    for (const list of lists) {
        for (const item of list) {
            const key = String(item.value);
            if (!map.has(key)) {
                map.set(key, item);
            } else {
                map.set(key, { ...map.get(key)!, ...item });
            }
        }
    }
    return Array.from(map.values());
}

const allKnownOptions = computed(() => {
    const initial = props.initialOption ? [props.initialOption] : [];
    return mergeOptions([props.options, remoteOptions.value, initial]);
});

const selectedOption = computed<TypeaheadOption | null>(() => {
    if (props.modelValue === null || props.modelValue === '') return null;
    return allKnownOptions.value.find((opt) => String(opt.value) === String(props.modelValue)) ?? null;
});

const filteredOptions = computed<TypeaheadOption[]>(() => {
    const search = hasUserTyped.value ? query.value.trim().toLowerCase() : '';
    if (!search) {
        return allKnownOptions.value;
    }
    return allKnownOptions.value.filter((opt) => {
        const labelMatch = opt.label.toLowerCase().includes(search);
        const descMatch = opt.description ? opt.description.toLowerCase().includes(search) : false;
        return labelMatch || descMatch;
    });
});

function syncQueryFromSelection(): void {
    query.value = selectedOption.value?.label ?? '';
    hasUserTyped.value = false;
}

watch(
    () => [props.modelValue, selectedOption.value?.label] as const,
    () => {
        if (!isOpen.value || !hasUserTyped.value) {
            syncQueryFromSelection();
        }
    },
    { immediate: true },
);

async function runFetch(searchTerm: string): Promise<void> {
    if (!props.fetchOptions) return;
    const seq = ++requestSeq;
    isLoading.value = true;
    try {
        const fetched = await props.fetchOptions(searchTerm);
        if (seq !== requestSeq) return;
        remoteOptions.value = mergeOptions([remoteOptions.value, fetched ?? []]);
        if (searchTerm === '') {
            preloaded.value = true;
        }
    } catch {
        // Keep in-memory options if remote request fails
    } finally {
        if (seq === requestSeq) {
            isLoading.value = false;
        }
    }
}

watch(
    () => props.preload,
    (shouldPreload) => {
        if (shouldPreload && props.fetchOptions && !preloaded.value) {
            runFetch('');
        }
    },
    { immediate: true },
);

function openDropdown(): void {
    if (props.disabled) return;
    isOpen.value = true;
    highlightedIndex.value = -1;
    if (props.preload && props.fetchOptions && !preloaded.value) {
        runFetch('');
    }
}

function handleFocus(): void {
    openDropdown();
}

function handleInput(event: Event): void {
    const target = event.target as HTMLInputElement;
    query.value = target.value;
    hasUserTyped.value = true;
    isOpen.value = true;
    highlightedIndex.value = 0;

    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    const trimmed = query.value.trim();
    if (!props.fetchOptions || trimmed.length < props.minChars) {
        return;
    }

    debounceTimer = setTimeout(() => {
        runFetch(trimmed);
    }, props.debounceMs);
}

function selectOption(option: TypeaheadOption): void {
    if (option.disabled) return;
    remoteOptions.value = mergeOptions([remoteOptions.value, [option]]);
    emit('update:modelValue', option.value);
    emit('select', option);
    query.value = option.label;
    hasUserTyped.value = false;
    isOpen.value = false;
    highlightedIndex.value = -1;
}

function clearSelection(): void {
    if (props.disabled) return;
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }
    query.value = '';
    hasUserTyped.value = false;
    highlightedIndex.value = -1;
    emit('update:modelValue', null);
    emit('select', null);
    emit('clear');
    nextTick(() => {
        inputRef.value?.focus();
    });
}

function handleKeydown(event: KeyboardEvent): void {
    if (!isOpen.value && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
        event.preventDefault();
        openDropdown();
        return;
    }

    if (!isOpen.value) return;

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (filteredOptions.value.length > 0) {
            highlightedIndex.value = (highlightedIndex.value + 1) % filteredOptions.value.length;
        }
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (filteredOptions.value.length > 0) {
            highlightedIndex.value =
                highlightedIndex.value <= 0 ? filteredOptions.value.length - 1 : highlightedIndex.value - 1;
        }
    } else if (event.key === 'Enter') {
        if (highlightedIndex.value >= 0 && highlightedIndex.value < filteredOptions.value.length) {
            event.preventDefault();
            const opt = filteredOptions.value[highlightedIndex.value];
            if (opt) selectOption(opt);
        }
    } else if (event.key === 'Escape') {
        isOpen.value = false;
        syncQueryFromSelection();
    }
}

function handleOutsideClick(event: MouseEvent): void {
    if (!containerRef.value) return;
    if (!containerRef.value.contains(event.target as Node)) {
        isOpen.value = false;
        syncQueryFromSelection();
    }
}

onMounted(() => {
    document.addEventListener('mousedown', handleOutsideClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleOutsideClick);
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }
});

const showClearButton = computed(
    () => props.clearable && !props.disabled && (query.value.length > 0 || (props.modelValue !== null && props.modelValue !== '')),
);
</script>

<template>
    <div ref="containerRef" class="relative w-full">
        <div class="relative flex items-center">
            <input
                :id="props.inputId"
                ref="inputRef"
                :value="query"
                type="text"
                role="combobox"
                autocomplete="off"
                :aria-expanded="isOpen"
                :placeholder="props.placeholder"
                :disabled="props.disabled"
                :required="props.required && (props.modelValue === null || props.modelValue === '')"
                class="h-8 w-full rounded border border-gray-200 bg-white pl-2.5 pr-12 text-[12px] text-gray-800 placeholder:text-gray-400 focus:border-teal-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-400"
                @focus="handleFocus"
                @input="handleInput"
                @keydown="handleKeydown"
            />

            <div class="absolute right-1.5 flex items-center gap-0.5">
                <Icon v-if="isLoading" name="lucide:loader-2" class="size-3.5 animate-spin text-gray-400" />

                <button
                    v-if="showClearButton"
                    type="button"
                    title="Limpar seleção"
                    aria-label="Limpar seleção"
                    class="inline-flex size-5 items-center justify-center rounded text-gray-400 hover:bg-gray-100 hover:text-gray-700"
                    @click.stop="clearSelection"
                >
                    <Icon name="lucide:x" class="size-3" />
                </button>

                <button
                    type="button"
                    tabindex="-1"
                    :disabled="props.disabled"
                    class="inline-flex size-5 items-center justify-center rounded text-gray-400 hover:text-gray-700 disabled:pointer-events-none"
                    @click="isOpen ? (isOpen = false) : openDropdown()"
                >
                    <Icon name="lucide:chevron-down" class="size-3.5 transition-transform" :class="isOpen ? 'rotate-180' : ''" />
                </button>
            </div>
        </div>

        <div
            v-if="isOpen"
            class="absolute left-0 top-full z-50 mt-1 max-h-56 w-full overflow-y-auto rounded border border-gray-200 bg-white py-1 shadow-lg"
        >
            <div v-if="isLoading && filteredOptions.length === 0" class="px-3 py-2 text-[11px] text-gray-400">
                Buscando...
            </div>
            <div v-else-if="filteredOptions.length === 0" class="px-3 py-2 text-[11px] text-gray-400">
                <slot name="empty">{{ props.emptyText }}</slot>
            </div>
            <button
                v-for="(option, index) in filteredOptions"
                :key="String(option.value)"
                type="button"
                :disabled="option.disabled"
                class="flex w-full items-center justify-between px-3 py-1.5 text-left text-[12px] transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                :class="[
                    index === highlightedIndex ? 'bg-teal-50 text-teal-700' : 'text-gray-700 hover:bg-gray-50',
                    props.modelValue !== null && String(props.modelValue) === String(option.value) ? 'bg-teal-50/60' : '',
                ]"
                @mouseenter="highlightedIndex = index"
                @mousedown.prevent="selectOption(option)"
            >
                <slot
                    name="option"
                    :option="option"
                    :active="index === highlightedIndex"
                    :selected="props.modelValue !== null && String(props.modelValue) === String(option.value)"
                >
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-gray-900">{{ option.label }}</div>
                        <div v-if="option.description" class="truncate text-[10px] text-gray-400">{{ option.description }}</div>
                    </div>
                    <Icon
                        v-if="props.modelValue !== null && String(props.modelValue) === String(option.value)"
                        name="lucide:check"
                        class="ml-2 size-3.5 shrink-0 text-teal-600"
                    />
                </slot>
            </button>
        </div>
    </div>
</template>
