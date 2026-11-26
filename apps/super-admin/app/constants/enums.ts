import type { SubscriptionStatus, WhatsAppCategory } from '~/types/tenant';
import type { UserRole } from '~/types/user';

export const SUBSCRIPTION_STATUS_META: Record<
    SubscriptionStatus,
    { label: string; color: 'green' | 'yellow' | 'red' | 'gray' | 'blue' }
> = {
    ACTIVE: { label: 'Ativo', color: 'green' },
    PAST_DUE: { label: 'Em atraso', color: 'yellow' },
    SUSPENDED: { label: 'Suspenso', color: 'red' },
    CANCELLED: { label: 'Cancelado', color: 'gray' },
    COURTESY: { label: 'Cortesia', color: 'blue' },
};

export const WHATSAPP_CATEGORY_OPTIONS: { value: WhatsAppCategory; label: string }[] = [
    { value: 'DEFAULT', label: 'Padrão' },
    { value: 'REMINDERS', label: 'Lembretes' },
    { value: 'MARKETING', label: 'Marketing' },
    { value: 'SUPPORT', label: 'Suporte' },
    { value: 'BILLING', label: 'Cobrança' },
];

export const USER_ROLE_OPTIONS: { value: UserRole; label: string }[] = [
    { value: 'SUPER_ADMIN', label: 'Super Admin' },
    { value: 'CLINIC_ADMIN', label: 'Admin da Clínica' },
    { value: 'PROFESSIONAL', label: 'Profissional' },
    { value: 'RECEPTIONIST', label: 'Recepcionista' },
    { value: 'PATIENT', label: 'Paciente' },
];

export const AVAILABLE_PERMISSIONS: string[] = [
    'tenants.manage',
    'whatsapp.manage',
    'central_audit.view',
    'users.view',
    'users.create',
    'users.update',
    'users.permissions.assign',
    'patients.view',
    'patients.create',
    'patients.update',
    'appointments.view',
    'appointments.create',
    'appointments.cancel',
    'appointments.summon',
    'waitlist.view',
    'waitlist.manage',
    'medical_records.view',
    'medical_records.create',
    'delegations.manage',
    'files.view',
    'files.upload',
    'clinic.settings.manage',
];

export const TIMEZONE_OPTIONS: { value: string; label: string }[] = [
    { value: 'America/Sao_Paulo', label: 'America/Sao_Paulo (BRT)' },
    { value: 'America/Manaus', label: 'America/Manaus (AMT)' },
    { value: 'America/Belem', label: 'America/Belem (BRT)' },
    { value: 'America/Fortaleza', label: 'America/Fortaleza (BRT)' },
    { value: 'America/Cuiaba', label: 'America/Cuiaba (AMT)' },
    { value: 'UTC', label: 'UTC' },
];
