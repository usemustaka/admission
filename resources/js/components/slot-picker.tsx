import { Check, TriangleAlert } from 'lucide-react';
import { FormField } from '@/components/form-field';
import { Textarea } from '@/components/ui/textarea';
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
 * visible but cannot be chosen. The date row scrolls sideways; sessions are a
 * list of full-width rows that grow to two columns only in a wide container.
 *
 * With `allowFull` (for the committee) full sessions can be chosen too, marked
 * "Kuota penuh". Choosing one other than `currentValue` asks for the reason
 * through `overrideReason`, which the caller sends along.
 */
export function SlotPicker({
    days,
    value,
    onValueChange,
    allowFull = false,
    currentValue,
    overrideReason = '',
    onOverrideReasonChange,
    overrideError,
}: {
    days: SlotDay[];
    value?: string;
    onValueChange: (sessionId: string) => void;
    allowFull?: boolean;
    currentValue?: string;
    overrideReason?: string;
    onOverrideReasonChange?: (reason: string) => void;
    overrideError?: string;
}) {
    const selectedDate = days.find((day) =>
        day.sessions.some((session) => session.id === value),
    )?.date;

    function pickDate(day: SlotDay) {
        const firstOpen = day.sessions.find(
            (session) => session.taken < session.quota,
        );
        const first = firstOpen ?? (allowFull ? day.sessions[0] : undefined);

        if (first) {
            onValueChange(first.id);
        }
    }

    const activeDay = days.find((day) => day.date === selectedDate);
    const chosen = activeDay?.sessions.find((session) => session.id === value);
    const askReason =
        allowFull &&
        chosen !== undefined &&
        chosen.taken >= chosen.quota &&
        chosen.id !== currentValue;

    return (
        <div className="grid gap-5">
            <div className="grid gap-2">
                <p className="text-[15px] font-semibold">Pilih tanggal</p>
                <div
                    role="group"
                    aria-label="Pilih tanggal"
                    className="no-scrollbar -mx-1 flex gap-2 overflow-x-auto px-1 py-1"
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
                                disabled={isFull && !allowFull}
                                aria-pressed={isActive}
                                onClick={() => pickDate(day)}
                                className={cn(
                                    'relative flex min-h-24 min-w-24 shrink-0 flex-col items-center justify-center gap-0.5 rounded-2xl border px-4 py-3 text-center transition-colors outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                                    isActive
                                        ? 'border-neutral-900 bg-primary text-primary-foreground ring-1 ring-neutral-900'
                                        : 'border-input bg-card hover:border-neutral-500',
                                    isFull &&
                                        !allowFull &&
                                        'cursor-not-allowed opacity-50 hover:border-input',
                                )}
                            >
                                {isActive && (
                                    <Check
                                        className="absolute top-2 right-2 size-4"
                                        strokeWidth={3}
                                    />
                                )}
                                <span className="text-sm font-medium">
                                    {formatDate(day.date, { weekday: 'short' })}
                                </span>
                                <span className="text-2xl leading-none font-bold tabular-nums">
                                    {formatDate(day.date, { day: 'numeric' })}
                                </span>
                                <span className="text-sm font-medium">
                                    {formatDate(day.date, {
                                        month: 'short',
                                        year: 'numeric',
                                    })}
                                </span>
                                {isFull && (
                                    <span className="text-xs font-semibold">
                                        {allowFull ? 'Kuota penuh' : 'Penuh'}
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </div>
            </div>

            {activeDay && (
                <div className="grid gap-2">
                    <p className="text-[15px] font-semibold">Pilih sesi</p>
                    <div
                        role="radiogroup"
                        aria-label="Pilih sesi"
                        className="grid gap-2 @2xl:grid-cols-2"
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
                                    disabled={isFull && !allowFull}
                                    onClick={() => onValueChange(session.id)}
                                    className={cn(
                                        'flex min-h-14 items-center justify-between gap-3 rounded-xl border bg-card px-4 py-3 text-left transition-colors outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                                        isActive
                                            ? 'border-neutral-900 bg-lime-50 ring-1 ring-neutral-900 dark:border-lime-400 dark:bg-lime-400/10 dark:ring-lime-400'
                                            : 'border-input hover:border-neutral-500',
                                        isFull &&
                                            !allowFull &&
                                            'cursor-not-allowed opacity-50 hover:border-input',
                                    )}
                                >
                                    <span className="flex items-center gap-2 text-[15px] font-semibold">
                                        {isActive && (
                                            <Check
                                                className="size-4 shrink-0"
                                                strokeWidth={3}
                                            />
                                        )}
                                        {session.label}
                                    </span>
                                    <span
                                        className={cn(
                                            'inline-flex shrink-0 items-center gap-1 text-sm font-medium',
                                            isFull
                                                ? allowFull
                                                    ? 'font-semibold text-warning'
                                                    : 'text-destructive-foreground'
                                                : left <= 5
                                                  ? 'text-warning'
                                                  : 'text-muted-foreground',
                                        )}
                                    >
                                        {isFull && allowFull && (
                                            <TriangleAlert className="size-4" />
                                        )}
                                        {isFull
                                            ? allowFull
                                                ? `Kuota penuh (${session.taken}/${session.quota})`
                                                : 'Penuh'
                                            : `Sisa ${left} kursi`}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </div>
            )}

            {askReason && (
                <FormField
                    label="Alasan memakai sesi yang sudah penuh"
                    hint="Kuota sesi ini sudah terpenuhi. Alasanmu dicatat di catatan pribadi anak."
                    error={overrideError}
                    required
                >
                    {(control) => (
                        <Textarea
                            {...control}
                            value={overrideReason}
                            onChange={(event) =>
                                onOverrideReasonChange?.(event.target.value)
                            }
                            maxLength={500}
                            placeholder="Misalnya: titipan kepala sekolah, atau pindah dari sesi yang dibatalkan"
                        />
                    )}
                </FormField>
            )}
        </div>
    );
}
