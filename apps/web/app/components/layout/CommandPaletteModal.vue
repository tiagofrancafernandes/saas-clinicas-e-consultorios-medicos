<script setup lang="ts">
import { COMMAND_ACTIONS } from '~/config/navigation';
import { useAuthStore } from '~/stores/auth';

interface Props {
    open: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{ (e: 'close'): void }>();

const auth = useAuthStore();
const router = useRouter();
const query = ref('');
const searchInput = ref<HTMLInputElement | null>(null);

const filteredActions = computed(() => {
    const q = query.value.trim().toLowerCase();
    return COMMAND_ACTIONS.filter((action) => auth.can(action.permission)).filter(
        (action) =>
            !q ||
            action.label.toLowerCase().includes(q) ||
            action.description.toLowerCase().includes(q),
    );
});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            query.value = '';
            nextTick(() => searchInput.value?.focus());
        }
    },
);

function handleEsc(event: KeyboardEvent): void {
    if (event.key === 'Escape' && props.open) {
        emit('close');
    }
}

onMounted(() => window.addEventListener('keydown', handleEsc));
onUnmounted(() => window.removeEventListener('keydown', handleEsc));

function selectAction(to: string): void {
    emit('close');
    router.push(to);
}
</script>

<template>
    <ClientOnly>
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="open"
                    id="command-palette-dialog"
                    class="fixed inset-0 z-50 flex items-start justify-center bg-black/30 px-4 pt-20"
                    @click.self="emit('close')"
                >
                <div class="w-full max-w-lg overflow-hidden rounded-md border border-gray-200 bg-white shadow-xl">
                    <div class="flex items-center gap-2 border-b border-gray-100 px-3 py-2.5">
                        <Icon name="lucide:search" class="size-4 text-gray-400" />
                        <input
                            ref="searchInput"
                            v-model="query"
                            type="text"
                            placeholder="Digite um comando (ex: cadastrar, listar...)"
                            class="w-full bg-transparent text-[13px] text-gray-800 placeholder:text-gray-400"
                            @keydown.esc="emit('close')"
                        />
                        <button
                            type="button"
                            class="rounded border border-gray-200 px-1.5 py-0.5 text-[10px] text-gray-400 hover:bg-gray-50"
                            @click="emit('close')"
                        >
                            ESC
                        </button>
                    </div>

                    <div class="max-h-72 overflow-y-auto p-1.5">
                        <button
                            v-for="action in filteredActions"
                            :key="action.id"
                            type="button"
                            class="flex w-full items-center gap-3 rounded px-2.5 py-2 text-left transition-colors hover:bg-gray-50"
                            @click="selectAction(action.to)"
                        >
                            <div class="flex size-7 shrink-0 items-center justify-center rounded border border-gray-200 bg-gray-50 text-gray-600">
                                <Icon :name="action.icon" class="size-3.5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-[12px] font-normal text-gray-800">
                                    {{ action.label }}
                                </div>
                                <div class="truncate text-[11px] text-gray-400">
                                    {{ action.description }}
                                </div>
                            </div>
                            <Icon name="lucide:corner-down-left" class="size-3.5 text-gray-300" />
                        </button>

                        <div v-if="filteredActions.length === 0" class="py-8 text-center text-[12px] text-gray-400">
                            Nenhuma ação encontrada para "{{ query }}".
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
    </ClientOnly>
</template>
