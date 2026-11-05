import { Badge } from '@/components/ui/badge';
import { StatusBadge } from '@/components/status-badge';
import { formatRupiah } from '@/lib/format';
import type { BillData } from '@/types/billing';

/**
 * A bill with its items and what was paid for each.
 */
export function BillCard({
    bill,
    showChild,
}: {
    bill: BillData;
    showChild: boolean;
}) {
    return (
        <article className="grid gap-4 rounded-2xl border bg-card p-5 shadow-xs">
            <header className="flex flex-wrap items-start justify-between gap-3">
                <div className="grid gap-1.5">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="secondary">
                            Tagihan {bill.type_label}
                        </Badge>
                        <StatusBadge status={bill.status} />
                    </div>
                    {showChild && (
                        <p className="font-semibold">
                            {bill.registrant.child_name}
                            <span className="font-normal text-muted-foreground">
                                {' '}
                                · {bill.registrant.organization}
                            </span>
                        </p>
                    )}
                </div>
                <div className="grid gap-0.5 text-right">
                    <p className="text-2xl font-bold tracking-tight tabular-nums">
                        {formatRupiah(bill.total)}
                    </p>
                    <p className="text-xs text-muted-foreground tabular-nums">
                        Terbayar {formatRupiah(bill.paid)}
                        {bill.pending > 0 &&
                            ` · menunggu verifikasi ${formatRupiah(bill.pending)}`}
                    </p>
                </div>
            </header>

            <ul className="grid divide-y rounded-xl border">
                {bill.items.map((item) => (
                    <li
                        key={item.id}
                        className="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-sm"
                    >
                        <span className="font-medium">{item.name}</span>
                        <span className="flex items-center gap-3 tabular-nums">
                            {item.paid >= item.amount ? (
                                <Badge variant="success">Lunas</Badge>
                            ) : (
                                item.paid > 0 && (
                                    <span className="text-xs text-muted-foreground">
                                        Terbayar {formatRupiah(item.paid)}
                                    </span>
                                )
                            )}
                            <span className="font-semibold">
                                {formatRupiah(item.amount)}
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
        </article>
    );
}
