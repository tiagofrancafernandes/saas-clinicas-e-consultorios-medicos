<template>
    <div class="min-h-screen bg-[#f8fafc] text-slate-900 flex flex-col">
        <PatientNavbar />
        <main class="flex-1 w-full max-w-5xl mx-auto px-4 sm:px-6 pt-20 pb-24 md:pb-10">
            <slot />
        </main>
        <PatientBottomNav />
    </div>
</template>
