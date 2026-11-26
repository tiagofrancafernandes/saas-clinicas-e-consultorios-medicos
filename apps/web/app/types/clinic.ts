export interface PatientRecord {
    id: string;
    name: string;
    phone: string;
    email?: string | null;
    tax_id?: string | null;
    whatsapp_verified: boolean;
    created_at: string;
}

export interface AppointmentRecord {
    id: string;
    patient_name: string;
    professional_name: string;
    room_name: string;
    scheduled_at: string;
    status: 'SCHEDULED' | 'CONFIRMED' | 'IN_CONSULTATION' | 'COMPLETED' | 'CANCELLED';
    billing_type: 'Private' | 'Insurance' | 'Package';
}

export interface MedicalRecordItem {
    id: string;
    patient_name: string;
    author_name: string;
    title: string;
    visibility: 'PRIVATE' | 'SHARED_TEAM' | 'PUBLIC_CLINIC';
    created_at: string;
}

export interface WaitlistItem {
    id: string;
    patient_name: string;
    phone: string;
    preferred_shift: 'MORNING' | 'AFTERNOON' | 'ANY';
    specialty: string;
    status: 'WAITING' | 'NOTIFIED' | 'BOOKED';
    created_at: string;
}

export interface ClinicUserRecord {
    id: number;
    name: string;
    email: string;
    tenant_id: string | null;
    roles: string[];
    role_permissions: string[];
    extra_permissions: string[];
    all_permissions: string[];
}

export interface WhatsAppInstanceSummary {
    id: string;
    label: string;
    phone_number: string;
    instance_name: string;
    category: string;
    is_default: boolean;
    active: boolean;
    attendant_name?: string | null;
}
