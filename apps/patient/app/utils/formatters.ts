import type { PatientAppointment } from '~/types/appointment';

export function formatDateTimeInTz(isoUtc: string | null | undefined, timezone = 'America/Sao_Paulo'): string {
    if (!isoUtc) {
        return '-';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        timeZone: timezone,
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(isoUtc));
}

export function formatDayMonthInTz(
    isoUtc: string,
    timezone = 'America/Sao_Paulo'
): { day: string; month: string; weekday: string; time: string } {
    const date = new Date(isoUtc);
    const day = new Intl.DateTimeFormat('pt-BR', { timeZone: timezone, day: '2-digit' }).format(date);
    const month = new Intl.DateTimeFormat('pt-BR', { timeZone: timezone, month: 'short' })
        .format(date)
        .replace('.', '')
        .toUpperCase();
    const weekday = new Intl.DateTimeFormat('pt-BR', { timeZone: timezone, weekday: 'long' }).format(date);
    const time = new Intl.DateTimeFormat('pt-BR', {
        timeZone: timezone,
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);

    return { day, month, weekday, time };
}

export function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function downloadAppointmentIcs(appointment: PatientAppointment, clinicName: string): void {
    const formatIcsDate = (iso: string): string =>
        new Date(iso)
            .toISOString()
            .replace(/[-:]/g, '')
            .replace(/\.\d{3}Z$/, 'Z');

    const title = appointment.appointment_type?.name || 'Consulta Médica';
    const professional = appointment.professional?.name ? ` com ${appointment.professional.name}` : '';
    const location = [clinicName, appointment.clinic_room?.name].filter(Boolean).join(' - ');

    const lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//ClinicSaaS//PatientPortal//PT-BR',
        'BEGIN:VEVENT',
        `UID:${appointment.id}@patient-portal`,
        `DTSTAMP:${formatIcsDate(new Date().toISOString())}`,
        `DTSTART:${formatIcsDate(appointment.start_time_utc)}`,
        `DTEND:${formatIcsDate(appointment.end_time_utc)}`,
        `SUMMARY:${title}${professional}`,
        `LOCATION:${location}`,
        'END:VEVENT',
        'END:VCALENDAR',
    ];

    const blob = new Blob([lines.join('\r\n')], { type: 'text/calendar;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `consulta-${appointment.id.slice(0, 8)}.ics`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}
