import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import {
    Check,
    CircleCheck,
    Clock,
    CircleX,
    Download,
    ExternalLink,
    Landmark,
    Plus,
    RotateCcw,
    Wallet,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { BulkBar } from '@/components/bulk-bar';
import { DataTable } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FilterPills } from '@/components/filter-pills';
import { OptionSelect } from '@/components/option-select';
import { PageHeader } from '@/components/page-header';
import { BulkVerifyDialog } from '@/components/payments/bulk-verify-dialog';
import { PaymentBulkSkippedDialog } from '@/components/payments/bulk-skipped-dialog';
import type { PaymentBulkSkipped } from '@/components/payments/bulk-skipped-dialog';
import {
    formatRelativeDay,
    summarizeItems,
} from '@/components/payments/payment-format';
import { PaymentQuickView } from '@/components/payments/payment-quick-view';
import type { QuickViewTarget } from '@/components/payments/payment-quick-view';
import { Pager } from '@/components/pager';
import { PersonAvatar } from '@/components/person-avatar';
import { SearchInput } from '@/components/search-input';
import { StatCard } from '@/components/stat-card';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate, formatRupiah } from '@/lib/format';
import { dashboard } from '@/routes';
import {
    bulkVerify,
    create,
    exportMethod,
    index,
    show,
} from '@/routes/admin/payments';
import type {
    PaymentListFilters,
    PaymentRow,
    PaymentSummary,
} from '@/types/billing';
import type { Option, Paginated } from '@/types/people';

const SEARCH_DELAY = 300;

type PaymentStatusKey = 'pending' | 'verified' | 'rejected';

export default function PaymentsIndex({
    payments,
    filters,
    counts,
    totals,
    summary,
    statuses,
    methods,
    can,
}: {
    payments: Paginated<PaymentRow>;
    filters: PaymentListFilters;
    counts: {
        all: number;
        pending: number;
        verified: number;
        rejected: number;
    };
    totals: { amount: number; by_status: Record<PaymentStatusKey, number> };
    summary: PaymentSummary;
    statuses: Option[];
    methods: Option[];
    can: { verify: boolean };
}) {
    const [term, setTerm] = useState(filters.cari);
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const [target, setTarget] = useState<QuickViewTarget | null>(null);
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [includeWithoutProof, setIncludeWithoutProof] = useState(false);
    const [bulkResult, setBulkResult] = useState<{
        done: number;
        skipped: PaymentBulkSkipped[];
    } | null>(null);
    const isFirstRender = useRef(true);

    /** Re-requests the list with some filters changed; the page goes back to 1 and the selection is dropped. */
    function visit(changes: Partial<PaymentListFilters>) {
        const next = { ...filters, cari: term, ...changes };

        setSelected(new Set());
        router.get(
            index().url,
            {
                status: next.status || undefined,
                cari: next.cari || undefined,
                cara: next.cara || undefined,
                dari: next.dari || undefined,
                sampai: next.sampai || undefined,
                urut: next.urut === 'tanggal' ? undefined : next.urut,
                arah: next.arah === 'desc' ? undefined : next.arah,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => visit({ cari: term }), SEARCH_DELAY);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [term]);

    // A selection never outlives the rows it was made on (another page or order), so the dialog's count and total
    // always describe what is checked.
    useEffect(() => {
        const present = new Set(payments.data.map((row) => String(row.id)));

        setSelected((current) =>
            [...current].every((id) => present.has(id))
                ? current
                : new Set([...current].filter((id) => present.has(id))),
        );
    }, [payments.data]);

    useEffect(
        () =>
            router.on('flash', (event) => {
                const result = (event as CustomEvent).detail?.flash?.bulk as
                    | { done: number; skipped: PaymentBulkSkipped[] }
                    | undefined;

                if (result && result.skipped.length > 0) {
                    setBulkResult(result);
                }
            }),
        [],
    );

    const selectedRows = payments.data.filter((row) =>
        selected.has(String(row.id)),
    );
    const selectedTotal = selectedRows.reduce(
        (sum, row) => sum + row.amount,
        0,
    );
    const selectedWithoutProof = selectedRows.filter(
        (row) => row.status === 'pending' && !row.has_proof,
    ).length;

    const hasNarrowing =
        filters.cari !== '' ||
        filters.cara !== '' ||
        filters.dari !== '' ||
        filters.sampai !== '';
    const hasFilter = hasNarrowing || filters.status !== '';

    /** The part of the filter that decides which payments exist for the quick view queue and the export. */
    const narrowing = useMemo(
        () =>
            Object.fromEntries(
                Object.entries({
                    cari: filters.cari,
                    cara: filters.cara,
                    dari: filters.dari,
                    sampai: filters.sampai,
                }).filter(([, value]) => value !== ''),
            ),
        [filters.cari, filters.cara, filters.dari, filters.sampai],
    );
    /** The order of the list, so the quick view's "next" follows the same one. */
    const order = useMemo(
        () => ({
            ...(filters.urut === 'tanggal' ? {} : { urut: filters.urut }),
            ...(filters.arah === 'desc' ? {} : { arah: filters.arah }),
        }),
        [filters.urut, filters.arah],
    );
    const exportQuery = {
        ...narrowing,
        ...(filters.status ? { status: filters.status } : {}),
    };

    function reset() {
        setTerm('');
        visit({ cari: '', cara: '', dari: '', sampai: '' });
    }

    function verifySelected() {
        router.post(
            bulkVerify().url,
            {
                ids: [...selected].map(Number),
                ...(includeWithoutProof ? { include_without_proof: 1 } : {}),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setConfirming(false);
                    setIncludeWithoutProof(false);
                },
                onSuccess: () => setSelected(new Set()),
                onError: () =>
                    toast.error('Verifikasi massal gagal. Coba lagi.'),
            },
        );
    }

    const sorting: SortingState = [
        {
            id: filters.urut === 'nominal' ? 'amount' : 'paid_on',
            desc: filters.arah === 'desc',
        },
    ];

    const columns = useMemo<ColumnDef<PaymentRow>[]>(
        () => [
            {
                id: 'payer',
                header: 'Pembayar',
                enableSorting: false,
                cell: ({ row }) => {
                    const payment = row.original;

                    return (
                        <div className="flex min-w-0 items-center gap-3">
                            <PersonAvatar name={payment.payer} size="sm" />
                            <span className="grid min-w-0">
                                <button
                                    type="button"
                                    onClick={() =>
                                        setTarget({ id: payment.id })
                                    }
                                    className="w-fit max-w-full truncate rounded-md text-left font-semibold outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                >
                                    {payment.payer}
                                </button>
                                <span className="truncate text-xs text-muted-foreground">
                                    {payment.for.length > 0
                                        ? `Untuk ${payment.for.join(', ')}`
                                        : 'Tanpa rincian'}
                                </span>
                                <span className="text-xs text-muted-foreground md:hidden">
                                    {formatRelativeDay(payment.paid_on)} ·{' '}
                                    {payment.method}
                                </span>
                            </span>
                        </div>
                    );
                },
            },
            {
                id: 'items',
                header: 'Dibayar untuk',
                enableSorting: false,
                meta: { className: 'hidden lg:table-cell max-w-64' },
                cell: ({ row }) => (
                    <span
                        className="line-clamp-2 text-sm"
                        title={row.original.items
                            .map((item) => item.name)
                            .join(', ')}
                    >
                        {summarizeItems(
                            row.original.items.map((item) => item.name),
                        )}
                    </span>
                ),
            },
            {
                id: 'method',
                header: 'Cara bayar',
                enableSorting: false,
                meta: { className: 'hidden md:table-cell whitespace-nowrap' },
                cell: ({ row }) => row.original.method,
            },
            {
                id: 'paid_on',
                header: 'Tanggal',
                enableSorting: true,
                meta: { className: 'hidden md:table-cell whitespace-nowrap' },
                cell: ({ row }) => (
                    <span className="grid">
                        <span>{formatDate(row.original.paid_on)}</span>
                        <span className="text-xs text-muted-foreground">
                            {formatRelativeDay(row.original.paid_on)}
                        </span>
                    </span>
                ),
            },
            {
                id: 'amount',
                header: 'Nominal',
                enableSorting: true,
                meta: { className: 'text-right whitespace-nowrap' },
                cell: ({ row }) => (
                    <span className="font-semibold tabular-nums">
                        {formatRupiah(row.original.amount)}
                    </span>
                ),
            },
            {
                id: 'status',
                header: 'Status',
                enableSorting: false,
                meta: { className: 'whitespace-nowrap' },
                cell: ({ row }) => {
                    const payment = row.original;

                    return (
                        <span className="flex flex-wrap items-center gap-1.5">
                            <StatusBadge status={payment.status} />
                            {payment.status === 'pending' &&
                                payment.method_value === 'transfer' &&
                                !payment.has_proof && (
                                    <Badge variant="warning">Tanpa bukti</Badge>
                                )}
                        </span>
                    );
                },
            },
            {
                id: 'open',
                header: () => <span className="sr-only">Detail</span>,
                enableSorting: false,
                meta: { className: 'w-12 pl-0 text-right' },
                cell: ({ row }) => (
                    <Button variant="ghost" size="icon" asChild>
                        <Link
                            href={show(row.original.id)}
                            aria-label={`Buka detail pembayaran ${row.original.payer}`}
                            prefetch
                        >
                            <ExternalLink />
                        </Link>
                    </Button>
                ),
            },
        ],
        [],
    );

    const pillItems = [
        { value: '', label: 'Semua', count: counts.all },
        ...statuses.map((status) => ({
            value: status.value,
            label: status.label,
            count: counts[status.value as 'pending' | 'verified' | 'rejected'],
        })),
    ];

    return (
        <>
            <Head title="Pembayaran" />
            <div className="grid gap-6">
                <PageHeader
                    icon={Wallet}
                    title="Pembayaran"
                    description="Cek bukti transfer dari orang tua dan catat pembayaran tunai. Hanya yang sudah diverifikasi yang dihitung ke tagihan."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        exportMethod({ query: exportQuery }).url
                                    }
                                >
                                    <Download />
                                    Ekspor CSV
                                </a>
                            </Button>
                            {can.verify && (
                                <Button asChild>
                                    <Link href={create()}>
                                        <Plus />
                                        Catat pembayaran
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <section
                    aria-label="Ringkasan pembayaran"
                    className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4"
                >
                    <StatCard
                        label="Menunggu verifikasi"
                        value={summary.pending_count}
                        hint={
                            summary.pending_count > 0
                                ? `${formatRupiah(summary.pending_amount)} belum dicek`
                                : 'Tidak ada antrean'
                        }
                        tone={summary.pending_count > 0 ? 'warning' : 'default'}
                        icon={Clock}
                        href={index({ query: { status: 'pending' } })}
                    />
                    <StatCard
                        label="Terverifikasi bulan ini"
                        value={formatRupiah(summary.verified_this_month)}
                        hint="Menurut tanggal verifikasi"
                        icon={CircleCheck}
                        href={index({ query: { status: 'verified' } })}
                    />
                    <StatCard
                        label="Ditolak"
                        value={summary.rejected_count}
                        hint={
                            summary.rejected_count > 0
                                ? 'Orang tua bisa mengirim ulang'
                                : undefined
                        }
                        icon={CircleX}
                        href={index({ query: { status: 'rejected' } })}
                    />
                    <StatCard
                        label="Belum tertagih"
                        value={formatRupiah(summary.outstanding)}
                        hint={
                            summary.overpaid > 0
                                ? `Kelebihan bayar ${formatRupiah(summary.overpaid)} di tagihan lain`
                                : 'Sisa dari semua tagihan'
                        }
                        tone={summary.overpaid > 0 ? 'warning' : 'default'}
                        icon={Landmark}
                    />
                </section>

                <FilterPills
                    label="Filter status pembayaran"
                    value={filters.status}
                    onChange={(status) => visit({ status })}
                    items={pillItems}
                />

                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_minmax(0,0.8fr)_minmax(0,0.8fr)_auto] xl:items-end">
                    <SearchInput
                        value={term}
                        onValueChange={setTerm}
                        placeholder="Cari nama anak atau orang tua…"
                        aria-label="Cari pembayaran"
                        className="md:col-span-2 xl:col-span-1"
                    />
                    <div className="grid gap-1.5">
                        <Label
                            htmlFor="cara"
                            className="text-xs text-muted-foreground"
                        >
                            Cara bayar
                        </Label>
                        <OptionSelect
                            id="cara"
                            value={filters.cara}
                            onValueChange={(cara) => visit({ cara })}
                            options={methods}
                            anyLabel="Semua cara bayar"
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label
                            htmlFor="dari"
                            className="text-xs text-muted-foreground"
                        >
                            Dari tanggal
                        </Label>
                        <Input
                            id="dari"
                            type="date"
                            value={filters.dari}
                            max={filters.sampai || undefined}
                            onChange={(event) =>
                                visit({ dari: event.target.value })
                            }
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label
                            htmlFor="sampai"
                            className="text-xs text-muted-foreground"
                        >
                            Sampai tanggal
                        </Label>
                        <Input
                            id="sampai"
                            type="date"
                            value={filters.sampai}
                            min={filters.dari || undefined}
                            onChange={(event) =>
                                visit({ sampai: event.target.value })
                            }
                        />
                    </div>
                    {hasNarrowing && (
                        <Button
                            variant="outline"
                            onClick={reset}
                            className="h-12 md:col-span-2 xl:col-span-1"
                        >
                            <RotateCcw />
                            Atur ulang
                        </Button>
                    )}
                </div>

                {payments.data.length === 0 ? (
                    <EmptyState
                        icon={Wallet}
                        title={
                            hasFilter
                                ? 'Tidak ada pembayaran yang cocok'
                                : 'Belum ada pembayaran'
                        }
                        description={
                            hasFilter
                                ? 'Coba ubah atau hapus filternya.'
                                : 'Pembayaran dari orang tua dan yang dicatat langsung akan muncul di sini.'
                        }
                        action={
                            hasFilter ? (
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        setTerm('');
                                        visit({
                                            status: '',
                                            cari: '',
                                            cara: '',
                                            dari: '',
                                            sampai: '',
                                        });
                                    }}
                                >
                                    <RotateCcw />
                                    Hapus semua filter
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <div className="grid gap-3">
                        <DataTable
                            columns={columns}
                            data={payments.data}
                            getRowId={(row) => String(row.id)}
                            sorting={sorting}
                            onSortingChange={(updater) => {
                                const next =
                                    typeof updater === 'function'
                                        ? updater(sorting)
                                        : updater;
                                const first = next[0];

                                if (first) {
                                    visit({
                                        urut:
                                            first.id === 'amount'
                                                ? 'nominal'
                                                : 'tanggal',
                                        arah: first.desc ? 'desc' : 'asc',
                                    });
                                }
                            }}
                            selection={
                                can.verify
                                    ? { selected, onChange: setSelected }
                                    : undefined
                            }
                            onRowOpen={(row) => setTarget({ id: row.id })}
                            activeRowId={
                                target === null ? null : String(target.id)
                            }
                        />
                        <div className="flex flex-wrap justify-between gap-x-4 gap-y-1 px-1 text-sm text-muted-foreground tabular-nums">
                            <span>{payments.total} pembayaran</span>
                            <ul
                                aria-label={`Total nominal${hasFilter ? ' sesuai filter' : ''}`}
                                className="flex flex-wrap gap-x-4 gap-y-1"
                            >
                                {statuses
                                    .filter(
                                        (status) =>
                                            filters.status === '' ||
                                            filters.status === status.value,
                                    )
                                    .map((status) => (
                                        <li key={status.value}>
                                            {status.label}{' '}
                                            <strong className="font-semibold text-foreground">
                                                {formatRupiah(
                                                    totals.by_status[
                                                        status.value as PaymentStatusKey
                                                    ],
                                                )}
                                            </strong>
                                        </li>
                                    ))}
                            </ul>
                        </div>
                    </div>
                )}

                <Pager page={payments} noun="pembayaran" />
            </div>

            <PaymentQuickView
                target={target}
                narrowing={{ ...narrowing, ...order }}
                onNavigate={(id) => setTarget({ id })}
                onClose={() => setTarget(null)}
            />

            <BulkBar
                count={selected.size}
                noun="pembayaran"
                onClear={() => setSelected(new Set())}
            >
                <Button
                    size="sm"
                    disabled={processing}
                    onClick={() => setConfirming(true)}
                >
                    <Check />
                    Verifikasi
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    asChild
                    className="border-white/30 bg-transparent text-white hover:bg-white/10 hover:text-white dark:border-black/20 dark:text-neutral-900 dark:hover:bg-black/10 dark:hover:text-neutral-900"
                >
                    <a href={exportMethod({ query: exportQuery }).url}>
                        <Download />
                        Ekspor CSV
                    </a>
                </Button>
            </BulkBar>

            <BulkVerifyDialog
                open={confirming}
                onOpenChange={setConfirming}
                count={selected.size}
                total={selectedTotal}
                withoutProof={selectedWithoutProof}
                includeWithoutProof={includeWithoutProof}
                onIncludeWithoutProofChange={setIncludeWithoutProof}
                processing={processing}
                onConfirm={verifySelected}
            />

            <PaymentBulkSkippedDialog
                skipped={bulkResult?.skipped ?? null}
                done={bulkResult?.done ?? 0}
                onClose={() => setBulkResult(null)}
            />
        </>
    );
}

PaymentsIndex.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Pembayaran', href: index() },
    ],
};
