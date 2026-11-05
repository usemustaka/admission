import { Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    CircleAlert,
    ExternalLink,
    RotateCw,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { PaymentProof } from '@/components/payments/payment-proof';
import { PaymentRejectForm } from '@/components/payments/payment-reject-form';
import { QuickView } from '@/components/quick-view';
import { StatusBadge } from '@/components/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatRupiah } from '@/lib/format';
import { quickView, reject, show, verify } from '@/routes/admin/payments';
import type { PaymentQuickViewData } from '@/types/billing';

/** What the list asked to see. A new object every time, so opening the same payment again reloads it. */
export type QuickViewTarget = { id: number };

type Loaded = { target: QuickViewTarget; data: PaymentQuickViewData };

/**
 * Quick view of one payment from the list: proof, who paid for what, and, for pending payments, Verifikasi and
 * Tolak right there. After either, it moves on to the next payment waiting so the queue can be cleared without
 * going back to the list. `narrowing` is the search, method and date filter of the list, which keeps that queue
 * inside what the admin is looking at.
 */
export function PaymentQuickView({
    target,
    narrowing,
    onNavigate,
    onClose,
}: {
    target: QuickViewTarget | null;
    narrowing: Record<string, string>;
    onNavigate: (id: number) => void;
    onClose: () => void;
}) {
    const [loaded, setLoaded] = useState<Loaded | null>(null);
    const [failed, setFailed] = useState<QuickViewTarget | null>(null);
    const [attempt, setAttempt] = useState(0);
    const [rejecting, setRejecting] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const narrowingKey = JSON.stringify(narrowing);

    useEffect(() => {
        if (target === null) {
            return;
        }

        const controller = new AbortController();

        fetch(quickView(target.id, { query: JSON.parse(narrowingKey) }).url, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                return response.json() as Promise<PaymentQuickViewData>;
            })
            .then((data) => {
                setLoaded({ target, data });
                setRejecting(false);
                setErrors({});
            })
            .catch((error: Error) => {
                if (error.name !== 'AbortError') {
                    setFailed(target);
                }
            });

        return () => controller.abort();
    }, [target, narrowingKey, attempt]);

    const isLoading =
        target !== null && loaded?.target !== target && failed !== target;
    const hasFailed = target !== null && failed === target;
    // Keeps showing the last payment while the panel slides out.
    const data = loaded?.data ?? null;
    const payment = data?.payment ?? null;

    function advance() {
        const next = data?.queue.next_id ?? null;

        if (next !== null) {
            onNavigate(next);

            return;
        }

        onClose();
        toast.info('Antrean sudah bersih. Tidak ada lagi yang menunggu.');
    }

    function act(url: string, body: Record<string, unknown>) {
        router.put(
            url,
            { ...body, quick: 1 },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => {
                    setProcessing(true);
                    setErrors({});
                },
                onFinish: () => setProcessing(false),
                onError: (received) => setErrors(received),
                onSuccess: advance,
            },
        );
    }

    const problem = errors.payment ?? errors.allocations ?? null;
    const canAct = data?.can.verify === true && payment !== null;

    return (
        <QuickView
            open={target !== null}
            onOpenChange={(open) => !open && onClose()}
            title={
                payment && !isLoading && !hasFailed
                    ? formatRupiah(payment.amount)
                    : 'Pembayaran'
            }
            description="Bukti dan rincian pembayaran"
            footer={
                data && payment && !isLoading && !hasFailed ? (
                    <div className="grid w-full gap-3">
                        {canAct && rejecting ? (
                            <PaymentRejectForm
                                error={errors.reason}
                                processing={processing}
                                onCancel={() => {
                                    setRejecting(false);
                                    setErrors({});
                                }}
                                onSubmit={(reason) =>
                                    act(reject(payment.id).url, { reason })
                                }
                            />
                        ) : (
                            <>
                                {canAct && (
                                    <div className="grid grid-cols-2 gap-2">
                                        <Button
                                            variant="outline"
                                            disabled={processing}
                                            onClick={() => setRejecting(true)}
                                        >
                                            <X />
                                            Tolak
                                        </Button>
                                        <Button
                                            disabled={
                                                processing ||
                                                !data.can_verify_as_submitted
                                            }
                                            onClick={() =>
                                                act(verify(payment.id).url, {})
                                            }
                                        >
                                            {processing ? (
                                                <Spinner />
                                            ) : (
                                                <Check />
                                            )}
                                            Verifikasi
                                        </Button>
                                    </div>
                                )}
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link href={show(payment.id)}>
                                            <ExternalLink />
                                            Buka detail
                                        </Link>
                                    </Button>
                                    {data.queue.next_id !== null && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            disabled={processing}
                                            onClick={() =>
                                                onNavigate(data.queue.next_id!)
                                            }
                                        >
                                            Berikutnya
                                            <span className="font-medium text-muted-foreground tabular-nums">
                                                ({data.queue.left} menunggu)
                                            </span>
                                            <ArrowRight />
                                        </Button>
                                    )}
                                </div>
                            </>
                        )}
                    </div>
                ) : undefined
            }
        >
            {isLoading && <QuickViewSkeleton />}

            {hasFailed && (
                <div className="grid justify-items-start gap-3">
                    <Alert variant="destructive">
                        <CircleAlert />
                        <AlertTitle>Pembayaran tidak bisa dimuat</AlertTitle>
                        <AlertDescription>
                            Cek koneksimu, lalu coba lagi.
                        </AlertDescription>
                    </Alert>
                    <Button
                        variant="outline"
                        onClick={() => {
                            setFailed(null);
                            setAttempt((value) => value + 1);
                        }}
                    >
                        <RotateCw />
                        Muat ulang
                    </Button>
                </div>
            )}

            {data && payment && !isLoading && !hasFailed && (
                <div className="grid gap-6">
                    <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge status={payment.status} />
                        <span className="text-sm text-muted-foreground">
                            {payment.method}
                        </span>
                    </div>

                    {problem && (
                        <Alert variant="destructive">
                            <CircleAlert />
                            <AlertTitle>Belum bisa diproses</AlertTitle>
                            <AlertDescription>
                                {problem}{' '}
                                <Link
                                    href={show(payment.id)}
                                    className="font-semibold underline underline-offset-4"
                                >
                                    Buka detail
                                </Link>{' '}
                                untuk membagi ulang nominalnya.
                            </AlertDescription>
                        </Alert>
                    )}

                    {canAct && !data.can_verify_as_submitted && !problem && (
                        <Alert variant="warning">
                            <CircleAlert />
                            <AlertTitle>Rincian perlu dibagi ulang</AlertTitle>
                            <AlertDescription>
                                Ada rincian yang sudah tidak bisa menerima
                                nominalnya. Buka detail untuk membaginya ke
                                rincian lain.
                            </AlertDescription>
                        </Alert>
                    )}

                    {payment.rejection_reason && (
                        <Alert variant="destructive">
                            <X />
                            <AlertTitle>Ditolak</AlertTitle>
                            <AlertDescription>
                                {payment.rejection_reason}
                            </AlertDescription>
                        </Alert>
                    )}

                    <PaymentProof
                        paymentId={payment.id}
                        hasProof={payment.has_proof}
                        isImage={payment.proof_is_image}
                        isTransfer={payment.method_value === 'transfer'}
                    />

                    <dl className="grid grid-cols-2 gap-x-4 gap-y-4 text-sm">
                        <Fact label="Pembayar" value={payment.payer} wide />
                        <Fact
                            label="Tanggal bayar"
                            value={formatDate(payment.paid_on)}
                        />
                        <Fact label="Dikirim" value={payment.created_at} />
                        {payment.verified_by && (
                            <Fact
                                label="Diperiksa oleh"
                                value={`${payment.verified_by} · ${payment.verified_at}`}
                                wide
                            />
                        )}
                        {payment.note && (
                            <Fact label="Catatan" value={payment.note} wide />
                        )}
                    </dl>

                    <section className="grid gap-3 border-t border-line pt-5">
                        <h3 className="text-sm font-semibold">
                            Untuk membayar apa
                        </h3>
                        <ul className="grid divide-y divide-line">
                            {data.allocations.map((row) => (
                                <li
                                    key={row.bill_item_id}
                                    className="flex items-start justify-between gap-4 py-2.5 first:pt-0"
                                >
                                    <span className="grid gap-0.5 text-sm">
                                        <span className="font-medium">
                                            {row.item}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {row.child} · Tagihan {row.bill}
                                        </span>
                                        {row.room !== null &&
                                            row.amount > row.room && (
                                                <span className="text-xs font-semibold text-amber-700 dark:text-amber-300">
                                                    Hanya bisa menerima{' '}
                                                    {formatRupiah(row.room)}
                                                </span>
                                            )}
                                    </span>
                                    <span className="font-semibold tabular-nums">
                                        {formatRupiah(row.amount)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                        <p className="flex justify-between border-t border-line pt-3 text-sm font-semibold tabular-nums">
                            <span>Total</span>
                            {formatRupiah(payment.amount)}
                        </p>
                    </section>
                </div>
            )}
        </QuickView>
    );
}

function Fact({
    label,
    value,
    wide = false,
}: {
    label: string;
    value: string | null;
    wide?: boolean;
}) {
    return (
        <div className={wide ? 'col-span-2 grid gap-0.5' : 'grid gap-0.5'}>
            <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </dt>
            <dd className="font-medium break-words">{value ?? '—'}</dd>
        </div>
    );
}

function QuickViewSkeleton() {
    return (
        <div className="grid gap-6" aria-busy="true" aria-label="Memuat">
            <Skeleton className="h-6 w-40 rounded-full" />
            <Skeleton className="h-56 w-full rounded-2xl" />
            <div className="grid grid-cols-2 gap-4">
                <Skeleton className="col-span-2 h-10" />
                <Skeleton className="h-10" />
                <Skeleton className="h-10" />
            </div>
            <div className="grid gap-3 border-t border-line pt-5">
                <Skeleton className="h-5 w-32" />
                <Skeleton className="h-10 w-full" />
                <Skeleton className="h-10 w-full" />
            </div>
        </div>
    );
}
