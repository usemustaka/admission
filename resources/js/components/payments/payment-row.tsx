import type { ReactNode } from 'react';
import { StatusBadge } from '@/components/status-badge';
import { formatDate, formatRupiah } from '@/lib/format';
import type { PaymentData } from '@/types/billing';

/**
 * One payment as a bordered row, shared by the parent portal and the registrant page. It uses no viewport
 * breakpoints because the portal shows it in a phone-wide column on any screen.
 */
export function PaymentRow({
    payment,
    showPayer = false,
    actions,
}: {
    payment: PaymentData;
    showPayer?: boolean;
    actions?: ReactNode;
}) {
    return (
        <div className="grid gap-2 rounded-2xl border border-line bg-card p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="grid gap-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge status={payment.status} />
                        <span className="text-sm text-muted-foreground">
                            {formatDate(payment.paid_on)} · {payment.method}
                        </span>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {[
                            showPayer ? `Dari ${payment.payer}` : null,
                            payment.for.length > 0
                                ? `Untuk ${payment.for.join(', ')}`
                                : null,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                </div>
                <div className="flex items-center gap-3">
                    <p className="text-xl font-semibold tracking-tight tabular-nums">
                        {formatRupiah(payment.amount)}
                    </p>
                    {actions}
                </div>
            </div>
            {payment.rejection_reason && (
                <p className="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300">
                    Ditolak: {payment.rejection_reason}
                </p>
            )}
        </div>
    );
}
