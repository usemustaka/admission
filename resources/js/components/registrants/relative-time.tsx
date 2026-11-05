import { formatDate } from '@/lib/format';

const MINUTE = 60_000;
const HOUR = 60 * MINUTE;
const DAY = 24 * HOUR;

/**
 * "3 hari lalu" style distance from now. Past the first months it falls back to months and years so a list
 * never shows "412 hari lalu".
 */
export function relativeTime(value: string, now: number = Date.now()): string {
    const elapsed = Math.max(now - new Date(value).getTime(), 0);

    if (elapsed < MINUTE) {
        return 'Baru saja';
    }

    if (elapsed < HOUR) {
        return `${Math.floor(elapsed / MINUTE)} menit lalu`;
    }

    if (elapsed < DAY) {
        return `${Math.floor(elapsed / HOUR)} jam lalu`;
    }

    const days = Math.floor(elapsed / DAY);

    if (days < 14) {
        return days === 1 ? 'Kemarin' : `${days} hari lalu`;
    }

    if (days < 60) {
        return `${Math.floor(days / 7)} minggu lalu`;
    }

    if (days < 365) {
        return `${Math.floor(days / 30)} bulan lalu`;
    }

    return `${Math.floor(days / 365)} tahun lalu`;
}

/**
 * Relative time with the exact date and time as a tooltip.
 */
export function RelativeTime({ value }: { value: string | null }) {
    if (!value) {
        return <span className="text-muted-foreground">-</span>;
    }

    return (
        <time
            dateTime={value}
            title={formatDate(value, {
                day: 'numeric',
                month: 'long',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            })}
        >
            {relativeTime(value)}
        </time>
    );
}
