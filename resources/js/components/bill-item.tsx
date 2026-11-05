import { StatusBadge } from '@/components/status-badge';
import { formatDate, formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';

export type BillKind = 'registration' | 'settlement';

const kindLabel: Record<BillKind, string> = {
    registration: 'Pendaftaran',
    settlement: 'Pelunasan',
};

/**
 * One bill as a row: what it is for, its status, and what is left to pay.
 */
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
                'grid gap-3 rounded-2xl border border-line bg-card p-4',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="grid min-w-0 gap-1">
                    <p className="text-sm font-semibold text-muted-foreground">
                        {kindLabel[kind]}
                    </p>
                    <p className="text-base font-semibold">{title}</p>
                    <p className="text-[15px] text-muted-foreground">
                        {[
                            studentName,
                            dueDate && `Bayar sebelum ${formatDate(dueDate)}`,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                </div>
                <StatusBadge status={status} />
            </div>

            <div className="grid gap-0.5">
                <p className="text-2xl font-bold tracking-tight tabular-nums">
                    {formatRupiah(status === 'paid' ? amount : remaining)}
                </p>
                {status === 'partial' && (
                    <p className="text-sm text-muted-foreground tabular-nums">
                        sisa dari {formatRupiah(amount)}
                    </p>
                )}
            </div>
        </div>
    );
}
