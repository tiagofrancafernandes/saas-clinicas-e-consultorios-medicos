export type AppointmentStatus =
    | 'SCHEDULED'
    | 'CONFIRMED'
    | 'ARRIVED'
    | 'IN_CONSULTATION'
    | 'COMPLETED'
    | 'CANCELLED'
    | 'NO_SHOW';

export type AppointmentTab = 'upcoming' | 'history' | 'cancelled';

export interface AppointmentProfessional {
    id: string;
    name: string;
    specialty: string | null;
}

export interface AppointmentRoom {
    id: string;
    name: string;
}

export interface AppointmentTypeInfo {
    id: string;
    name: string;
    duration_minutes: number | null;
}

export interface PatientAppointment {
    id: string;
    status: AppointmentStatus;
    start_time_utc: string;
    end_time_utc: string;
    notes: string | null;
    cancellation_reason: string | null;
    can_confirm: boolean;
    can_cancel: boolean;
    cancellation_deadline_utc: string;
    professional: AppointmentProfessional | null;
    clinic_room: AppointmentRoom | null;
    appointment_type: AppointmentTypeInfo | null;
}
