import { Link } from '@inertiajs/react';
import { FileText, Printer, Receipt, Wallet } from 'lucide-react';
import { SectionCard } from '@/components/section-card';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate, formatRupiah } from '@/lib/format';
import { show as showPayment } from '@/routes/admin/payments';
import { proof, receipt } from '@/routes/payments';
import type { BillData } from '@/types/billing';

export type RegistrantPayment = {
    id: number;
    amount: number;
    paid_on: string;
    method: string;
    method_value: string;
    status: string;
    status_label: string;
    rejection_reason: string | null;
    has_proof: boolean;
    note: string | null;
    for_child: number;
    items: string[];
    receipt: boolean;
};

const statusVariant: Record<string, 'success' | 'warning' | 'danger'> = {
    verified: 'success',
    pending: 'warning',
    rejected: 'danger',
};

/**
 * Every bill of the child with how much of each item is paid, and under it the payments that paid for it: when, how,
 * how much went to this child, and whether the committee has counted it.
 */
export function PaymentsCard({
    bills,
    payments,
    canViewPayments,
    settlementReceiptHref,
}: {
    bills: BillData[];
    payments: RegistrantPayment[];
    canViewPayments: boolean;
    settlementReceiptHref: string | null;
}) {
    return (
        <SectionCard
            title="Tagihan"
            icon={Wallet}
            compact
            contentClassName="grid divide-y divide-line [&>*]:py-4 [&>*:first-child]:pt-0 [&>*:last-child]:pb-0"
            actions={
                settlementReceiptHref && (
                    <Button variant="outline" size="sm" asChild>
                        <a
                            href={settlementReceiptHref}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <Printer />
                            Kwitansi lunas
                        </a>
                    </Button>
                )
            }
        >
            {bills.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Belum ada tagihan terbit.
                </p>
            ) : (
                bills.map((bill) => (
                    <section key={bill.id} className="grid gap-2.5">
                        <div className="flex items-center justify-between gap-3">
                            <h3 className="text-sm font-semibold">
                                Tagihan {bill.type_label}
                            </h3>
                            <StatusBadge status={bill.status} />
                        </div>
                        <ul className="grid gap-1 text-sm">
                            {bill.items.map((item) => (
                                <li
                                    key={item.id}
                                    className="flex items-baseline justify-between gap-3"
                                >
                                    <span>{item.name}</span>
                                    <span className="text-muted-foreground tabular-nums">
                                        {formatRupiah(item.paid)} /{' '}
                                        {formatRupiah(item.amount)}
                                    </span>
                                </li>
                            ))}
                            <li className="flex items-baseline justify-between gap-3 border-t border-line pt-1 font-semibold">
                                <span>Terbayar</span>
                                <span className="tabular-nums">
                                    {formatRupiah(bill.paid)} /{' '}
                                    {formatRupiah(bill.total)}
                                </span>
                            </li>
                            {bill.pending > 0 && (
                                <li className="flex justify-between gap-3 text-amber-700 dark:text-amber-300">
                                    <span>Menunggu verifikasi</span>
                                    <span className="tabular-nums">
                                        {formatRupiah(bill.pending)}
                                    </span>
                                </li>
                            )}
                        </ul>
                    </section>
                ))
            )}

            {canViewPayments && payments.length > 0 && (
                <section className="grid gap-3">
                    <h3 className="text-sm font-semibold">
                        Riwayat pembayaran
                    </h3>
                    <ul className="grid divide-y divide-line">
                        {payments.map((payment) => (
                            <li
                                key={payment.id}
                                className="grid gap-1 py-3 text-sm first:pt-0 last:pb-0"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <span className="flex flex-wrap items-center gap-2">
                                        <span className="font-semibold tabular-nums">
                                            {formatRupiah(payment.for_child)}
                                        </span>
                                        <Badge
                                            variant={
                                                statusVariant[payment.status] ??
                                                'muted'
                                            }
                                        >
                                            {payment.status_label}
                                        </Badge>
                                    </span>
                                    <span className="flex items-center gap-1">
                                        {payment.has_proof && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                asChild
                                            >
                                                <a
                                                    href={proof(payment.id).url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    aria-label="Lihat bukti bayar"
                                                    title="Bukti bayar"
                                                >
                                                    <FileText />
                                                </a>
                                            </Button>
                                        )}
                                        {payment.receipt && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                asChild
                                            >
                                                <a
                                                    href={
                                                        receipt(payment.id).url
                                                    }
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    aria-label="Cetak kwitansi"
                                                    title="Kwitansi"
                                                >
                                                    <Receipt />
                                                </a>
                                            </Button>
                                        )}
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={showPayment(payment.id)}
                                            >
                                                Detail
                                            </Link>
                                        </Button>
                                    </span>
                                </div>
                                <p className="text-muted-foreground">
                                    {formatDate(payment.paid_on)} ·{' '}
                                    {payment.method} ·{' '}
                                    {payment.items.join(', ')}
                                    {payment.for_child !== payment.amount &&
                                        ` · dari transfer ${formatRupiah(payment.amount)}`}
                                </p>
                                {payment.rejection_reason && (
                                    <p className="text-red-700 dark:text-red-300">
                                        Ditolak: {payment.rejection_reason}
                                    </p>
                                )}
                                {payment.note && (
                                    <p className="text-muted-foreground italic">
                                        {payment.note}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </SectionCard>
    );
}
