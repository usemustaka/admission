import { Head, router } from '@inertiajs/react';
import { Check, Wallet, X } from 'lucide-react';
import { useState } from 'react';
import { DescriptionList } from '@/components/description-list';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { StatusBadge } from '@/components/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, formatRupiah } from '@/lib/format';
import { dashboard } from '@/routes';
import { index, reject, verify } from '@/routes/admin/payments';
import { proof } from '@/routes/payments';
import type { PaymentData } from '@/types/billing';

type Allocation = {
    bill_item_id: number;
    item: string;
    bill: string;
    child: string;
    item_amount: number;
    amount: number;
    room: number | null;
};

type PaymentDetail = PaymentData & {
    note: string | null;
    proof_is_image: boolean;
    created_by: string | null;
    verified_by: string | null;
    verified_at: string | null;
};

export default function PaymentShow({
    payment,
    allocations,
    can,
}: {
    payment: PaymentDetail;
    allocations: Allocation[];
    can: { verify: boolean };
}) {
    const [amounts, setAmounts] = useState<Record<number, string>>(
        Object.fromEntries(
            allocations.map((row) => [row.bill_item_id, String(row.amount)]),
        ),
    );
    const [rejecting, setRejecting] = useState(false);
    const [reason, setReason] = useState('');
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
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: setErrors,
            },
        );
    }

    function submitReject() {
        router.put(
            reject(payment.id).url,
            { reason },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: setErrors,
                onSuccess: () => setRejecting(false),
            },
        );
    }

    return (
        <>
            <Head title={`Pembayaran ${formatRupiah(payment.amount)}`} />
            <div className="mx-auto grid w-full max-w-4xl gap-6 p-4 md:p-6">
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

                {payment.rejection_reason && (
                    <Alert variant="destructive">
                        <X />
                        <AlertTitle>Ditolak</AlertTitle>
                        <AlertDescription>
                            {payment.rejection_reason}
                        </AlertDescription>
                    </Alert>
                )}

                <SectionCard title="Detail">
                    <DescriptionList
                        items={[
                            { label: 'Pembayar', value: payment.payer },
                            { label: 'Cara bayar', value: payment.method },
                            {
                                label: 'Tanggal bayar',
                                value: formatDate(payment.paid_on),
                            },
                            { label: 'Dikirim', value: payment.created_at },
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

                {payment.has_proof && (
                    <SectionCard title="Bukti pembayaran">
                        {payment.proof_is_image ? (
                            <a
                                href={proof(payment.id).url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <img
                                    src={proof(payment.id).url}
                                    alt="Bukti pembayaran"
                                    className="max-h-96 rounded-xl border object-contain"
                                />
                            </a>
                        ) : (
                            <Button variant="outline" asChild>
                                <a
                                    href={proof(payment.id).url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Buka bukti (PDF)
                                </a>
                            </Button>
                        )}
                    </SectionCard>
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
                        <ul className="grid divide-y rounded-xl border">
                            {allocations.map((row, position) => {
                                const error =
                                    errors[`allocations.${position}.amount`];

                                return (
                                    <li
                                        key={row.bill_item_id}
                                        className="grid gap-2 px-4 py-3 sm:grid-cols-[1fr_12rem] sm:items-start"
                                    >
                                        <div className="grid gap-0.5 text-sm">
                                            <span className="font-semibold">
                                                {row.item}
                                            </span>
                                            <span className="text-xs text-muted-foreground tabular-nums">
                                                {row.child} · Tagihan {row.bill}{' '}
                                                · Rincian{' '}
                                                {formatRupiah(row.item_amount)}
                                                {row.room !== null &&
                                                    ` · bisa menerima ${formatRupiah(row.room)}`}
                                            </span>
                                        </div>
                                        {can.verify ? (
                                            <div className="grid gap-1">
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    aria-label={`Alokasi ${row.item}`}
                                                    value={
                                                        amounts[
                                                            row.bill_item_id
                                                        ]
                                                    }
                                                    aria-invalid={Boolean(
                                                        error,
                                                    )}
                                                    onChange={(e) =>
                                                        setAmounts({
                                                            ...amounts,
                                                            [row.bill_item_id]:
                                                                e.target.value,
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
                                                {formatRupiah(row.amount)}
                                            </p>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>

                        {can.verify && (
                            <p
                                className={
                                    matches
                                        ? 'text-right text-sm font-semibold text-success'
                                        : 'text-right text-sm font-semibold text-destructive-foreground'
                                }
                            >
                                Total {formatRupiah(total)}
                                {matches
                                    ? ' · sama dengan pembayaran'
                                    : ` · harus ${formatRupiah(payment.amount)}`}
                            </p>
                        )}
                        {(errors.allocations || errors.payment) && (
                            <p className="text-sm font-medium text-destructive-foreground">
                                {errors.allocations ?? errors.payment}
                            </p>
                        )}
                    </div>
                </SectionCard>

                {can.verify && (
                    <div className="flex flex-wrap justify-end gap-3">
                        <Button
                            variant="outline"
                            onClick={() => setRejecting(true)}
                        >
                            <X />
                            Tolak
                        </Button>
                        <Button
                            disabled={processing || !matches}
                            onClick={submitVerify}
                        >
                            {processing ? <Spinner /> : <Check />}
                            Verifikasi
                        </Button>
                    </div>
                )}
            </div>

            <Dialog open={rejecting} onOpenChange={setRejecting}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tolak pembayaran ini?</DialogTitle>
                        <DialogDescription>
                            Orang tua akan melihat alasannya dan bisa mengirim
                            ulang.
                        </DialogDescription>
                    </DialogHeader>
                    <FormField label="Alasan" required error={errors.reason}>
                        {(control) => (
                            <Textarea
                                placeholder="Contoh: bukti transfer tidak terbaca"
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                                {...control}
                            />
                        )}
                    </FormField>
                    <DialogFooter className="gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setRejecting(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={processing || reason.trim() === ''}
                            onClick={submitReject}
                        >
                            {processing && <Spinner />}
                            Ya, tolak
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

PaymentShow.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Pembayaran', href: index() },
    ],
};
