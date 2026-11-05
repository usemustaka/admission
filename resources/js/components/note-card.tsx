import { Globe, Lock, Users } from 'lucide-react';
import type { ReactNode } from 'react';
import { PersonAvatar } from '@/components/person-avatar';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type NoteUrgency = 'low' | 'normal' | 'high' | 'critical';
export type NoteVisibility = 'public' | 'private';

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
    public: { label: 'Semua panitia', icon: Globe },
    private: { label: 'Hanya saya', icon: Lock },
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
    sharedWith = [],
    sharedWithFamily = false,
    actions,
    className,
}: {
    authorName: string;
    authorPhoto?: string | null;
    createdAt: string;
    body: string;
    urgency: NoteUrgency;
    visibility: NoteVisibility;
    /** Names of the people or roles this note is shared with, besides everyone. */
    sharedWith?: string[];
    /** Whether the parents of the child can read this note. */
    sharedWithFamily?: boolean;
    actions?: ReactNode;
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

            <footer className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                    <span className="inline-flex items-center gap-1.5">
                        <VisibilityIcon className="size-3.5" />
                        {visibilityMeta[visibility].label}
                    </span>
                    {sharedWith.length > 0 && (
                        <span className="inline-flex items-center gap-1.5">
                            <Users className="size-3.5" />
                            Dibagikan ke {sharedWith.join(', ')}
                        </span>
                    )}
                    {sharedWithFamily && (
                        <Badge variant="success">Terlihat oleh keluarga</Badge>
                    )}
                </div>
                {actions && <div className="flex gap-1">{actions}</div>}
            </footer>
        </article>
    );
}
