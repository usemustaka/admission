import { Head, Link } from '@inertiajs/react';
import { Receipt, Wallet } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { PaymentRow } from '@/components/payments/payment-row';
import { BillCard } from '@/components/portal/bill-card';
import { SectionCard } from '@/components/section-card';
import { Button } from '@/components/ui/button';
import { create } from '@/routes/portal/payments';
import type { BillData, PaymentData } from '@/types/billing';
import type { PortalRegistrant } from '@/types/portal';

export default function PortalBills({
    focus,
    bills,
    payments,
    canPay,
}: {
    focus: PortalRegistrant | null;
    bills: BillData[];
    payments: PaymentData[];
    canPay: boolean;
}) {
    const payUrl = create(focus ? { query: { anak: focus.id } } : undefined);

    return (
        <>
            <Head title="Tagihan" />
            <div className="mx-auto grid w-full max-w-4xl gap-6 px-4 py-8">
                <PageHeader
                    icon={Wallet}
                    title={focus ? `Tagihan ${focus.child.name}` : 'Tagihan'}
                    description={
                        focus
                            ? 'Tagihan untuk anak ini saja.'
                            : 'Semua tagihan anak-anakmu ada di sini. Pilih satu anak di atas untuk melihat tagihannya saja.'
                    }
                    actions={
                        canPay && (
                            <Button asChild>
                                <Link href={payUrl}>
                                    <Receipt />
                                    Konfirmasi pembayaran
                                </Link>
                            </Button>
                        )
                    }
                />

                {bills.length === 0 ? (
                    <EmptyState
                        icon={Wallet}
                        title="Belum ada tagihan"
                        description="Tagihan akan muncul di sini begitu ada yang perlu dibayar."
                    />
                ) : (
                    <div className="grid gap-4">
                        {bills.map((bill) => (
                            <BillCard
                                key={bill.id}
                                bill={bill}
                                showChild={focus === null}
                            />
                        ))}
                    </div>
                )}

                {payments.length > 0 && (
                    <SectionCard
                        title="Riwayat pembayaran"
                        description="Pembayaran baru dihitung setelah diverifikasi panitia."
                    >
                        <div className="grid gap-3">
                            {payments.map((payment) => (
                                <PaymentRow
                                    key={payment.id}
                                    payment={payment}
                                />
                            ))}
                        </div>
                    </SectionCard>
                )}
            </div>
        </>
    );
}
