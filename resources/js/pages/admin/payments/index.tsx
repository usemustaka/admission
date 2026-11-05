import { Head, Link } from '@inertiajs/react';
import { Plus, Wallet } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { PaymentRow } from '@/components/payments/payment-row';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { create, index, show } from '@/routes/admin/payments';
import type { PaymentData } from '@/types/billing';
import type { Option, Paginated } from '@/types/people';

export default function PaymentsIndex({
    payments,
    filters,
    counts,
    statuses,
    can,
}: {
    payments: Paginated<PaymentData>;
    filters: { status: string };
    counts: { pending: number };
    statuses: Option[];
    can: { verify: boolean };
}) {
    const tabs = [{ value: '', label: 'Semua' }, ...statuses];

    return (
        <>
            <Head title="Pembayaran" />
            <div className="mx-auto grid w-full max-w-4xl gap-6 p-4 md:p-6">
                <PageHeader
                    icon={Wallet}
                    title="Pembayaran"
                    description="Konfirmasi pembayaran dari orang tua dan pembayaran yang dicatat langsung. Hanya yang sudah diverifikasi yang dihitung."
                    actions={
                        can.verify && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus />
                                    Catat pembayaran
                                </Link>
                            </Button>
                        )
                    }
                />

                <nav
                    className="flex flex-wrap gap-2"
                    aria-label="Filter status pembayaran"
                >
                    {tabs.map((tab) => {
                        const isActive = filters.status === tab.value;

                        return (
                            <Link
                                key={tab.value}
                                href={index({
                                    query: tab.value
                                        ? { status: tab.value }
                                        : undefined,
                                })}
                                className={cn(
                                    'inline-flex items-center gap-2 rounded-full border px-4 py-1.5 text-sm font-semibold transition-colors',
                                    isActive
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'bg-card hover:border-sage-400',
                                )}
                            >
                                {tab.label}
                                {tab.value === 'pending' &&
                                    counts.pending > 0 && (
                                        <Badge variant="warning">
                                            {counts.pending}
                                        </Badge>
                                    )}
                            </Link>
                        );
                    })}
                </nav>

                {payments.data.length === 0 ? (
                    <EmptyState
                        icon={Wallet}
                        title="Tidak ada pembayaran"
                        description="Belum ada pembayaran dengan status ini."
                    />
                ) : (
                    <div className="grid gap-3">
                        {payments.data.map((payment) => (
                            <PaymentRow
                                key={payment.id}
                                payment={payment}
                                showPayer
                                actions={
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={show(payment.id)}>
                                            Buka
                                        </Link>
                                    </Button>
                                }
                            />
                        ))}
                    </div>
                )}

                {payments.last_page > 1 && (
                    <nav
                        className="flex items-center justify-between gap-4"
                        aria-label="Halaman"
                    >
                        <p className="text-sm text-muted-foreground">
                            Halaman {payments.current_page} dari{' '}
                            {payments.last_page}
                        </p>
                        <div className="flex gap-2">
                            {payments.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={payments.prev_page_url}>
                                        Sebelumnya
                                    </Link>
                                </Button>
                            )}
                            {payments.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={payments.next_page_url}>
                                        Berikutnya
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </nav>
                )}
            </div>
        </>
    );
}

PaymentsIndex.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Pembayaran', href: index() },
    ],
};
