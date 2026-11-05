import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';

const HOUR = 3_600_000;
const DAY = 24 * HOUR;

/** From how many days of waiting the committee should look at a registrant first. */
const LATE_AFTER_DAYS = 3;

/**
 * How long something has waited, without "lalu": "5 jam", "3 hari", "2 minggu".
 */
export function waitingFor(value: string, now: number = Date.now()): string {
    const elapsed = Math.max(now - new Date(value).getTime(), 0);

    if (elapsed < HOUR) {
        return 'Baru saja';
    }

    if (elapsed < DAY) {
        return `${Math.floor(elapsed / HOUR)} jam`;
    }

    const days = Math.floor(elapsed / DAY);

    return days < 14 ? `${days} hari` : `${Math.floor(days / 7)} minggu`;
}

/**
 * The "Menunggu sejak" cell: how long a registrant in the check step has waited since the data was sent in, with
 * the exact moment as a tooltip. Registrants that are not waiting for the committee show a dash.
 */
export function WaitingSince({
    value,
    waiting,
}: {
    value: string | null;
    waiting: boolean;
}) {
    if (!waiting || !value) {
        return <span className="text-muted-foreground">-</span>;
    }

    const late = Date.now() - new Date(value).getTime() > LATE_AFTER_DAYS * DAY;

    return (
        <time
            dateTime={value}
            title={`Dikirim ${formatDate(value, {
                day: 'numeric',
                month: 'long',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            })}`}
            className={cn(
                'font-medium tabular-nums',
                late && 'text-amber-700 dark:text-amber-300',
            )}
        >
            {waitingFor(value)}
        </time>
    );
}
