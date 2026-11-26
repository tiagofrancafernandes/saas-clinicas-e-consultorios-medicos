<script setup lang="ts">
import type { NavItem } from '~/types/navigation';

interface Props {
    item: NavItem;
    expandedView: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    navigate: [];
}>();

const route = useRoute();

function isChildActive(to: string): boolean {
    if (to === '/') {
        return route.path === '/';
    }
    return route.path === to || route.path.startsWith(`${to}/`);
}

const hasActiveChild = computed(() => (props.item.children ?? []).some((child) => isChildActive(child.to)));

const isExpanded = ref(hasActiveChild.value);

watch(hasActiveChild, (active) => {
    if (active) {
        isExpanded.value = true;
    }
});

function toggleExpand(): void {
    isExpanded.value = !isExpanded.value;
}
</script>

<template>
    <div v-if="props.item.children?.length">
        <button
            type="button"
            :title="!props.expandedView ? props.item.label : undefined"
            :class="[
                'flex h-8 w-full cursor-pointer items-center gap-2.5 overflow-hidden rounded px-2.5 text-left text-[12px] whitespace-nowrap transition-colors',
                hasActiveChild
                    ? 'bg-gray-50 font-normal text-gray-900'
                    : 'font-light text-gray-600 hover:bg-gray-50 hover:text-gray-900',
            ]"
            @click="toggleExpand"
        >
            <Icon :name="props.item.icon" class="size-4 shrink-0 text-gray-400" />
            <span
                :class="[
                    'flex-1 overflow-hidden whitespace-nowrap transition-opacity duration-150',
                    props.expandedView ? 'opacity-100' : 'opacity-0',
                ]"
            >
                {{ props.item.label }}
            </span>
            <Icon
                v-if="props.expandedView"
                name="lucide:chevron-right"
                :class="[
                    'size-3.5 shrink-0 text-gray-400 transition-transform duration-150',
                    isExpanded ? 'rotate-90' : '',
                ]"
            />
        </button>

        <Transition
            enter-active-class="transition-all duration-150 ease-out overflow-hidden"
            enter-from-class="max-h-0 opacity-0"
            enter-to-class="max-h-60 opacity-100"
            leave-active-class="transition-all duration-100 ease-in overflow-hidden"
            leave-from-class="max-h-60 opacity-100"
            leave-to-class="max-h-0 opacity-0"
        >
            <div
                v-show="isExpanded && props.expandedView"
                class="mt-0.5 ml-3.5 space-y-0.5 overflow-hidden border-l border-gray-200 pl-2.5"
            >
                <NuxtLink
                    v-for="child in props.item.children"
                    :key="child.to"
                    :to="child.to"
                    :class="[
                        'flex h-7 items-center gap-2 overflow-hidden rounded px-2 text-[12px] whitespace-nowrap transition-colors',
                        isChildActive(child.to)
                            ? 'bg-blue-50 font-normal text-blue-700'
                            : 'font-light text-gray-600 hover:bg-gray-50 hover:text-gray-900',
                    ]"
                    @click="emit('navigate')"
                >
                    <span class="truncate whitespace-nowrap">{{ child.label }}</span>
                    <span
                        v-if="child.badge"
                        class="ml-auto shrink-0 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-500"
                    >
                        {{ child.badge }}
                    </span>
                </NuxtLink>
            </div>
        </Transition>
    </div>

    <NuxtLink
        v-else-if="props.item.to"
        :to="props.item.to"
        :title="!props.expandedView ? props.item.label : undefined"
        :class="[
            'flex h-8 w-full items-center gap-2.5 overflow-hidden rounded px-2.5 text-[12px] whitespace-nowrap transition-colors',
            isChildActive(props.item.to)
                ? 'bg-blue-50 font-normal text-blue-700'
                : 'font-light text-gray-600 hover:bg-gray-50 hover:text-gray-900',
        ]"
        @click="emit('navigate')"
    >
        <Icon
            :name="props.item.icon"
            :class="['size-4 shrink-0', isChildActive(props.item.to) ? 'text-blue-600' : 'text-gray-400']"
        />
        <span
            :class="[
                'flex-1 overflow-hidden whitespace-nowrap transition-opacity duration-150',
                props.expandedView ? 'opacity-100' : 'opacity-0',
            ]"
        >
            {{ props.item.label }}
        </span>
        <span
            v-if="props.item.badge && props.expandedView"
            class="ml-auto shrink-0 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-500"
        >
            {{ props.item.badge }}
        </span>
    </NuxtLink>
</template>
