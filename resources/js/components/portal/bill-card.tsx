import { CircleCheck, Circle, Clock } from 'lucide-react';
import { StatusBadge } from '@/components/status-badge';
import { Progress } from '@/components/ui/progress';
import { formatRupiah } from '@/lib/format';
import type { BillData, BillItemData } from '@/types/billing';

/**
 * Where one bill item stands, in words and an icon, never colour alone.
 */
function ItemState({ item }: { item: BillItemData }) {
    if (item.paid >= item.amount) {
        return (
            <span className="inline-flex items-center gap-1.5 text-sm font-medium text-success">
                <CircleCheck className="size-4" />
                Lunas
            </span>
        );
    }

    const parts: string[] = [];

    if (item.paid > 0) {
        parts.push(`Terbayar ${formatRupiah(item.paid)}`);
    }

    if (item.pending > 0) {
        parts.push(`${formatRupiah(item.pending)} menunggu verifikasi`);
    }

    if (parts.length === 0) {
        return (
            <span className="inline-flex items-center gap-1.5 text-sm text-muted-foreground">
                <Circle className="size-4" />
                Belum dibayar
            </span>
        );
    }

    return (
        <span className="inline-flex items-start gap-1.5 text-sm text-muted-foreground">
            <Clock className="mt-0.5 size-4 shrink-0" />
            {parts.join(' · ')}
        </span>
    );
}

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
        <article className="grid gap-3 rounded-2xl border border-line bg-card p-4">
            <header className="grid gap-3">
                <div className="flex items-start justify-between gap-3">
                    <div className="grid min-w-0 gap-0.5">
                        {showChild && (
                            <p className="truncate text-lg font-semibold tracking-tight">
                                {bill.registrant.child_name}
                            </p>
                        )}
                        <p
                            className={
                                showChild
                                    ? 'text-[15px] text-muted-foreground'
                                    : 'text-lg font-semibold tracking-tight'
                            }
                        >
                            Tagihan {bill.type_label}
                            {showChild && ` · ${bill.registrant.organization}`}
                        </p>
                    </div>
                    <StatusBadge status={bill.status} />
                </div>

                <div className="grid gap-2">
                    <p className="text-3xl font-bold tracking-tight tabular-nums">
                        {formatRupiah(bill.total)}
                    </p>
                    <Progress
                        value={bill.paid}
                        max={Math.max(bill.total, 1)}
                        aria-label="Jumlah yang sudah dibayar"
                    />
                    <p className="text-[15px] text-muted-foreground tabular-nums">
                        Terbayar {formatRupiah(bill.paid)}
                        {bill.pending > 0 &&
                            ` · ${formatRupiah(bill.pending)} menunggu verifikasi`}
                    </p>
                </div>
            </header>

            <ul className="grid divide-y divide-line border-t border-line">
                {bill.items.map((item) => (
                    <li
                        key={item.id}
                        className="flex items-start justify-between gap-3 py-3"
                    >
                        <div className="grid min-w-0 gap-1">
                            <span className="text-[15px] font-medium">
                                {item.name}
                            </span>
                            <ItemState item={item} />
                        </div>
                        <span className="shrink-0 text-[15px] font-semibold tabular-nums">
                            {formatRupiah(item.amount)}
                        </span>
                    </li>
                ))}
            </ul>
        </article>
    );
}
