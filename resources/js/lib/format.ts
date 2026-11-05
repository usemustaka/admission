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

const relativeTimeFormatter = new Intl.RelativeTimeFormat('id-ID', {
    numeric: 'auto',
});

const relativeTimeUnits: [Intl.RelativeTimeFormatUnit, number][] = [
    ['day', 86_400],
    ['hour', 3_600],
    ['minute', 60],
];

/** "5 menit yang lalu", "kemarin": how long ago a moment was. */
export function formatRelativeTime(value: string | Date): string {
    const date = typeof value === 'string' ? new Date(value) : value;
    const seconds = Math.round((date.getTime() - Date.now()) / 1000);

    for (const [unit, size] of relativeTimeUnits) {
        if (Math.abs(seconds) >= size) {
            return relativeTimeFormatter.format(
                Math.round(seconds / size),
                unit,
            );
        }
    }

    return 'baru saja';
}
