import type { NavGroup } from '~/types/navigation';

export const SIDEBAR_NAV_GROUPS: NavGroup[] = [
    {
        title: 'Visão Geral',
        items: [
            {
                id: 'dashboard',
                label: 'Dashboard',
                icon: 'lucide:layout-grid',
                to: '/',
            },
        ],
    },
    {
        title: 'Gestão SaaS',
        items: [
            {
                id: 'tenants-group',
                label: 'Clínicas (Tenants)',
                icon: 'lucide:building-2',
                children: [
                    {
                        label: 'Listar clínicas',
                        to: '/tenants',
                    },
                    {
                        label: 'Assinaturas e planos',
                        to: '/subscriptions',
                    },
                ],
            },
            {
                id: 'users-group',
                label: 'Usuários e ACL',
                icon: 'lucide:users',
                children: [
                    {
                        label: 'Todos os usuários',
                        to: '/users',
                    },
                ],
            },
        ],
    },
    {
        title: 'Sistema',
        items: [
            {
                id: 'audit',
                label: 'Auditoria Central',
                icon: 'lucide:shield-check',
                to: '/audit-logs',
            },
            {
                id: 'settings',
                label: 'Configurações',
                icon: 'lucide:settings',
                to: '/settings',
            },
        ],
    },
];
