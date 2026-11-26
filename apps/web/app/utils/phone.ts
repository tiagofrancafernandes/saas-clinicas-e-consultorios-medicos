export interface PhoneValidationResult {
    valid: boolean;
    digits: string;
    error?: string;
}

export function normalizePhoneDigits(raw: string): string {
    return raw.replace(/\D/g, '');
}

export function validateWhatsAppNumber(raw: string): PhoneValidationResult {
    const digits = normalizePhoneDigits(raw);

    if (!digits) {
        return {
            valid: false,
            digits,
            error: 'Informe o número de destino com DDI, DDD e número.',
        };
    }

    // Brazilian format: DDI (55) + DDD (2 digits, 11-99) + 8 or 9 digits
    if (digits.startsWith('55')) {
        if (!/^55[1-9]\d{9,10}$/.test(digits)) {
            return {
                valid: false,
                digits,
                error: 'Número brasileiro inválido. Use DDI 55 + DDD + número (12 ou 13 dígitos, ex: 5541984402684).',
            };
        }

        return { valid: true, digits };
    }

    // Generic international E.164 without '+' (DDI + area + number: 10 to 15 digits)
    if (digits.length < 10 || digits.length > 15) {
        return {
            valid: false,
            digits,
            error: 'Número inválido. Informe DDI + DDD + número (entre 10 e 15 dígitos).',
        };
    }

    return { valid: true, digits };
}
