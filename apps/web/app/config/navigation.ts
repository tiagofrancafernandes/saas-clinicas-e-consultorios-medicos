export interface NavSubItem {
    label: string;
    to: string;
    icon: string;
    permission?: string;
}

export interface NavGroup {
    id: string;
    label: string;
    icon: string;
    children: NavSubItem[];
}

export interface CommandAction {
    id: string;
    label: string;
    description: string;
    icon: string;
    to: string;
    permission?: string;
}

export const SIDEBAR_GROUPS: NavGroup[] = [
    {
        id: 'overview',
        label: 'Visão Geral',
        icon: 'lucide:layout-dashboard',
        children: [
            { label: 'Painel da Clínica', to: '/', icon: 'lucide:activity' },
        ],
    },
    {
        id: 'attendance',
        label: 'Atendimento',
        icon: 'lucide:calendar-clock',
        children: [
            { label: 'Agendamentos', to: '/appointments', icon: 'lucide:calendar-days', permission: 'appointments.view' },
            { label: 'Fila de Espera', to: '/waitlist', icon: 'lucide:clock-4', permission: 'waitlist.view' },
        ],
    },
    {
        id: 'clinical',
        label: 'Pacientes & Clínico',
        icon: 'lucide:heart-pulse',
        children: [
            { label: 'Pacientes', to: '/patients', icon: 'lucide:users', permission: 'patients.view' },
            { label: 'Prontuários SOAP', to: '/medical-records', icon: 'lucide:file-text', permission: 'medical_records.view' },
        ],
    },
    {
        id: 'administration',
        label: 'Administração',
        icon: 'lucide:shield-check',
        children: [
            { label: 'Usuários & Permissões', to: '/users', icon: 'lucide:key-round', permission: 'users.view' },
            { label: 'WhatsApp da Clínica', to: '/whatsapp', icon: 'lucide:message-circle', permission: 'clinic.settings.manage' },
            { label: 'Dados da Clínica', to: '/clinic', icon: 'lucide:building-2' },
        ],
    },
];

export const COMMAND_ACTIONS: CommandAction[] = [
    { id: 'cmd-dashboard', label: 'Ir para Painel da Clínica', description: 'Visão geral e indicadores', icon: 'lucide:layout-dashboard', to: '/' },
    { id: 'cmd-patients-list', label: 'Listar pacientes', description: 'Consultar cadastro e verificação WhatsApp', icon: 'lucide:users', to: '/patients', permission: 'patients.view' },
    { id: 'cmd-patients-create', label: 'Cadastrar paciente', description: 'Abrir tela de pacientes para novo registro', icon: 'lucide:user-plus', to: '/patients?action=create', permission: 'patients.create' },
    { id: 'cmd-appointments-list', label: 'Listar agendamentos', description: 'Agenda diária, confirmações e chamadas', icon: 'lucide:calendar-days', to: '/appointments', permission: 'appointments.view' },
    { id: 'cmd-appointments-create', label: 'Cadastrar agendamento', description: 'Reservar horário com trava pessimista', icon: 'lucide:calendar-plus', to: '/appointments?action=create', permission: 'appointments.create' },
    { id: 'cmd-waitlist', label: 'Listar fila de espera', description: 'Pacientes aguardando encaixe', icon: 'lucide:clock-4', to: '/waitlist', permission: 'waitlist.view' },
    { id: 'cmd-records', label: 'Listar prontuários SOAP', description: 'Evoluções clínicas e sigilo médico', icon: 'lucide:file-text', to: '/medical-records', permission: 'medical_records.view' },
    { id: 'cmd-users', label: 'Gerenciar usuários e permissões', description: 'Papéis e permissões extras (ACL)', icon: 'lucide:key-round', to: '/users', permission: 'users.view' },
    { id: 'cmd-whatsapp', label: 'Administrar WhatsApp da clínica', description: 'Ativar, definir padrão, categoria e atendente', icon: 'lucide:message-circle', to: '/whatsapp', permission: 'clinic.settings.manage' },
    { id: 'cmd-clinic', label: 'Dados da clínica', description: 'Nome, plano ativo e configurações', icon: 'lucide:building-2', to: '/clinic' },
];
