const relativeFormatter = new Intl.RelativeTimeFormat('id-ID', {
    numeric: 'auto',
});

/**
 * "hari ini", "kemarin", "3 hari yang lalu", "2 bulan yang lalu" for a calendar date (YYYY-MM-DD), so an admin
 * can see how long a payment has been waiting without doing date maths.
 */
export function formatRelativeDay(
    value: string,
    now: Date = new Date(),
): string {
    const [year, month, day] = value.split('-').map(Number);
    const target = new Date(year, month - 1, day);
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const days = Math.round((target.getTime() - today.getTime()) / 86_400_000);
    const distance = Math.abs(days);

    if (distance < 31) {
        return relativeFormatter.format(days, 'day');
    }

    if (distance < 365) {
        return relativeFormatter.format(Math.round(days / 30), 'month');
    }

    return relativeFormatter.format(Math.round(days / 365), 'year');
}

/**
 * A date as YYYY-MM-DD from the local calendar fields. `toISOString()` gives the UTC date, which is still
 * yesterday for the first seven hours of a day in WIB.
 */
export function formatLocalDate(date: Date = new Date()): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

/** The first couple of item names, then how many more there are. */
export function summarizeItems(names: string[], visible = 2): string {
    if (names.length <= visible) {
        return names.join(', ');
    }

    return `${names.slice(0, visible).join(', ')} +${names.length - visible}`;
}

/** Ready-made reasons for turning a payment down, to fill the reason box with one tap. */
export const REJECTION_REASONS = [
    'Bukti transfer tidak terbaca',
    'Nominal tidak sesuai dengan bukti',
    'Dana belum masuk ke rekening',
] as const;
