import { CircleAlert, Printer } from 'lucide-react';
import { SectionCard } from '@/components/section-card';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { formatDate, formatRupiah } from '@/lib/format';
import { receipt } from '@/routes/payments';
import type { PaymentData } from '@/types/billing';

/**
 * What the parent already sent, newest first, with the reason when a payment
 * was turned down.
 */
export function PaymentHistory({ payments }: { payments: PaymentData[] }) {
    return (
        <SectionCard
            title="Riwayat pembayaran"
            description="Pembayaran baru dihitung setelah diverifikasi panitia."
        >
            <ul className="grid divide-y divide-line">
                {payments.map((payment) => (
                    <li
                        key={payment.id}
                        className="grid gap-1.5 py-3 first:pt-0 last:pb-0"
                    >
                        <div className="flex items-center justify-between gap-3">
                            <p className="text-lg font-bold tracking-tight tabular-nums">
                                {formatRupiah(payment.amount)}
                            </p>
                            <StatusBadge status={payment.status} />
                        </div>
                        <p className="text-[15px] text-muted-foreground">
                            {formatDate(payment.paid_on)} · {payment.method}
                            {payment.for.length > 0 &&
                                ` · Untuk ${payment.for.join(', ')}`}
                        </p>
                        {payment.status === 'verified' && (
                            <Button
                                variant="outline"
                                size="sm"
                                className="justify-self-start"
                                asChild
                            >
                                <a
                                    href={receipt(payment.id).url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Printer />
                                    Kwitansi
                                </a>
                            </Button>
                        )}
                        {payment.rejection_reason && (
                            <p className="flex items-start gap-2 text-[15px] text-destructive-foreground">
                                <CircleAlert className="mt-0.5 size-4 shrink-0" />
                                <span className="grid gap-0.5">
                                    <span>
                                        Ditolak: {payment.rejection_reason}
                                    </span>
                                    <span>
                                        Kirim konfirmasi lagi dengan bukti yang
                                        benar.
                                    </span>
                                </span>
                            </p>
                        )}
                    </li>
                ))}
            </ul>
        </SectionCard>
    );
}
