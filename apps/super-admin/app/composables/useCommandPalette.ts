import type { CommandAction } from '~/types/navigation';

export function useCommandPalette() {
    const isOpen = useState<boolean>('sa_command_palette_open', () => false);
    const customActions = useState<CommandAction[]>('sa_command_actions', () => []);

    const defaultActions: CommandAction[] = [
        {
            id: 'nav-dashboard',
            label: 'Ir para Dashboard',
            description: 'Visão geral da plataforma',
            group: 'Navegação',
            icon: 'lucide:layout-grid',
            to: '/',
        },
        {
            id: 'nav-tenants',
            label: 'Listar clínicas (Tenants)',
            description: 'Gerenciar clínicas cadastradas e status',
            group: 'Clínicas',
            icon: 'lucide:building-2',
            to: '/tenants',
        },
        {
            id: 'action-create-tenant',
            label: 'Cadastrar nova clínica',
            description: 'Provisionar novo tenant e assinatura inicial',
            group: 'Ações rápidas',
            icon: 'lucide:plus-circle',
            to: '/tenants?create=1',
        },
        {
            id: 'nav-subscriptions',
            label: 'Listar assinaturas e planos',
            description: 'Acompanhar status de cobrança e cortesias',
            group: 'Clínicas',
            icon: 'lucide:credit-card',
            to: '/subscriptions',
        },
        {
            id: 'nav-users',
            label: 'Listar usuários e permissões',
            description: 'Gerenciar papéis e ACL central',
            group: 'Usuários',
            icon: 'lucide:users',
            to: '/users',
        },
        {
            id: 'action-create-user',
            label: 'Cadastrar novo usuário',
            description: 'Criar usuário Super Admin ou de clínica',
            group: 'Ações rápidas',
            icon: 'lucide:user-plus',
            to: '/users?create=1',
        },
        {
            id: 'nav-audit',
            label: 'Auditoria Central',
            description: 'Eventos críticos e logs de provisionamento',
            group: 'Sistema',
            icon: 'lucide:shield-check',
            to: '/audit-logs',
        },
        {
            id: 'nav-settings',
            label: 'Configurações do Super Admin',
            description: 'Preferências globais e integração Evolution API',
            group: 'Sistema',
            icon: 'lucide:settings',
            to: '/settings',
        },
    ];

    const allActions = computed(() => [...customActions.value, ...defaultActions]);

    function open(): void {
        isOpen.value = true;
    }

    function close(): void {
        isOpen.value = false;
    }

    function toggle(): void {
        isOpen.value = !isOpen.value;
    }

    function registerActions(actions: CommandAction[]): void {
        customActions.value = actions;
    }

    return {
        isOpen,
        allActions,
        open,
        close,
        toggle,
        registerActions,
    };
}
