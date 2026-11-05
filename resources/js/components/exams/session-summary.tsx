import { CalendarDays, Clock, UsersRound } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/format';
import type { ExamSessionInfo } from '@/types/exam';

export function formatExamDate(date: string): string {
    return formatDate(date, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

/**
 * Seats taken against the quota. When the committee overrode a full session the taken count passes the
 * quota, which shows as a warning instead of a negative number of seats left.
 */
export function SeatCount({ taken, quota }: { taken: number; quota: number }) {
    return (
        <span className="inline-flex flex-wrap items-center gap-2 tabular-nums">
            <span className="font-semibold">
                {taken}/{quota}
            </span>
            {taken > quota && <Badge variant="warning">Melebihi kuota</Badge>}
            {taken === quota && <Badge variant="muted">Penuh</Badge>}
        </span>
    );
}

/**
 * What a session is, in a few lines: when, which ruleset, how full it is and how far the scoring has got.
 */
export function SessionSummary({ session }: { session: ExamSessionInfo }) {
    return (
        <div className="grid gap-2 text-sm">
            <p className="flex flex-wrap items-center gap-x-4 gap-y-1.5">
                <span className="inline-flex items-center gap-1.5 font-semibold">
                    <CalendarDays className="size-4 text-muted-foreground" />
                    {formatExamDate(session.date)}
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <Clock className="size-4 text-muted-foreground" />
                    {session.label}
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <UsersRound className="size-4 text-muted-foreground" />
                    <SeatCount taken={session.taken} quota={session.quota} />
                </span>
            </p>
            <p className="text-muted-foreground">
                {session.ruleset} · {session.organization ?? 'Semua lembaga'}
            </p>
            <p className="flex flex-wrap gap-2">
                {session.missing > 0 && (
                    <Badge variant="warning">
                        {session.missing} belum dinilai lengkap
                    </Badge>
                )}
                {session.passed > 0 && (
                    <Badge variant="success">{session.passed} tuntas</Badge>
                )}
                {session.failed > 0 && (
                    <Badge variant="danger">
                        {session.failed} belum tuntas
                    </Badge>
                )}
            </p>
        </div>
    );
}
