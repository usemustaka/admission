import { Head } from '@inertiajs/react';
import { Wallet } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { BankCard } from '@/components/portal/bank-card';
import { BillCard } from '@/components/portal/bill-card';
import { BillSummary } from '@/components/portal/bill-summary';
import { PaymentHistory } from '@/components/portal/payment-history';
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
            <div className="grid gap-4">
                <PageHeader
                    title={focus ? `Tagihan ${focus.child.name}` : 'Tagihan'}
                    description={
                        focus
                            ? 'Tagihan untuk anak ini saja.'
                            : 'Semua tagihan anak-anakmu ada di sini. Pilih satu anak di atas untuk melihat tagihannya saja.'
                    }
                />

                {bills.length === 0 ? (
                    <EmptyState
                        icon={Wallet}
                        title="Belum ada tagihan"
                        description="Tagihan akan muncul di sini begitu ada yang perlu dibayar."
                    />
                ) : (
                    <>
                        <BillSummary
                            bills={bills}
                            canPay={canPay}
                            payHref={payUrl.url}
                        />

                        <BankCard />
                        <section
                            aria-label="Daftar tagihan"
                            className="grid gap-3"
                        >
                            {bills.map((bill) => (
                                <BillCard
                                    key={bill.id}
                                    bill={bill}
                                    showChild={focus === null}
                                />
                            ))}
                        </section>
                    </>
                )}

                {payments.length > 0 && <PaymentHistory payments={payments} />}
            </div>
        </>
    );
}
