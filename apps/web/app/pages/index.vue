<script setup lang="ts">
import { useAuthStore } from '~/stores/auth';

const auth = useAuthStore();

const quickModules = computed(() =>
    [
        {
            title: 'Agendamentos',
            desc: 'Agenda diária, confirmações e chamadas',
            to: '/appointments',
            icon: 'lucide:calendar-days',
            permission: 'appointments.view',
        },
        {
            title: 'Pacientes',
            desc: 'Cadastro com verificador de WhatsApp',
            to: '/patients',
            icon: 'lucide:users',
            permission: 'patients.view',
        },
        {
            title: 'Prontuários SOAP',
            desc: 'Evoluções clínicas e níveis de sigilo',
            to: '/medical-records',
            icon: 'lucide:file-text',
            permission: 'medical_records.view',
        },
        {
            title: 'Usuários & Permissões',
            desc: 'Papéis e permissões individuais (ACL)',
            to: '/users',
            icon: 'lucide:key-round',
            permission: 'users.view',
        },
        {
            title: 'Dados da Clínica',
            desc: 'Plano ativo, WhatsApp e configurações',
            to: '/clinic',
            icon: 'lucide:building-2',
            permission: 'clinic.settings.manage',
        },
    ].filter((mod) => auth.can(mod.permission)),
);
</script>

<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-[19px] font-normal tracking-tight text-gray-900">
                Olá, {{ auth.user?.name }}
            </h1>
            <p class="mt-0.5 text-[12px] text-gray-500">
                Perfil ativo: <span class="text-gray-800">{{ auth.primaryRole }}</span> · {{ auth.permissions.length }} permissões habilitadas.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <NuxtLink
                v-for="mod in quickModules"
                :key="mod.to"
                :to="mod.to"
                class="group rounded border border-gray-200 bg-white p-4 transition-colors hover:border-blue-300"
            >
                <div class="flex items-center justify-between">
                    <div class="flex size-8 items-center justify-center rounded border border-gray-200 bg-gray-50 text-gray-600 group-hover:border-blue-200 group-hover:bg-blue-50 group-hover:text-blue-600">
                        <Icon :name="mod.icon" class="size-4" />
                    </div>
                    <Icon name="lucide:arrow-right" class="size-3.5 text-gray-300 group-hover:text-blue-600" />
                </div>
                <h2 class="mt-3 text-[13px] font-normal text-gray-900">{{ mod.title }}</h2>
                <p class="mt-0.5 text-[11px] text-gray-400">{{ mod.desc }}</p>
            </NuxtLink>
        </div>
    </div>
</template>
