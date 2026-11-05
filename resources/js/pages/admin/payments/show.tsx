import { Head, router } from '@inertiajs/react';
import {
    Check,
    CircleAlert,
    Plus,
    Printer,
    RotateCcw,
    Undo2,
    Wallet,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { DescriptionList } from '@/components/description-list';
import { PageHeader } from '@/components/page-header';
import { PaymentAllocationAdder } from '@/components/payments/payment-allocation-adder';
import { PaymentCorrectionDialog } from '@/components/payments/payment-correction-dialog';
import type { CorrectionTarget } from '@/components/payments/payment-correction-dialog';
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
import {
    index,
    reject,
    reopen,
    verify,
    voidMethod,
} from '@/routes/admin/payments';
import { receipt } from '@/routes/payments';
import type {
    OpenGroup,
    PaymentAllocationRow,
    PaymentDetail,
    PaymentDetailCan,
    PaymentHistoryEntry,
} from '@/types/billing';

/** One line of the division: the submitted ones come from the server, the added ones from the family's open items. */
type DivisionRow = {
    bill_item_id: number;
    item: string;
    child: string;
    bill: string;
    item_amount: number;
    /** What the item can still take, when known. */
    room: number | null;
    isAdded: boolean;
};

export default function PaymentShow({
    payment,
    allocations,
    can,
    open_groups: openGroups,
    history,
}: {
    payment: PaymentDetail;
    allocations: PaymentAllocationRow[];
    can: PaymentDetailCan;
    open_groups: OpenGroup[];
    history: PaymentHistoryEntry[];
}) {
    const [amounts, setAmounts] = useState<Record<number, string>>(
        Object.fromEntries(
            allocations.map((row) => [row.bill_item_id, String(row.amount)]),
        ),
    );
    const [addedIds, setAddedIds] = useState<number[]>([]);
    const [adding, setAdding] = useState(false);
    const [rejecting, setRejecting] = useState(false);
    const [correction, setCorrection] = useState<'void' | 'reopen' | null>(
        null,
    );
    const [correctionKey, setCorrectionKey] = useState(0);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const submitted: DivisionRow[] = allocations.map((row) => ({
        bill_item_id: row.bill_item_id,
        item: row.item,
        child: row.child,
        bill: row.bill,
        item_amount: row.item_amount,
        room: row.room,
        isAdded: false,
    }));
    const added: DivisionRow[] = addedIds.flatMap((id) => {
        for (const group of openGroups) {
            const item = group.items.find((candidate) => candidate.id === id);

            if (item) {
                return [
                    {
                        bill_item_id: item.id,
                        item: item.name,
                        child: group.registrant.child_name,
                        bill: group.type_label,
                        item_amount: item.amount,
                        room: item.outstanding,
                        isAdded: true,
                    },
                ];
            }
        }

        return [];
    });
    // The order of this list is the order the server reads the division in, so its errors point at these positions.
    const division = [...submitted, ...added];

    const amountOf = (row: DivisionRow) =>
        Number(amounts[row.bill_item_id]) || 0;
    const total = division.reduce((sum, row) => sum + amountOf(row), 0);
    const isChanged =
        added.length > 0 ||
        allocations.some(
            (row) => Number(amounts[row.bill_item_id]) !== row.amount,
        );
    const matches = total === payment.amount;
    const isOverRoom = division.some(
        (row) => row.room !== null && amountOf(row) > row.room,
    );
    const canAddMore = openGroups.length > 0;

    function addItem(itemId: number) {
        const item = openGroups
            .flatMap((group) => group.items)
            .find((candidate) => candidate.id === itemId);

        if (!item) {
            return;
        }

        const leftToPlace = Math.max(payment.amount - total, 0);

        setAddedIds([...addedIds, itemId]);
        setAmounts({
            ...amounts,
            [itemId]: String(Math.min(item.outstanding, leftToPlace)),
        });
        setAdding(false);
    }

    function removeAdded(itemId: number) {
        setAddedIds(addedIds.filter((id) => id !== itemId));
        setAmounts(
            Object.fromEntries(
                Object.entries(amounts).filter(
                    ([key]) => Number(key) !== itemId,
                ),
            ),
        );
    }

    function visit(
        url: string,
        data: Parameters<typeof router.put>[1],
        onSuccess?: () => void,
    ) {
        router.put(url, data, {
            preserveScroll: true,
            onStart: () => {
                setProcessing(true);
                setErrors({});
            },
            onFinish: () => setProcessing(false),
            onError: setErrors,
            onSuccess,
        });
    }

    function submitVerify() {
        visit(
            verify(payment.id).url,
            isChanged
                ? {
                      allocations: division.map((row) => ({
                          bill_item_id: row.bill_item_id,
                          amount: amountOf(row),
                      })),
                  }
                : {},
        );
    }

    function submitReject(reason: string) {
        visit(reject(payment.id).url, { reason }, () => setRejecting(false));
    }

    function submitCorrection(values: {
        reason: string;
        to: CorrectionTarget;
    }) {
        const isVoid = correction === 'void';

        visit(
            isVoid ? voidMethod(payment.id).url : reopen(payment.id).url,
            isVoid ? values : { reason: values.reason },
            () => {
                setCorrection(null);
                setCorrectionKey((key) => key + 1);
            },
        );
    }

    const problem = errors.allocations ?? errors.payment;
    const correctionProblem = correction !== null ? errors.payment : undefined;

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
                    actions={
                        can.receipt && (
                            <Button variant="outline" asChild>
                                <a
                                    href={receipt(payment.id).url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Printer />
                                    Cetak kwitansi
                                </a>
                            </Button>
                        )
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
                                    ? 'Kamu bisa membagi ulang nominalnya, juga ke rincian lain milik keluarga ini. Totalnya harus tetap sama dengan pembayaran.'
                                    : undefined
                            }
                        >
                            <div className="grid gap-4">
                                <ul className="grid divide-y divide-line">
                                    {division.map((row, position) => {
                                        const error =
                                            errors[
                                                `allocations.${position}.amount`
                                            ] ??
                                            errors[
                                                `allocations.${position}.bill_item_id`
                                            ];
                                        const over =
                                            row.room !== null &&
                                            amountOf(row) > row.room;

                                        return (
                                            <li
                                                key={row.bill_item_id}
                                                className="grid gap-2 py-3 first:pt-0 last:pb-0 sm:grid-cols-[1fr_12rem] sm:items-start"
                                            >
                                                <div className="grid gap-0.5 text-sm">
                                                    <span className="font-semibold">
                                                        {row.item}
                                                        {row.isAdded && (
                                                            <span className="ml-2 text-xs font-medium text-muted-foreground">
                                                                ditambahkan
                                                            </span>
                                                        )}
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
                                                        <div className="flex items-center gap-2">
                                                            <Input
                                                                type="number"
                                                                inputMode="numeric"
                                                                min={0}
                                                                max={
                                                                    row.room ??
                                                                    undefined
                                                                }
                                                                aria-label={`Alokasi ${row.item} ${row.child}`}
                                                                value={
                                                                    amounts[
                                                                        row
                                                                            .bill_item_id
                                                                    ] ?? ''
                                                                }
                                                                aria-invalid={Boolean(
                                                                    error ||
                                                                    over,
                                                                )}
                                                                className="tabular-nums"
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setAmounts({
                                                                        ...amounts,
                                                                        [row.bill_item_id]:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                    })
                                                                }
                                                            />
                                                            {row.isAdded && (
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    aria-label={`Hapus ${row.item} ${row.child}`}
                                                                    onClick={() =>
                                                                        removeAdded(
                                                                            row.bill_item_id,
                                                                        )
                                                                    }
                                                                >
                                                                    <X />
                                                                </Button>
                                                            )}
                                                        </div>
                                                        {(error || over) && (
                                                            <p className="text-sm font-medium text-destructive-foreground">
                                                                {error ??
                                                                    `Melebihi sisa (${formatRupiah(row.room ?? 0)})`}
                                                            </p>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <p className="font-semibold tabular-nums sm:text-right">
                                                        {formatRupiah(
                                                            amountOf(row),
                                                        )}
                                                    </p>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ul>

                                {can.verify && canAddMore && (
                                    <div className="grid gap-3 border-t border-line pt-3">
                                        {adding ? (
                                            <>
                                                <PaymentAllocationAdder
                                                    groups={openGroups}
                                                    used={
                                                        new Set(
                                                            division.map(
                                                                (row) =>
                                                                    row.bill_item_id,
                                                            ),
                                                        )
                                                    }
                                                    onAdd={addItem}
                                                />
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="justify-self-start"
                                                    onClick={() =>
                                                        setAdding(false)
                                                    }
                                                >
                                                    Tutup daftar
                                                </Button>
                                            </>
                                        ) : (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="justify-self-start"
                                                onClick={() => setAdding(true)}
                                            >
                                                <Plus />
                                                Tambah rincian lain
                                            </Button>
                                        )}
                                    </div>
                                )}

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

                        {history.length > 0 && (
                            <SectionCard
                                title="Jejak perubahan"
                                description="Dicatat otomatis setiap kali pembayaran dibatalkan atau dibuka ulang."
                            >
                                <ul className="grid divide-y divide-line">
                                    {history.map((entry) => (
                                        <li
                                            key={entry.id}
                                            className="grid gap-1 py-3 text-sm first:pt-0 last:pb-0"
                                        >
                                            <span className="text-xs font-medium text-muted-foreground">
                                                {entry.created_at}
                                            </span>
                                            <span>{entry.body}</span>
                                        </li>
                                    ))}
                                </ul>
                            </SectionCard>
                        )}
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
                                                    processing ||
                                                    !matches ||
                                                    isOverRoom
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
                                            {matches && isOverRoom && (
                                                <p className="text-sm text-muted-foreground">
                                                    Ada rincian yang diberi
                                                    lebih dari sisanya.
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </SectionCard>
                        )}

                        {(can.void || can.reopen) && (
                            <SectionCard
                                title="Koreksi"
                                description={
                                    can.void
                                        ? 'Salah verifikasi? Batalkan supaya tidak lagi dihitung ke tagihan.'
                                        : 'Ditolak karena keliru? Buka ulang supaya bisa diverifikasi.'
                                }
                            >
                                <div className="grid gap-2">
                                    {!can.verify && problem && (
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
                                    {can.void && (
                                        <Button
                                            variant="outline"
                                            onClick={() => {
                                                setErrors({});
                                                setCorrection('void');
                                            }}
                                        >
                                            <Undo2 />
                                            Batalkan pembayaran
                                        </Button>
                                    )}
                                    {can.reopen && (
                                        <Button
                                            variant="outline"
                                            onClick={() => {
                                                setErrors({});
                                                setCorrection('reopen');
                                            }}
                                        >
                                            <RotateCcw />
                                            Buka ulang
                                        </Button>
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

            <PaymentCorrectionDialog
                key={correctionKey}
                mode={correction ?? 'void'}
                open={correction !== null}
                onOpenChange={(open) => !open && setCorrection(null)}
                processing={processing}
                error={errors.reason}
                targetError={errors.to ?? correctionProblem}
                onSubmit={submitCorrection}
            />
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
