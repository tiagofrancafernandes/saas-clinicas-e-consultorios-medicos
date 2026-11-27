import type { AppointmentStatus } from '~/types/appointment';

export const APPOINTMENT_STATUS_LABELS: Record<AppointmentStatus, string> = {
    SCHEDULED: 'Agendada',
    CONFIRMED: 'Confirmada',
    ARRIVED: 'Na recepção',
    IN_CONSULTATION: 'Em atendimento',
    COMPLETED: 'Realizada',
    CANCELLED: 'Cancelada',
    NO_SHOW: 'Não compareceu',
};

export const APPOINTMENT_STATUS_TONES: Record<
    AppointmentStatus,
    'info' | 'success' | 'warning' | 'danger' | 'neutral'
> = {
    SCHEDULED: 'info',
    CONFIRMED: 'success',
    ARRIVED: 'warning',
    IN_CONSULTATION: 'warning',
    COMPLETED: 'neutral',
    CANCELLED: 'danger',
    NO_SHOW: 'danger',
};

export const DOCUMENT_COLLECTION_LABELS: Record<string, string> = {
    exams: 'Exames',
    prescriptions: 'Receitas',
    certificates: 'Atestados',
    general: 'Documentos',
};
