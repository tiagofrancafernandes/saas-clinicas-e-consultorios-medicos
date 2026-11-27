import type { PatientAppointment } from './appointment';

export interface PatientProfile {
    id: string;
    name: string;
    phone: string;
    email: string | null;
    birth_date: string | null;
}

export interface ClinicPortalMeta {
    name: string;
    timezone: string;
    cancellation_window_hours: number;
}

export interface PortalSummary {
    patient: PatientProfile | null;
    clinic: ClinicPortalMeta;
    upcoming_count: number;
    documents_count: number;
    next_appointment: PatientAppointment | null;
}
