const rupiahFormatter = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

export function formatRupiah(amount: number): string {
    return rupiahFormatter.format(amount).replace(/\s/gu, '');
}

export function formatDate(
    value: string | Date,
    options: Intl.DateTimeFormatOptions = {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    },
): string {
    const date = typeof value === 'string' ? new Date(value) : value;

    return new Intl.DateTimeFormat('id-ID', options).format(date);
}

export const NIK_LENGTH = 16;

export function digitsOnly(value: string): string {
    return value.replace(/\D/gu, '');
}

export function isCompleteNik(value: string): boolean {
    return digitsOnly(value).length === NIK_LENGTH;
}

/**
 * Normalizes an Indonesian phone number (08xx, +628xx, 628xx) to 628xx.
 */
export function normalizePhone(phone: string): string {
    const digits = digitsOnly(phone);

    if (digits.startsWith('0')) {
        return `62${digits.slice(1)}`;
    }

    if (digits.startsWith('8')) {
        return `62${digits}`;
    }

    return digits;
}

export function whatsappUrl(phone: string, text?: string): string {
    const base = `https://wa.me/${normalizePhone(phone)}`;

    return text ? `${base}?text=${encodeURIComponent(text)}` : base;
}
