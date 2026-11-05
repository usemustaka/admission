import {
    BadgeCheck,
    CircleCheck,
    CircleDashed,
    ClipboardList,
    Clock,
    Megaphone,
    PenLine,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type StatusTone = 'neutral' | 'info' | 'warning' | 'success' | 'danger';

type StatusMeta = {
    label: string;
    tone: StatusTone;
    icon: LucideIcon;
};

const toneVariant = {
    neutral: 'muted',
    info: 'info',
    warning: 'warning',
    success: 'success',
    danger: 'danger',
} as const;

/**
 * Display metadata for known workflow states. Unknown keys fall back to a
 * neutral badge that shows the raw key, so new states never break the UI.
 */
export const statusMeta: Record<string, StatusMeta> = {
    registered: { label: 'Baru daftar', tone: 'neutral', icon: CircleDashed },
    awaiting_payment: {
        label: 'Menunggu pembayaran',
        tone: 'warning',
        icon: Wallet,
    },
    draft: { label: 'Draf', tone: 'neutral', icon: ClipboardList },
    review: { label: 'Sedang diperiksa', tone: 'info', icon: Clock },
    revision: { label: 'Perlu diperbaiki', tone: 'warning', icon: PenLine },
    validated: { label: 'Data valid', tone: 'success', icon: CircleCheck },
    announced: { label: 'Sudah diumumkan', tone: 'info', icon: Megaphone },
    placed: { label: 'Sudah ditempatkan', tone: 'success', icon: BadgeCheck },
    unpaid: { label: 'Belum dibayar', tone: 'warning', icon: Wallet },
    partial: { label: 'Dibayar sebagian', tone: 'info', icon: Wallet },
    paid: { label: 'Lunas', tone: 'success', icon: CircleCheck },
};

export function StatusBadge({
    status,
    label,
    tone,
    className,
}: {
    status: string;
    label?: string;
    tone?: StatusTone;
    className?: string;
}) {
    const meta = statusMeta[status];
    const Icon = meta?.icon ?? CircleDashed;

    return (
        <Badge
            variant={toneVariant[tone ?? meta?.tone ?? 'neutral']}
            className={cn('gap-1.5', className)}
        >
            <Icon />
            {label ?? meta?.label ?? status}
        </Badge>
    );
}
