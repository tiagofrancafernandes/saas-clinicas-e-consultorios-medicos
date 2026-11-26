<script setup lang="ts">
interface TabItem {
    key: string;
    label: string;
    icon?: string;
    badge?: string | number;
}

interface Props {
    title: string;
    subtitle?: string;
    backTo: string;
    backLabel?: string;
    tabs: TabItem[];
    activeTab: string;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    subtitle: undefined,
    backLabel: 'Voltar',
    loading: false,
});

const emit = defineEmits<{
    'update:activeTab': [key: string];
}>();
</script>

<template>
    <div>
        <div class="mb-3">
            <NuxtLink
                :to="props.backTo"
                class="inline-flex items-center gap-1.5 text-[12px] font-light text-gray-500 transition-colors hover:text-gray-900"
            >
                <Icon name="lucide:arrow-left" class="size-3.5" />
                <span>{{ props.backLabel }}</span>
            </NuxtLink>
        </div>

        <div class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div class="flex items-center gap-3">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-[20px] font-medium tracking-tight text-gray-900">{{ props.title }}</h1>
                        <slot name="badge" />
                    </div>
                    <p v-if="props.subtitle" class="mt-0.5 text-[12px] font-light text-gray-500">
                        {{ props.subtitle }}
                    </p>
                </div>
            </div>
            <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
                <slot name="actions" />
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="mb-5 flex items-center gap-1 overflow-x-auto border-b border-gray-200">
            <button
                v-for="tab in props.tabs"
                :key="tab.key"
                type="button"
                :class="[
                    'flex cursor-pointer items-center gap-1.5 whitespace-nowrap border-b-2 px-3 py-2 text-[12px] transition-colors',
                    props.activeTab === tab.key
                        ? 'border-blue-600 font-normal text-blue-600'
                        : 'border-transparent font-light text-gray-500 hover:text-gray-800',
                ]"
                @click="emit('update:activeTab', tab.key)"
            >
                <Icon v-if="tab.icon" :name="tab.icon" class="size-3.5" />
                <span>{{ tab.label }}</span>
                <span
                    v-if="tab.badge !== undefined"
                    class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-600"
                >
                    {{ tab.badge }}
                </span>
            </button>
        </div>

        <div v-if="props.loading" class="rounded-md border border-gray-200 bg-white py-16 text-center">
            <Icon name="lucide:loader-2" class="mx-auto size-5 animate-spin text-blue-600" />
            <div class="mt-2 text-[12px] font-light text-gray-400">Carregando informações...</div>
        </div>

        <slot v-else />
    </div>
</template>
