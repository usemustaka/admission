import { ShieldAlert } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/format';
import type { TimelineEntry } from '@/types/registrant';

/**
 * The history of a registration, oldest first. Parents see the reason of a revision; the committee also sees
 * who did it and the internal note of a bypass.
 */
export function StatusTimeline({ entries }: { entries: TimelineEntry[] }) {
    if (entries.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Belum ada perubahan status.
            </p>
        );
    }

    return (
        <ol className="grid">
            {entries.map((entry, index) => (
                <li
                    key={entry.id}
                    className="relative flex gap-4 pb-6 last:pb-0"
                >
                    {index < entries.length - 1 && (
                        <span
                            className="absolute top-4 bottom-0 left-[7px] w-px bg-border"
                            aria-hidden="true"
                        />
                    )}
                    <span
                        className="relative mt-1.5 size-[15px] shrink-0 rounded-full border-2 border-primary bg-card"
                        aria-hidden="true"
                    />
                    <div className="grid gap-1">
                        <p className="flex flex-wrap items-center gap-2 text-sm font-semibold">
                            {entry.to_label}
                            {entry.is_bypass && (
                                <Badge variant="warning" className="gap-1">
                                    <ShieldAlert />
                                    Dilompati
                                </Badge>
                            )}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {formatDate(entry.at, {
                                day: 'numeric',
                                month: 'short',
                                year: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit',
                            })}
                            {entry.by && ` · ${entry.by}`}
                        </p>
                        {entry.note && (
                            <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                {entry.note}
                            </p>
                        )}
                    </div>
                </li>
            ))}
        </ol>
    );
}
