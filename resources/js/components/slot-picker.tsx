import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';

export type SlotSession = {
    id: string;
    /** Free text, e.g. "10:00 s.d. 12:00 WIB". */
    label: string;
    quota: number;
    taken: number;
};

export type SlotDay = {
    /** ISO date, e.g. 2026-11-04. */
    date: string;
    sessions: SlotSession[];
};

/**
 * Lets a parent pick an exam date and session. Sessions that are full stay
 * visible but cannot be chosen.
 */
export function SlotPicker({
    days,
    value,
    onValueChange,
}: {
    days: SlotDay[];
    value?: string;
    onValueChange: (sessionId: string) => void;
}) {
    const selectedDate = days.find((day) =>
        day.sessions.some((session) => session.id === value),
    )?.date;

    function pickDate(day: SlotDay) {
        const firstOpen = day.sessions.find(
            (session) => session.taken < session.quota,
        );

        if (firstOpen) {
            onValueChange(firstOpen.id);
        }
    }

    const activeDay = days.find((day) => day.date === selectedDate);

    return (
        <div className="grid gap-5">
            <div
                role="group"
                aria-label="Pilih tanggal"
                className="flex gap-2 overflow-x-auto pb-1"
            >
                {days.map((day) => {
                    const isFull = day.sessions.every(
                        (session) => session.taken >= session.quota,
                    );
                    const isActive = day.date === selectedDate;

                    return (
                        <button
                            key={day.date}
                            type="button"
                            disabled={isFull}
                            aria-pressed={isActive}
                            onClick={() => pickDate(day)}
                            className={cn(
                                'flex min-w-24 shrink-0 flex-col items-center gap-0.5 rounded-2xl border bg-card px-4 py-3 text-center shadow-xs transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                isActive
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'hover:border-sage-400',
                                isFull &&
                                    'cursor-not-allowed opacity-50 hover:border-border',
                            )}
                        >
                            <span className="text-xs font-medium opacity-80">
                                {formatDate(day.date, { weekday: 'short' })}
                            </span>
                            <span className="text-2xl leading-none font-bold tabular-nums">
                                {formatDate(day.date, { day: 'numeric' })}
                            </span>
                            <span className="text-xs font-medium opacity-80">
                                {formatDate(day.date, {
                                    month: 'short',
                                    year: 'numeric',
                                })}
                            </span>
                        </button>
                    );
                })}
            </div>

            {activeDay && (
                <div
                    role="radiogroup"
                    aria-label="Pilih sesi"
                    className="grid gap-2 sm:grid-cols-2"
                >
                    {activeDay.sessions.map((session) => {
                        const left = session.quota - session.taken;
                        const isFull = left <= 0;
                        const isActive = session.id === value;

                        return (
                            <button
                                key={session.id}
                                type="button"
                                role="radio"
                                aria-checked={isActive}
                                disabled={isFull}
                                onClick={() => onValueChange(session.id)}
                                className={cn(
                                    'flex items-center justify-between gap-3 rounded-xl border bg-card px-4 py-3 text-left shadow-xs transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                    isActive
                                        ? 'border-primary bg-sage-50 ring-1 ring-primary dark:bg-secondary/40'
                                        : 'hover:border-sage-400',
                                    isFull &&
                                        'cursor-not-allowed opacity-50 hover:border-border',
                                )}
                            >
                                <span className="text-sm font-semibold">
                                    {session.label}
                                </span>
                                <span
                                    className={cn(
                                        'text-xs font-medium',
                                        isFull
                                            ? 'text-destructive-foreground'
                                            : left <= 5
                                              ? 'text-warning'
                                              : 'text-muted-foreground',
                                    )}
                                >
                                    {isFull ? 'Penuh' : `Sisa ${left} kursi`}
                                </span>
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
