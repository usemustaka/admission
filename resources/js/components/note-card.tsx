import { Globe, Lock, Users } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { PersonAvatar } from '@/components/person-avatar';
import { cn } from '@/lib/utils';

export type NoteUrgency = 'low' | 'normal' | 'high' | 'critical';
export type NoteVisibility = 'public' | 'private' | 'shared';

const urgencyMeta = {
    low: { label: 'Santai', variant: 'muted', border: 'border-l-border' },
    normal: { label: 'Biasa', variant: 'info', border: 'border-l-info' },
    high: { label: 'Penting', variant: 'warning', border: 'border-l-warning' },
    critical: {
        label: 'Mendesak',
        variant: 'danger',
        border: 'border-l-destructive',
    },
} as const;

const visibilityMeta = {
    public: { label: 'Terlihat semua', icon: Globe },
    private: { label: 'Hanya saya', icon: Lock },
    shared: { label: 'Dibagikan', icon: Users },
} as const;

export function NoteUrgencyBadge({ urgency }: { urgency: NoteUrgency }) {
    const meta = urgencyMeta[urgency];

    return <Badge variant={meta.variant}>{meta.label}</Badge>;
}

export function NoteCard({
    authorName,
    authorPhoto,
    createdAt,
    body,
    urgency,
    visibility,
    sharedCount,
    className,
}: {
    authorName: string;
    authorPhoto?: string | null;
    createdAt: string;
    body: string;
    urgency: NoteUrgency;
    visibility: NoteVisibility;
    /** Number of people the note is shared with, when visibility is "shared". */
    sharedCount?: number;
    className?: string;
}) {
    const VisibilityIcon = visibilityMeta[visibility].icon;

    return (
        <article
            className={cn(
                'grid gap-3 rounded-2xl border border-l-4 bg-card p-4 shadow-xs',
                urgencyMeta[urgency].border,
                className,
            )}
        >
            <header className="flex items-center gap-3">
                <PersonAvatar name={authorName} src={authorPhoto} size="sm" />
                <div className="grid flex-1 leading-tight">
                    <span className="text-sm font-semibold">{authorName}</span>
                    <span className="text-xs text-muted-foreground">
                        {createdAt}
                    </span>
                </div>
                <NoteUrgencyBadge urgency={urgency} />
            </header>

            <p className="text-sm leading-relaxed whitespace-pre-wrap">
                {body}
            </p>

            <footer className="flex items-center gap-1.5 text-xs text-muted-foreground">
                <VisibilityIcon className="size-3.5" />
                {visibilityMeta[visibility].label}
                {visibility === 'shared' &&
                    sharedCount !== undefined &&
                    ` · ${sharedCount} orang`}
            </footer>
        </article>
    );
}
