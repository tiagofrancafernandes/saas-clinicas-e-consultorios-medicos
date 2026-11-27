<template>
    <UiCard>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3 flex-1">
                <UiSkeleton width-class="w-10" height-class="h-10" rounded-class="rounded-lg" class="shrink-0" />
                <div class="space-y-2 flex-1 max-w-sm">
                    <UiSkeleton width-class="w-48" height-class="h-4" />
                    <UiSkeleton width-class="w-32" height-class="h-3" />
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <UiSkeleton width-class="w-24" height-class="h-8" rounded-class="rounded-lg" />
                <UiSkeleton width-class="w-20" height-class="h-8" rounded-class="rounded-lg" />
            </div>
        </div>
    </UiCard>
</template>
