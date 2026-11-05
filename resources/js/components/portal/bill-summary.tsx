import { Link } from '@inertiajs/react';
import { CircleCheck, Clock, Receipt } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatRupiah } from '@/lib/format';
import type { BillData } from '@/types/billing';

/**
 * The first thing a parent reads on the bills page: how much is still to be
 * paid, and the one button to pay it.
 */
export function BillSummary({
    bills,
    canPay,
    payHref,
}: {
    bills: BillData[];
    canPay: boolean;
    payHref: string;
}) {
    const toPay = bills.reduce(
        (sum, bill) =>
            sum + bill.items.reduce((left, item) => left + item.outstanding, 0),
        0,
    );
    const pending = bills.reduce((sum, bill) => sum + bill.pending, 0);

    return (
        <section
            aria-label="Ringkasan tagihan"
            className="grid gap-3 rounded-2xl border border-line bg-card p-4"
        >
            <div className="grid gap-1">
                <p className="text-[15px] font-semibold text-muted-foreground">
                    {toPay > 0 ? 'Yang perlu kamu bayar' : 'Yang perlu dibayar'}
                </p>
                <p className="text-4xl font-bold tracking-tight tabular-nums">
                    {formatRupiah(toPay)}
                </p>
            </div>

            {pending > 0 && (
                <p className="flex items-start gap-2 text-[15px] text-muted-foreground">
                    <Clock className="mt-0.5 size-4 shrink-0" />
                    <span>
                        {formatRupiah(pending)} sedang diperiksa panitia.
                        Dihitung lunas setelah diverifikasi.
                    </span>
                </p>
            )}

            {toPay === 0 && pending === 0 && (
                <p className="flex items-center gap-2 text-[15px] font-medium text-success">
                    <CircleCheck className="size-5" />
                    Semua tagihan sudah lunas.
                </p>
            )}

            {canPay && (
                <div className="grid gap-2">
                    <Button asChild size="lg" className="w-full">
                        <Link href={payHref}>
                            <Receipt />
                            Bayar tagihan
                        </Link>
                    </Button>
                    <p className="text-sm text-muted-foreground">
                        Transfer dulu, lalu kirim bukti transfernya di langkah
                        berikutnya.
                    </p>
                </div>
            )}
        </section>
    );
}
