<script setup lang="ts">
import type { NavGroup } from '~/config/navigation';

interface Props {
    group: NavGroup;
    compact?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    compact: false,
});

const route = useRoute();

const hasActiveChild = computed(() =>
    props.group.children.some((child) =>
        child.to === '/' ? route.path === '/' : route.path.startsWith(child.to),
    ),
);

const isExpanded = ref(hasActiveChild.value);

watch(hasActiveChild, (active) => {
    if (active) {
        isExpanded.value = true;
    }
});
</script>

<template>
    <div class="space-y-0.5 overflow-hidden">
        <button
            type="button"
            :class="[
                'flex h-8 w-full items-center justify-between overflow-hidden rounded px-2.5 text-left text-[12px] font-normal transition-colors group',
                hasActiveChild ? 'text-white bg-teal-700 text-white' : 'text-teal-700 hover:bg-teal-500 hover:text-white',
            ]"
            @click="isExpanded = !isExpanded"
        >
            <span class="flex min-w-0 items-center gap-2.5 overflow-hidden">
                <Icon
                    :name="group.icon"
                    class="size-4 shrink-0"
                    :class="[
                        hasActiveChild ? 'text-white' : 'text-teal-700',
                        'group-hover:bg-teal-500 group-hover:text-white',
                    ]"
                />
                <span
                    v-show="!compact"
                    class="whitespace-nowrap"
                >
                    {{ group.label }}
                </span>
            </span>
            <Icon
                v-show="!compact"
                name="lucide:chevron-right"
                class="size-3.5 shrink-0 text-teal-700 group-hover:bg-teal-500 group-hover:text-white transition-transform duration-150"
                :class="[
                    hasActiveChild ? 'text-white' : 'text-teal-700',
                    isExpanded ? 'rotate-90' : ''
                ]"
            />
        </button>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div v-show="isExpanded && !compact" class="space-y-0.5 overflow-hidden pl-4">
                <NuxtLink
                    v-for="child in group.children"
                    :key="child.to"
                    :to="child.to"
                    class="flex h-8 items-center gap-2.5 overflow-hidden rounded px-2.5 text-[12px] whitespace-nowrap transition-colors"
                    :class="
                        (child.to === '/' ? route.path === '/' : route.path.startsWith(child.to))
                            ? 'bg-teal-500 text-white font-normal'
                            : 'text-teal-700 hover:bg-teal-500 hover:text-white'
                    "
                >
                    <Icon :name="child.icon" class="size-3.5 shrink-0" />
                    <span class="truncate whitespace-nowrap">{{ child.label }}</span>
                </NuxtLink>
            </div>
        </Transition>
    </div>
</template>
