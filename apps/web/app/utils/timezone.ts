export interface TimezoneOption {
    value: string;
    label: string;
}

export const COMMON_TIMEZONES: TimezoneOption[] = [
    { value: 'America/Sao_Paulo', label: 'Brasília / São Paulo (America/Sao_Paulo)' },
    { value: 'America/Manaus', label: 'Manaus (America/Manaus)' },
    { value: 'America/Fortaleza', label: 'Fortaleza (America/Fortaleza)' },
    { value: 'America/Cuiaba', label: 'Cuiabá (America/Cuiaba)' },
    { value: 'America/Belem', label: 'Belém (America/Belem)' },
    { value: 'America/Rio_Branco', label: 'Rio Branco (America/Rio_Branco)' },
    { value: 'America/Noronha', label: 'Fernando de Noronha (America/Noronha)' },
    { value: 'America/New_York', label: 'Nova York (America/New_York)' },
    { value: 'Europe/Lisbon', label: 'Lisboa (Europe/Lisbon)' },
    { value: 'UTC', label: 'UTC' },
];

export function buildTimezoneOptions(defaultTimezone?: string): TimezoneOption[] {
    const tz = defaultTimezone || 'America/Sao_Paulo';
    if (COMMON_TIMEZONES.some((item) => item.value === tz)) {
        return COMMON_TIMEZONES;
    }
    return [{ value: tz, label: `${tz} (Clínica)` }, ...COMMON_TIMEZONES];
}

export function getDatePartsInTimezone(date: Date, timeZone: string): { date: string; time: string; hour: string; minute: string } {
    try {
        const parts = new Intl.DateTimeFormat('en-CA', {
            timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        }).formatToParts(date);

        const map = Object.fromEntries(parts.map((p) => [p.type, p.value]));
        const hour = map.hour === '24' ? '00' : (map.hour || '09');
        const minute = map.minute || '00';
        return {
            date: `${map.year}-${map.month}-${map.day}`,
            time: `${hour}:${minute}`,
            hour,
            minute,
        };
    } catch {
        const iso = date.toISOString();
        return {
            date: iso.slice(0, 10),
            time: iso.slice(11, 16),
            hour: iso.slice(11, 13),
            minute: iso.slice(14, 16),
        };
    }
}

export function zonedDateTimeToUtcMs(dateStr: string, timeStr: string, timeZone: string): number {
    const [year, month, day] = dateStr.split('-').map(Number);
    const [hour, minute] = timeStr.split(':').map(Number);
    if (!year || !month || !day || Number.isNaN(hour) || Number.isNaN(minute)) {
        return Number.NaN;
    }

    const utcGuess = Date.UTC(year, month - 1, day, hour, minute, 0);
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    }).formatToParts(new Date(utcGuess));

    const map = Object.fromEntries(parts.map((p) => [p.type, p.value]));
    const zonedHour = map.hour === '24' ? 0 : Number(map.hour);
    const asUtc = Date.UTC(
        Number(map.year),
        Number(map.month) - 1,
        Number(map.day),
        zonedHour,
        Number(map.minute),
        Number(map.second),
    );
    return utcGuess - (asUtc - utcGuess);
}

export function formatUtcInTimezone(utcIso: string, timeZone: string, withDate = false): string {
    if (!utcIso) return '—';
    const d = new Date(utcIso);
    if (Number.isNaN(d.getTime())) return utcIso;
    try {
        return new Intl.DateTimeFormat('pt-BR', {
            timeZone,
            ...(withDate ? { day: '2-digit', month: '2-digit', year: 'numeric' } : {}),
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        }).format(d);
    } catch {
        return utcIso;
    }
}
