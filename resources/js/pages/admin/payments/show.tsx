import { Head, router } from '@inertiajs/react';
import { Check, CircleAlert, Wallet, X } from 'lucide-react';
import { useState } from 'react';
import { DescriptionList } from '@/components/description-list';
import { PageHeader } from '@/components/page-header';
import { PaymentProof } from '@/components/payments/payment-proof';
import { PaymentRejectForm } from '@/components/payments/payment-reject-form';
import { SectionCard } from '@/components/section-card';
import { StatusBadge } from '@/components/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index, reject, verify } from '@/routes/admin/payments';
import type { PaymentAllocationRow, PaymentDetail } from '@/types/billing';

export default function PaymentShow({
    payment,
    allocations,
    can,
}: {
    payment: PaymentDetail;
    allocations: PaymentAllocationRow[];
    can: { verify: boolean };
}) {
    const [amounts, setAmounts] = useState<Record<number, string>>(
        Object.fromEntries(
            allocations.map((row) => [row.bill_item_id, String(row.amount)]),
        ),
    );
    const [rejecting, setRejecting] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const total = Object.values(amounts).reduce(
        (sum, value) => sum + (Number(value) || 0),
        0,
    );
    const isChanged = allocations.some(
        (row) => Number(amounts[row.bill_item_id]) !== row.amount,
    );
    const matches = total === payment.amount;

    function submitVerify() {
        router.put(
            verify(payment.id).url,
            isChanged
                ? {
                      allocations: allocations.map((row) => ({
                          bill_item_id: row.bill_item_id,
                          amount: Number(amounts[row.bill_item_id]) || 0,
                      })),
                  }
                : {},
            {
                preserveScroll: true,
                onStart: () => {
                    setProcessing(true);
                    setErrors({});
                },
                onFinish: () => setProcessing(false),
                onError: setErrors,
            },
        );
    }

    function submitReject(reason: string) {
        router.put(
            reject(payment.id).url,
            { reason },
            {
                preserveScroll: true,
                onStart: () => {
                    setProcessing(true);
                    setErrors({});
                },
                onFinish: () => setProcessing(false),
                onError: setErrors,
                onSuccess: () => setRejecting(false),
            },
        );
    }

    const problem = errors.allocations ?? errors.payment;

    return (
        <>
            <Head title={`Pembayaran ${formatRupiah(payment.amount)}`} />
            <div className="grid gap-6">
                <PageHeader
                    icon={Wallet}
                    title={formatRupiah(payment.amount)}
                    description={`Dari ${payment.payer} · ${formatDate(payment.paid_on)}`}
                    meta={
                        <div className="pt-1">
                            <StatusBadge status={payment.status} />
                        </div>
                    }
                />

                <div className="grid items-start gap-6 @5xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <div className="grid min-w-0 gap-6">
                        {payment.rejection_reason && (
                            <Alert variant="destructive">
                                <X />
                                <AlertTitle>Ditolak</AlertTitle>
                                <AlertDescription>
                                    {payment.rejection_reason}
                                </AlertDescription>
                            </Alert>
                        )}

                        <SectionCard
                            title="Untuk membayar apa"
                            description={
                                can.verify
                                    ? 'Kamu bisa membagi ulang nominalnya. Totalnya harus tetap sama dengan pembayaran.'
                                    : undefined
                            }
                        >
                            <div className="grid gap-4">
                                <ul className="grid divide-y divide-line">
                                    {allocations.map((row, position) => {
                                        const error =
                                            errors[
                                                `allocations.${position}.amount`
                                            ];

                                        return (
                                            <li
                                                key={row.bill_item_id}
                                                className="grid gap-2 py-3 first:pt-0 last:pb-0 sm:grid-cols-[1fr_12rem] sm:items-start"
                                            >
                                                <div className="grid gap-0.5 text-sm">
                                                    <span className="font-semibold">
                                                        {row.item}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground tabular-nums">
                                                        {row.child} · Tagihan{' '}
                                                        {row.bill} · Rincian{' '}
                                                        {formatRupiah(
                                                            row.item_amount,
                                                        )}
                                                        {row.room !== null &&
                                                            ` · bisa menerima ${formatRupiah(row.room)}`}
                                                    </span>
                                                </div>
                                                {can.verify ? (
                                                    <div className="grid gap-1">
                                                        <Input
                                                            type="number"
                                                            inputMode="numeric"
                                                            min={0}
                                                            aria-label={`Alokasi ${row.item}`}
                                                            value={
                                                                amounts[
                                                                    row
                                                                        .bill_item_id
                                                                ]
                                                            }
                                                            aria-invalid={Boolean(
                                                                error,
                                                            )}
                                                            className="tabular-nums"
                                                            onChange={(event) =>
                                                                setAmounts({
                                                                    ...amounts,
                                                                    [row.bill_item_id]:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                })
                                                            }
                                                        />
                                                        {error && (
                                                            <p className="text-sm font-medium text-destructive-foreground">
                                                                {error}
                                                            </p>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <p className="font-semibold tabular-nums sm:text-right">
                                                        {formatRupiah(
                                                            row.amount,
                                                        )}
                                                    </p>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ul>

                                {can.verify && (
                                    <p
                                        role="status"
                                        className={cn(
                                            'border-t border-line pt-3 text-right text-sm font-semibold tabular-nums',
                                            matches
                                                ? 'text-success'
                                                : 'text-destructive-foreground',
                                        )}
                                    >
                                        Total {formatRupiah(total)}
                                        {matches
                                            ? ' · sama dengan pembayaran'
                                            : ` · harus ${formatRupiah(payment.amount)}`}
                                    </p>
                                )}
                            </div>
                        </SectionCard>

                        <SectionCard title="Detail">
                            <DescriptionList
                                items={[
                                    { label: 'Pembayar', value: payment.payer },
                                    {
                                        label: 'Cara bayar',
                                        value: payment.method,
                                    },
                                    {
                                        label: 'Tanggal bayar',
                                        value: formatDate(payment.paid_on),
                                    },
                                    {
                                        label: 'Dikirim',
                                        value: payment.created_at,
                                    },
                                    {
                                        label: 'Dicatat oleh',
                                        value: payment.created_by,
                                    },
                                    {
                                        label: 'Diperiksa oleh',
                                        value: payment.verified_by
                                            ? `${payment.verified_by} · ${payment.verified_at}`
                                            : null,
                                    },
                                    {
                                        label: 'Catatan',
                                        value: payment.note,
                                        wide: true,
                                    },
                                ]}
                            />
                        </SectionCard>
                    </div>

                    <div className="grid min-w-0 gap-6 @5xl:sticky @5xl:top-20">
                        {can.verify && (
                            <SectionCard
                                title="Periksa pembayaran"
                                description="Cocokkan nominal dan bukti, lalu verifikasi atau tolak."
                            >
                                <div className="grid gap-4">
                                    {problem && (
                                        <Alert variant="destructive">
                                            <CircleAlert />
                                            <AlertTitle>
                                                Belum bisa diproses
                                            </AlertTitle>
                                            <AlertDescription>
                                                {problem}
                                            </AlertDescription>
                                        </Alert>
                                    )}

                                    {rejecting ? (
                                        <PaymentRejectForm
                                            error={errors.reason}
                                            processing={processing}
                                            onCancel={() => {
                                                setRejecting(false);
                                                setErrors({});
                                            }}
                                            onSubmit={submitReject}
                                        />
                                    ) : (
                                        <div className="grid gap-2">
                                            <Button
                                                size="lg"
                                                disabled={
                                                    processing || !matches
                                                }
                                                onClick={submitVerify}
                                            >
                                                {processing ? (
                                                    <Spinner />
                                                ) : (
                                                    <Check />
                                                )}
                                                Verifikasi
                                            </Button>
                                            <Button
                                                variant="outline"
                                                disabled={processing}
                                                onClick={() =>
                                                    setRejecting(true)
                                                }
                                            >
                                                <X />
                                                Tolak
                                            </Button>
                                            {!matches && (
                                                <p className="text-sm text-muted-foreground">
                                                    Verifikasi menunggu totalnya
                                                    pas dengan{' '}
                                                    {formatRupiah(
                                                        payment.amount,
                                                    )}
                                                    .
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </SectionCard>
                        )}

                        <SectionCard title="Bukti pembayaran">
                            <PaymentProof
                                paymentId={payment.id}
                                hasProof={payment.has_proof}
                                isImage={payment.proof_is_image}
                                isTransfer={payment.method_value === 'transfer'}
                            />
                        </SectionCard>
                    </div>
                </div>
            </div>
        </>
    );
}

PaymentShow.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Pembayaran', href: index() },
        { title: 'Detail', href: '#' },
    ],
};
