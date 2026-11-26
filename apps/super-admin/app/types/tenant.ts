export type SubscriptionStatus = 'ACTIVE' | 'PAST_DUE' | 'SUSPENDED' | 'CANCELLED' | 'COURTESY';

export type WhatsAppCategory = 'DEFAULT' | 'REMINDERS' | 'MARKETING' | 'SUPPORT' | 'BILLING';

export interface Subscription {
    id: string;
    tenant_id: string;
    status: SubscriptionStatus;
    billing_interval: string;
    current_period_start_utc: string;
    current_period_end_utc: string;
    is_exempt: boolean;
    created_at?: string;
}

export interface Tenant {
    id: string;
    name: string;
    slug: string;
    tax_id: string | null;
    timezone: string;
    settings: Record<string, unknown>;
    subscriptions?: Subscription[];
    created_at: string;
    updated_at?: string;
}

export interface CreateTenantPayload {
    name: string;
    slug: string;
    tax_id?: string | null;
    timezone?: string;
    settings?: Record<string, unknown>;
}

export interface WhatsAppInstance {
    id: string;
    label: string;
    phone_number: string;
    instance_name: string;
    token?: string;
    category: WhatsAppCategory;
    is_default: boolean;
    active: boolean;
    attendant_name: string | null;
    settings: Record<string, unknown>;
    created_at?: string;
}

export interface CreateWhatsAppInstancePayload {
    label: string;
    phone_number: string;
    instance_name: string;
    token: string;
    category: WhatsAppCategory;
    is_default?: boolean;
    active?: boolean;
    attendant_name?: string | null;
}

export interface UpdateWhatsAppInstancePayload {
    label?: string;
    phone_number?: string;
    instance_name?: string;
    token?: string;
    category?: WhatsAppCategory;
    is_default?: boolean;
    active?: boolean;
    attendant_name?: string | null;
}
