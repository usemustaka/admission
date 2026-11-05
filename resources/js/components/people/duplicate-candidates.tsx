import { TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import { PersonAvatar } from '@/components/person-avatar';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/format';
import type { DuplicateCandidate } from '@/types/people';

function details(candidate: DuplicateCandidate): string {
    return [
        candidate.birth_date
            ? `Lahir ${formatDate(new Date(`${candidate.birth_date}T00:00:00`))}`
            : 'Tanggal lahir belum ada',
        candidate.phone_masked ?? 'Belum ada telepon',
    ].join(' · ');
}

/**
 * "Kemungkinan sudah ada": the people who look like the one being added or linked, with why, so staff can pick the
 * existing record instead of making a second one. `action` renders what to do with one candidate ("Pakai yang ini").
 */
export function DuplicateCandidates({
    title = 'Kemungkinan sudah ada',
    message,
    candidates,
    action,
    children,
}: {
    title?: string;
    message: string;
    candidates: DuplicateCandidate[];
    action: (candidate: DuplicateCandidate) => ReactNode;
    /** The confirmation that goes on anyway ("Tetap tambah"). */
    children?: ReactNode;
}) {
    return (
        <Alert variant="warning" className="grid-cols-[1rem_1fr] gap-y-3">
            <TriangleAlert />
            <AlertTitle className="line-clamp-none">{title}</AlertTitle>
            <AlertDescription className="col-start-2 grid gap-3">
                <p>{message}</p>
                <ul className="grid gap-2">
                    {candidates.map((candidate) => (
                        <li
                            key={candidate.id}
                            className="flex flex-wrap items-center gap-3 rounded-xl border border-amber-200 bg-background p-3 dark:border-amber-400/30"
                        >
                            <PersonAvatar name={candidate.name} size="sm" />
                            <span className="grid min-w-0 flex-1 leading-tight">
                                <span className="font-semibold">
                                    {candidate.name}
                                </span>
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    {details(candidate)}
                                </span>
                                <span className="mt-1 flex flex-wrap gap-1.5">
                                    {candidate.reasons.map((reason) => (
                                        <Badge key={reason} variant="warning">
                                            {reason}
                                        </Badge>
                                    ))}
                                    <Badge variant="muted">
                                        {candidate.has_nik
                                            ? 'NIK ada'
                                            : 'NIK belum ada'}
                                    </Badge>
                                    <Badge variant="muted">
                                        {candidate.has_family_card
                                            ? 'KK ada'
                                            : 'KK belum ada'}
                                    </Badge>
                                </span>
                            </span>
                            {action(candidate)}
                        </li>
                    ))}
                </ul>
                {children}
            </AlertDescription>
        </Alert>
    );
}
