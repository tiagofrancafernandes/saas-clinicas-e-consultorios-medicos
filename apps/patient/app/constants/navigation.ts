export interface PatientNavItem {
    label: string;
    to: string;
    icon: string;
}

export const PATIENT_NAV_ITEMS: PatientNavItem[] = [
    { label: 'Início', to: '/', icon: 'heroicons:home' },
    { label: 'Minha Agenda', to: '/appointments', icon: 'heroicons:calendar-days' },
    { label: 'Documentos', to: '/documents', icon: 'heroicons:document-text' },
    { label: 'Meu Perfil', to: '/profile', icon: 'heroicons:user-circle' },
];
