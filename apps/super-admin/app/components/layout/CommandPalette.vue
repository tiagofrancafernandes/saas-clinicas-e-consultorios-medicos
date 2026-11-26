<script setup lang="ts">
import type { CommandAction } from '~/types/navigation';

const { isOpen, allActions, open, close } = useCommandPalette();
const router = useRouter();

const query = ref('');
const activeIndex = ref(0);
const inputRef = ref<HTMLInputElement | null>(null);

const filteredActions = computed<CommandAction[]>(() => {
    const term = query.value.toLowerCase().trim();
    if (!term) {
        return allActions.value;
    }
    return allActions.value.filter(
        (item) =>
            item.label.toLowerCase().includes(term) ||
            (item.description?.toLowerCase().includes(term) ?? false) ||
            item.group.toLowerCase().includes(term)
    );
});

const groupedActions = computed(() => {
    const groups = new Map<string, CommandAction[]>();
    for (const action of filteredActions.value) {
        const list = groups.get(action.group) ?? [];
        list.push(action);
        groups.set(action.group, list);
    }
    return Array.from(groups.entries()).map(([group, items]) => ({ group, items }));
});

watch(isOpen, async (opened) => {
    if (opened) {
        query.value = '';
        activeIndex.value = 0;
        await nextTick();
        inputRef.value?.focus();
    }
});

watch(query, () => {
    activeIndex.value = 0;
});

async function executeAction(action: CommandAction): Promise<void> {
    close();
    if (action.action) {
        action.action();
        return;
    }
    if (action.to) {
        await router.push(action.to);
    }
}

function onGlobalKeydown(event: KeyboardEvent): void {
    const target = event.target as HTMLElement | null;
    const isInput =
        target?.tagName === 'INPUT' ||
        target?.tagName === 'TEXTAREA' ||
        target?.tagName === 'SELECT' ||
        target?.isContentEditable;

    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        isOpen.value ? close() : open();
        return;
    }

    if (event.key === '/' && !isInput && !isOpen.value) {
        event.preventDefault();
        open();
        return;
    }

    if (!isOpen.value) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        close();
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (filteredActions.value.length > 0) {
            activeIndex.value = (activeIndex.value + 1) % filteredActions.value.length;
        }
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (filteredActions.value.length > 0) {
            activeIndex.value = (activeIndex.value - 1 + filteredActions.value.length) % filteredActions.value.length;
        }
        return;
    }

    if (event.key === 'Enter') {
        const selected = filteredActions.value[activeIndex.value];
        if (selected) {
            event.preventDefault();
            executeAction(selected);
        }
    }
}

onMounted(() => {
    window.addEventListener('keydown', onGlobalKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onGlobalKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isOpen"
                class="fixed inset-0 z-[70] flex items-start justify-center bg-gray-900/40 px-4 pt-[12vh]"
                @click.self="close"
            >
                <div class="w-full max-w-[540px] overflow-hidden rounded-md border border-gray-200 bg-white shadow-xl">
                    <!-- Search Bar -->
                    <div class="flex items-center gap-2.5 border-b border-gray-100 px-3.5 py-2.5">
                        <Icon name="lucide:search" class="size-4 shrink-0 text-gray-400" />
                        <input
                            ref="inputRef"
                            v-model="query"
                            type="text"
                            placeholder="Digite um comando ou busque (ex: cadastrar, listar)..."
                            class="h-7 flex-1 bg-transparent text-[13px] font-light text-gray-900 placeholder:text-gray-400 outline-none"
                        />
                        <kbd class="rounded border border-gray-200 px-1.5 py-0.5 text-[10px] text-gray-400">ESC</kbd>
                    </div>

                    <!-- Results -->
                    <div class="max-h-[340px] overflow-y-auto p-2">
                        <div v-if="groupedActions.length === 0" class="py-10 text-center">
                            <Icon name="lucide:search-x" class="mx-auto mb-2 size-5 text-gray-300" />
                            <p class="text-[12px] font-light text-gray-500">Nenhum comando encontrado.</p>
                        </div>

                        <div v-for="section in groupedActions" :key="section.group" class="mb-2 last:mb-0">
                            <div class="px-2 py-1 text-[10px] font-medium uppercase tracking-wider text-gray-400">
                                {{ section.group }}
                            </div>
                            <button
                                v-for="action in section.items"
                                :key="action.id"
                                type="button"
                                :class="[
                                    'flex w-full cursor-pointer items-center gap-2.5 rounded px-2.5 py-2 text-left transition-colors',
                                    filteredActions[activeIndex]?.id === action.id
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-700 hover:bg-gray-50',
                                ]"
                                @mouseenter="activeIndex = filteredActions.findIndex((a) => a.id === action.id)"
                                @click="executeAction(action)"
                            >
                                <Icon
                                    :name="action.icon"
                                    :class="[
                                        'size-4 shrink-0',
                                        filteredActions[activeIndex]?.id === action.id
                                            ? 'text-blue-600'
                                            : 'text-gray-400',
                                    ]"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-[12px] font-normal">{{ action.label }}</div>
                                    <div
                                        v-if="action.description"
                                        class="truncate text-[11px] font-light text-gray-400"
                                    >
                                        {{ action.description }}
                                    </div>
                                </div>
                                <Icon name="lucide:corner-down-left" class="size-3.5 shrink-0 text-gray-300" />
                            </button>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div
                        class="flex items-center justify-between border-t border-gray-100 bg-gray-50/70 px-3.5 py-2 text-[10px] font-light text-gray-400"
                    >
                        <div class="flex items-center gap-3">
                            <span>↑↓ navegar</span>
                            <span>↵ executar</span>
                        </div>
                        <span>Command Palette</span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
