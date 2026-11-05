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
