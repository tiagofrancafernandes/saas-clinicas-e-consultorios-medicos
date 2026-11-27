import type { AppointmentTab, PatientAppointment } from '~/types/appointment';
import type { PatientDocument } from '~/types/document';
import type { ClinicPortalMeta, PatientProfile, PortalSummary } from '~/types/portal';
import { useTenantApiClient } from './apiClient';

export function usePortalService() {
    const { request, fetchBlob } = useTenantApiClient();

    return {
        getSummary(): Promise<PortalSummary> {
            return request<PortalSummary>('/tenant/portal/summary');
        },

        getAppointments(tab: AppointmentTab): Promise<{ data: PatientAppointment[] }> {
            return request<{ data: PatientAppointment[] }>('/tenant/portal/appointments', {
                query: { tab },
            });
        },

        confirmAppointment(id: string): Promise<{ message: string; data: PatientAppointment }> {
            return request<{ message: string; data: PatientAppointment }>(`/tenant/portal/appointments/${id}/confirm`, {
                method: 'POST',
            });
        },

        cancelAppointment(id: string, reason?: string): Promise<{ message: string; data: PatientAppointment }> {
            return request<{ message: string; data: PatientAppointment }>(`/tenant/portal/appointments/${id}/cancel`, {
                method: 'POST',
                body: { reason: reason || null },
            });
        },

        getDocuments(): Promise<{ data: PatientDocument[] }> {
            return request<{ data: PatientDocument[] }>('/tenant/portal/documents');
        },

        downloadDocumentBlob(fileId: string): Promise<Blob> {
            return fetchBlob(`/tenant/portal/documents/${fileId}/download`);
        },

        getProfile(): Promise<{ patient: PatientProfile | null; clinic: ClinicPortalMeta }> {
            return request<{ patient: PatientProfile | null; clinic: ClinicPortalMeta }>('/tenant/portal/profile');
        },

        updateProfile(payload: {
            phone?: string;
            email?: string | null;
        }): Promise<{ message: string; patient: PatientProfile }> {
            return request<{ message: string; patient: PatientProfile }>('/tenant/portal/profile', {
                method: 'PUT',
                body: payload,
            });
        },
    };
}
