import { Badge } from '@/components/ui/badge';
import { StatusBadge } from '@/components/status-badge';
import { formatDate, formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';

export type BillKind = 'registration' | 'settlement';

const kindLabel: Record<BillKind, string> = {
    registration: 'Pendaftaran',
    settlement: 'Pelunasan',
};

export function BillItem({
    title,
    kind,
    amount,
    paidAmount = 0,
    dueDate,
    studentName,
    className,
}: {
    title: string;
    kind: BillKind;
    amount: number;
    paidAmount?: number;
    dueDate?: string;
    /** Shown when bills of several children are listed together. */
    studentName?: string;
    className?: string;
}) {
    const remaining = Math.max(amount - paidAmount, 0);
    const status =
        paidAmount <= 0 ? 'unpaid' : remaining === 0 ? 'paid' : 'partial';

    return (
        <div
            className={cn(
                'flex flex-col gap-3 rounded-2xl border bg-card p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between',
                className,
            )}
        >
            <div className="grid gap-1.5">
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">{kindLabel[kind]}</Badge>
                    <StatusBadge status={status} />
                </div>
                <p className="font-semibold">{title}</p>
                <p className="text-sm text-muted-foreground">
                    {[
                        studentName,
                        dueDate && `Bayar sebelum ${formatDate(dueDate)}`,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
            </div>

            <div className="grid gap-0.5 sm:text-right">
                <p className="text-xl font-bold tracking-tight tabular-nums">
                    {formatRupiah(status === 'paid' ? amount : remaining)}
                </p>
                {status === 'partial' && (
                    <p className="text-xs text-muted-foreground tabular-nums">
                        dari {formatRupiah(amount)}
                    </p>
                )}
            </div>
        </div>
    );
}
