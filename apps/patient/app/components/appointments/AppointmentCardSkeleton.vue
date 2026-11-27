<template>
    <UiCard>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start gap-3.5 w-full">
                <UiSkeleton width-class="w-14" height-class="h-14" rounded-class="rounded-xl" class="shrink-0" />
                <div class="space-y-2 flex-1 max-w-md">
                    <div class="flex items-center gap-2">
                        <UiSkeleton width-class="w-36" height-class="h-4" />
                        <UiSkeleton width-class="w-20" height-class="h-5" rounded-class="rounded-full" />
                    </div>
                    <UiSkeleton width-class="w-48" height-class="h-3" />
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
