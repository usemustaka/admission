import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import {
    BadgeCheck,
    ClipboardCheck,
    Clock,
    Download,
    GraduationCap,
    Megaphone,
    MessageCircle,
    MessageSquareWarning,
    RotateCcw,
    Settings2,
    ShieldAlert,
    UsersRound,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { BulkBar } from '@/components/bulk-bar';
import { DataTable } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FilterPills } from '@/components/filter-pills';
import { OptionSelect } from '@/components/option-select';
import { PageHeader } from '@/components/page-header';
import { Pager } from '@/components/pager';
import { PersonAvatar } from '@/components/person-avatar';
import { BulkActionDialog } from '@/components/registrants/bulk-action-dialog';
import type { BulkSelection } from '@/components/registrants/bulk-action-dialog';
import { BulkSkippedDialog } from '@/components/registrants/bulk-skipped-dialog';
import { FilterToggle } from '@/components/registrants/filter-toggle';
import { RegistrantQuickView } from '@/components/registrants/registrant-quick-view';
import { RelativeTime } from '@/components/registrants/relative-time';
import { RegistrantsTableSkeleton } from '@/components/registrants/registrants-table-skeleton';
import { WaitingSince } from '@/components/registrants/waiting-since';
import { SearchInput } from '@/components/search-input';
import { StatCard } from '@/components/stat-card';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import { edit as admissionSettings } from '@/routes/admin/admission-settings';
import registrantRoutes, { index, show } from '@/routes/admin/registrants';
import type { Option, Paginated } from '@/types/people';
import type {
    BulkAction,
    BulkSkipped,
    RegistrantRow,
    StatusCounts,
} from '@/types/registrant';

type Filters = {
    cari: string;
    status: string;
    lembaga: number | '';
    gelombang: number | '';
    perlu_dicek: boolean;
    bayar: '' | 'lunas' | 'belum' | 'menunggu';
    belum_ujian: boolean;
    urut: 'nama' | 'terbaru' | 'dikirim';
    arah: 'asc' | 'desc';
    per_halaman: number;
};

/** Ticked rows that can be picked one by one; the server accepts no more ids than this per run. */
const MAX_BULK = 200;

/** Most registrants one "select all results" run handles (the server's cap). */
const MAX_FILTERED = 1000;

const DEFAULT_PER_PAGE = 25;

const paymentOptions: Option[] = [
    { value: 'lunas', label: 'Lunas' },
    { value: 'belum', label: 'Belum lunas' },
    { value: 'menunggu', label: 'Bukti bayar menunggu' },
];

/** Newest registration first; names and waiting time run from the start. */
function defaultDirection(urut: Filters['urut']) {
    return urut === 'terbaru' ? 'desc' : 'asc';
}

/**
 * The query string the filters and the order stand for; defaults are left out so URLs stay short. This is also
 * what a "select all results" bulk action sends, so the server re-runs exactly what the list shows.
 */
function filterQuery(filters: Filters) {
    return {
        cari: filters.cari || undefined,
        status: filters.status || undefined,
        lembaga: filters.lembaga || undefined,
        gelombang: filters.gelombang || undefined,
        perlu_dicek: filters.perlu_dicek ? 1 : undefined,
        bayar: filters.bayar || undefined,
        belum_ujian: filters.belum_ujian ? 1 : undefined,
        urut: filters.urut === 'terbaru' ? undefined : filters.urut,
        arah:
            filters.arah === defaultDirection(filters.urut)
                ? undefined
                : filters.arah,
    };
}

/** The list's own URL: the filters plus the page size. */
function toQuery(filters: Filters) {
    return {
        ...filterQuery(filters),
        per_halaman:
            filters.per_halaman === DEFAULT_PER_PAGE
                ? undefined
                : filters.per_halaman,
    };
}

const LIST_FAILED = 'Daftar pendaftar belum bisa dimuat. Coba lagi ya.';

/**
 * What to tell the committee when loading the list with other filters fails; the old list stays on screen.
 */
const listFailure = {
    onError: (errors: Record<string, string>) =>
        toast.error(Object.values(errors)[0] ?? LIST_FAILED),
    onNetworkError: () => {
        toast.error(LIST_FAILED);
    },
};

export default function RegistrantsIndex({
    registrants,
    filters,
    options,
    counts,
    statusCounts,
    can,
}: {
    registrants: Paginated<RegistrantRow>;
    filters: Filters;
    options: {
        per_page: number[];
        statuses: Option[];
        organizations: Option<number>[];
        waves: Option<number>[];
    };
    counts: {
        needs_review: number;
        in_review: number;
        validated: number;
        announced: number;
    };
    statusCounts: StatusCounts;
    can: { bulk: boolean; export: boolean; editAnnouncement: boolean };
}) {
    const [term, setTerm] = useState(filters.cari);
    const [loading, setLoading] = useState(false);
    const [quickId, setQuickId] = useState<number | null>(null);
    const [quickOpen, setQuickOpen] = useState(false);
    const [selected, setSelected] = useState<Set<string>>(new Set());
    /** Every result of the filters is selected, not only the ticked rows. */
    const [selectAll, setSelectAll] = useState(false);
    const [confirming, setConfirming] = useState<BulkAction | null>(null);
    const [bulkResult, setBulkResult] = useState<{
        done: number;
        skipped: BulkSkipped[];
        skippedTotal: number;
    } | null>(null);
    const isFirstRender = useRef(true);

    function visit(changes: Partial<Filters>) {
        const next = { ...filters, cari: term, ...changes };

        setSelected(new Set());
        setSelectAll(false);

        router.get(index().url, toQuery(next), {
            preserveState: true,
            replace: true,
            ...listFailure,
        });
    }

    function resetFilters() {
        setTerm('');
        setSelected(new Set());
        setSelectAll(false);
        router.get(
            index().url,
            toQuery({
                ...filters,
                cari: '',
                status: '',
                lembaga: '',
                gelombang: '',
                perlu_dicek: false,
                bayar: '',
                belum_ujian: false,
            }),
            {
                preserveState: true,
                replace: true,
                ...listFailure,
            },
        );
    }

    /** Any visit to the list (a filter, a sort, another page) shows the table skeleton until it is loaded. */
    useEffect(() => {
        const listPath = new URL(index().url, window.location.origin).pathname;
        const stopStart = router.on('start', (event) => {
            const { visit } = event.detail;

            if (visit.method === 'get' && visit.url.pathname === listPath) {
                setLoading(true);
            }
        });
        const stopFinish = router.on('finish', () => setLoading(false));

        return () => {
            stopStart();
            stopFinish();
        };
    }, []);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => visit({ cari: term }), 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [term]);

    useEffect(
        () =>
            router.on('flash', (event) => {
                const flash = (event as CustomEvent).detail?.flash;
                const result = flash?.bulk as
                    | {
                          done: number;
                          skipped: BulkSkipped[];
                          skipped_total: number;
                      }
                    | undefined;

                if (result && result.skipped.length > 0) {
                    setBulkResult({
                        done: result.done,
                        skipped: result.skipped,
                        skippedTotal: result.skipped_total,
                    });
                }
            }),
        [],
    );

    function openQuickView(row: RegistrantRow) {
        setQuickId(row.id);
        setQuickOpen(true);
    }

    function changeSelection(next: Set<string>) {
        setSelected(next);
        setSelectAll(false);
    }

    const sorting: SortingState = [
        {
            id:
                filters.urut === 'nama'
                    ? 'child'
                    : filters.urut === 'dikirim'
                      ? 'waiting'
                      : 'registered_at',
            desc: filters.arah === 'desc',
        },
    ];

    /** What the detail page needs to keep its queue in step with this list: the filters and the order. */
    const detailQuery = useMemo(
        () => ({
            ...filterQuery({ ...filters, cari: term }),
            urut: filters.urut,
            arah: filters.arah,
        }),
        [filters, term],
    );

    /** Waiting time only means something while registrants are in review, so it gets a column there. */
    const showWaiting =
        filters.status === 'review' || filters.urut === 'dikirim';

    const columns = useMemo<ColumnDef<RegistrantRow>[]>(
        () =>
            (
                [
                    {
                        id: 'child',
                        header: 'Calon siswa',
                        accessorFn: (row) => row.child.name,
                        enableSorting: true,
                        cell: ({ row }) => {
                            const registrant = row.original;

                            return (
                                <button
                                    type="button"
                                    onClick={() => openQuickView(registrant)}
                                    className="flex min-h-10 items-center gap-3 rounded-lg text-left outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                >
                                    <PersonAvatar
                                        name={registrant.child.name}
                                        src={registrant.child.photo_url}
                                        size="sm"
                                    />
                                    <span className="grid">
                                        <span className="font-semibold">
                                            {registrant.child.name}
                                        </span>
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            {registrant.child.nik ??
                                                'Belum ada NIK'}
                                        </span>
                                        <span className="mt-1.5 md:hidden">
                                            <RowStatus
                                                registrant={registrant}
                                            />
                                        </span>
                                    </span>
                                </button>
                            );
                        },
                    },
                    {
                        id: 'program',
                        header: 'Lembaga',
                        enableSorting: false,
                        meta: { className: 'hidden md:table-cell' },
                        cell: ({ row }) => (
                            <span className="grid">
                                <span>{row.original.organization}</span>
                                <span className="text-xs whitespace-nowrap text-muted-foreground">
                                    {row.original.wave}
                                </span>
                            </span>
                        ),
                    },
                    {
                        id: 'status',
                        header: 'Status',
                        enableSorting: false,
                        meta: { className: 'hidden md:table-cell' },
                        cell: ({ row }) => (
                            <RowStatus registrant={row.original} />
                        ),
                    },
                    {
                        id: 'payment',
                        header: 'Pembayaran',
                        enableSorting: false,
                        meta: { className: 'hidden lg:table-cell' },
                        cell: ({ row }) => (
                            <span className="flex flex-wrap items-center gap-1.5">
                                {row.original.payment === 'none' ? (
                                    <span className="text-sm text-muted-foreground">
                                        Tanpa tagihan
                                    </span>
                                ) : (
                                    <StatusBadge
                                        status={row.original.payment}
                                    />
                                )}
                                {row.original.payment_pending > 0 && (
                                    <Badge variant="warning">
                                        <Clock />
                                        Menunggu verifikasi
                                    </Badge>
                                )}
                            </span>
                        ),
                    },
                    {
                        id: 'exam',
                        header: 'Ujian',
                        enableSorting: false,
                        meta: {
                            className: 'hidden whitespace-nowrap xl:table-cell',
                        },
                        cell: ({ row }) => {
                            const { exam_session, exam_date, passed } =
                                row.original;

                            return (
                                <span className="grid text-sm">
                                    {exam_session && exam_date ? (
                                        <>
                                            <span>{formatDate(exam_date)}</span>
                                            <span className="text-xs text-muted-foreground">
                                                {exam_session}
                                            </span>
                                        </>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            Belum memilih
                                        </span>
                                    )}
                                    {passed !== null && (
                                        <span
                                            className={cn(
                                                'text-xs font-semibold',
                                                passed
                                                    ? 'text-emerald-700 dark:text-emerald-300'
                                                    : 'text-red-700 dark:text-red-300',
                                            )}
                                        >
                                            {passed ? 'Tuntas' : 'Belum tuntas'}
                                        </span>
                                    )}
                                </span>
                            );
                        },
                    },
                    {
                        id: 'registered_at',
                        header: 'Mendaftar',
                        accessorFn: (row) => row.registered_at,
                        enableSorting: true,
                        meta: {
                            className: 'hidden whitespace-nowrap md:table-cell',
                        },
                        cell: ({ row }) => (
                            <span className="text-sm">
                                <RelativeTime
                                    value={row.original.registered_at}
                                />
                            </span>
                        ),
                    },
                    {
                        id: 'waiting',
                        header: 'Menunggu sejak',
                        accessorFn: (row) => row.submitted_at,
                        enableSorting: true,
                        sortDescFirst: false,
                        meta: {
                            className: 'hidden whitespace-nowrap lg:table-cell',
                        },
                        cell: ({ row }) => (
                            <span className="text-sm">
                                <WaitingSince
                                    value={row.original.submitted_at}
                                    waiting={row.original.status === 'review'}
                                />
                            </span>
                        ),
                    },
                    {
                        id: 'actions',
                        header: () => <span className="sr-only">Aksi</span>,
                        enableSorting: false,
                        meta: { className: 'hidden text-right md:table-cell' },
                        cell: ({ row }) => {
                            const { contact, child } = row.original;

                            return (
                                <span className="flex items-center justify-end gap-1.5">
                                    {contact ? (
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            className="size-10 md:size-8"
                                            asChild
                                        >
                                            <a
                                                href={contact.whatsapp_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                aria-label={`WhatsApp ${contact.name} (${contact.label.toLowerCase()} ${child.name})`}
                                                title={`WhatsApp ${contact.name} · ${contact.label}`}
                                            >
                                                <MessageCircle />
                                            </a>
                                        </Button>
                                    ) : (
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            className="size-10 md:size-8"
                                            disabled
                                            aria-label={`Belum ada nomor HP untuk ${child.name}`}
                                            title="Belum ada nomor HP kontak"
                                        >
                                            <MessageCircle />
                                        </Button>
                                    )}
                                    <Button
                                        variant="ghost"
                                        className="md:h-8"
                                        asChild
                                    >
                                        <Link
                                            href={show(row.original.id, {
                                                query: detailQuery,
                                            })}
                                        >
                                            Detail
                                        </Link>
                                    </Button>
                                </span>
                            );
                        },
                    },
                ] satisfies ColumnDef<RegistrantRow>[]
            ).filter((column) => showWaiting || column.id !== 'waiting'),
        [detailQuery, showWaiting],
    );

    const hasFilter =
        filters.cari !== '' ||
        filters.status !== '' ||
        filters.lembaga !== '' ||
        filters.gelombang !== '' ||
        filters.perlu_dicek ||
        filters.bayar !== '' ||
        filters.belum_ujian;

    const exportUrl = registrantRoutes.export.url({
        query: toQuery({ ...filters, cari: term }),
    });

    const statusItems = [
        { value: '', label: 'Semua', count: statusCounts.all },
        ...options.statuses.map((status) => ({
            value: status.value,
            label: status.label,
            count: statusCounts[status.value] ?? 0,
        })),
    ];

    const pageIds = registrants.data.map((row) => String(row.id));
    const allOnPageSelected =
        pageIds.length > 0 && pageIds.every((id) => selected.has(id));
    const hasMoreThanPage = registrants.total > registrants.data.length;

    /** Who a bulk action is about: every result of the filters, or the ticked rows. */
    const bulkSelection: BulkSelection = selectAll
        ? {
              mode: 'filters',
              filters: filterQuery(filters),
              total: registrants.total,
          }
        : { mode: 'ids', ids: [...selected].map(Number) };

    /** How many ticked rows each step applies to, from the statuses on this page; unknown for a select-all. */
    const selectedRows = registrants.data.filter((row) =>
        selected.has(String(row.id)),
    );
    const eligibleCount: Record<BulkAction, number> = {
        accept: selectedRows.filter((row) => row.can.accept).length,
        announce: selectedRows.filter((row) => row.can.announce).length,
        revise: selectedRows.filter((row) => row.can.revise).length,
    };
    const noneEligibleHint: Record<BulkAction, string> = {
        accept: 'Tidak ada yang berstatus Sedang diperiksa di pilihanmu.',
        announce: 'Tidak ada yang berstatus Data valid di pilihanmu.',
        revise: 'Tidak ada yang berstatus Sedang diperiksa di pilihanmu.',
    };

    const selectionExportUrl = selectAll
        ? exportUrl
        : registrantRoutes.export.url({
              query: { selected_only: 1, ids: [...selected].map(Number) },
          });

    const tooManyTicked = !selectAll && selected.size > MAX_BULK;

    function bulkButton(action: BulkAction) {
        const ticked = !selectAll;
        const none = ticked && eligibleCount[action] === 0;

        return {
            disabled: tooManyTicked || none,
            title: none ? noneEligibleHint[action] : undefined,
            suffix: ticked ? ` (${eligibleCount[action]})` : '',
        };
    }

    return (
        <>
            <Head title="Pendaftar" />
            <div className="grid gap-6">
                <PageHeader
                    title="Pendaftar"
                    description="Semua pendaftaran siswa baru. Klik satu baris untuk melihat ringkasannya tanpa meninggalkan daftar."
                    actions={
                        <>
                            {can.export && (
                                <Button variant="outline" asChild>
                                    <a href={exportUrl}>
                                        <Download />
                                        Ekspor CSV
                                    </a>
                                </Button>
                            )}
                            {can.editAnnouncement && (
                                <Button variant="outline" asChild>
                                    <Link href={admissionSettings()}>
                                        <Settings2 />
                                        Templat pengumuman
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatCard
                        label="Perlu diperiksa"
                        value={counts.in_review}
                        icon={ClipboardCheck}
                        hint="Menunggu panitia"
                        tone={counts.in_review > 0 ? 'warning' : 'default'}
                        href={index({ query: { status: 'review' } })}
                    />
                    <StatCard
                        label="Perlu cek NIK"
                        value={counts.needs_review}
                        icon={ShieldAlert}
                        hint="Belum bisa diproses"
                        tone={counts.needs_review > 0 ? 'danger' : 'default'}
                        href={index({ query: { perlu_dicek: 1 } })}
                    />
                    <StatCard
                        label="Siap diumumkan"
                        value={counts.validated}
                        icon={Megaphone}
                        hint="Data sudah valid"
                        tone={counts.validated > 0 ? 'success' : 'default'}
                        href={index({ query: { status: 'validated' } })}
                    />
                    <StatCard
                        label="Siap ditempatkan"
                        value={counts.announced}
                        icon={GraduationCap}
                        hint="Sudah diumumkan"
                        tone={counts.announced > 0 ? 'success' : 'default'}
                        href={index({ query: { status: 'announced' } })}
                    />
                </div>

                <div className="grid gap-4">
                    <FilterPills
                        label="Status"
                        value={filters.status}
                        onChange={(status) => visit({ status })}
                        items={statusItems}
                    />

                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,1.6fr)_repeat(3,minmax(0,1fr))]">
                        <SearchInput
                            value={term}
                            onValueChange={setTerm}
                            placeholder="Cari nama anak, orang tua, HP, atau NIK…"
                            aria-label="Cari pendaftar"
                            className="sm:col-span-2 xl:col-span-1"
                        />
                        <OptionSelect
                            aria-label="Lembaga"
                            value={String(filters.lembaga)}
                            onValueChange={(lembaga) =>
                                visit({
                                    lembaga: lembaga ? Number(lembaga) : '',
                                })
                            }
                            options={options.organizations}
                            anyLabel="Semua lembaga"
                        />
                        <OptionSelect
                            aria-label="Gelombang"
                            value={String(filters.gelombang)}
                            onValueChange={(gelombang) =>
                                visit({
                                    gelombang: gelombang
                                        ? Number(gelombang)
                                        : '',
                                })
                            }
                            options={options.waves}
                            anyLabel="Semua gelombang"
                        />
                        <OptionSelect
                            aria-label="Pembayaran"
                            value={filters.bayar}
                            onValueChange={(bayar) =>
                                visit({ bayar: bayar as Filters['bayar'] })
                            }
                            options={paymentOptions}
                            anyLabel="Semua pembayaran"
                            className="sm:col-span-2 xl:col-span-1"
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <FilterToggle
                            pressed={filters.perlu_dicek}
                            onPressedChange={(perlu_dicek) =>
                                visit({ perlu_dicek })
                            }
                        >
                            Perlu cek NIK
                        </FilterToggle>
                        <FilterToggle
                            pressed={filters.belum_ujian}
                            onPressedChange={(belum_ujian) =>
                                visit({ belum_ujian })
                            }
                        >
                            Belum memilih ujian
                        </FilterToggle>
                        <FilterToggle
                            pressed={
                                filters.urut === 'dikirim' &&
                                filters.arah === 'asc'
                            }
                            onPressedChange={(oldestFirst) =>
                                oldestFirst
                                    ? visit({
                                          urut: 'dikirim',
                                          arah: 'asc',
                                          status: filters.status || 'review',
                                      })
                                    : visit({ urut: 'terbaru', arah: 'desc' })
                            }
                        >
                            Terlama dulu
                        </FilterToggle>
                        {hasFilter && (
                            <Button variant="ghost" onClick={resetFilters}>
                                <RotateCcw />
                                Reset filter
                            </Button>
                        )}
                    </div>
                </div>

                {can.bulk &&
                    (selectAll || (allOnPageSelected && hasMoreThanPage)) && (
                        <div
                            role="status"
                            className="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-2xl border border-line bg-neutral-50 px-4 py-3 text-sm dark:bg-card"
                        >
                            {selectAll ? (
                                <>
                                    <p>
                                        Semua{' '}
                                        <strong className="tabular-nums">
                                            {registrants.total}
                                        </strong>{' '}
                                        hasil dipilih.
                                        {registrants.total > MAX_FILTERED &&
                                            ` Sekali proses paling banyak ${MAX_FILTERED}; sisanya bisa dijalankan lagi.`}
                                    </p>
                                    <Button
                                        variant="link"
                                        size="sm"
                                        className="h-auto p-0"
                                        onClick={() => setSelectAll(false)}
                                    >
                                        Pilih yang di halaman ini saja
                                    </Button>
                                </>
                            ) : (
                                <>
                                    <p className="tabular-nums">
                                        {selected.size} pendaftar di halaman ini
                                        dipilih.
                                    </p>
                                    <Button
                                        variant="link"
                                        size="sm"
                                        className="h-auto p-0"
                                        onClick={() => setSelectAll(true)}
                                    >
                                        Pilih semua {registrants.total} hasil
                                    </Button>
                                </>
                            )}
                        </div>
                    )}

                {loading ? (
                    <RegistrantsTableSkeleton
                        rows={Math.min(
                            Math.max(registrants.data.length, 5),
                            10,
                        )}
                    />
                ) : registrants.data.length === 0 ? (
                    <EmptyState
                        icon={UsersRound}
                        title={
                            hasFilter
                                ? 'Tidak ada yang cocok'
                                : 'Belum ada pendaftar'
                        }
                        description={
                            hasFilter
                                ? 'Coba ubah atau hapus filternya.'
                                : 'Pendaftaran dari orang tua akan muncul di sini.'
                        }
                        action={
                            hasFilter ? (
                                <Button
                                    variant="outline"
                                    onClick={resetFilters}
                                >
                                    <RotateCcw />
                                    Reset filter
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={registrants.data}
                        getRowId={(row) => String(row.id)}
                        sorting={sorting}
                        selection={
                            can.bulk
                                ? { selected, onChange: changeSelection }
                                : undefined
                        }
                        onRowOpen={openQuickView}
                        activeRowId={
                            quickOpen && quickId !== null
                                ? String(quickId)
                                : null
                        }
                        onSortingChange={(updater) => {
                            const next =
                                typeof updater === 'function'
                                    ? updater(sorting)
                                    : updater;
                            const first = next[0];

                            if (first) {
                                visit({
                                    urut:
                                        first.id === 'child'
                                            ? 'nama'
                                            : first.id === 'waiting'
                                              ? 'dikirim'
                                              : 'terbaru',
                                    arah: first.desc ? 'desc' : 'asc',
                                });
                            }
                        }}
                    />
                )}

                {(registrants.total > DEFAULT_PER_PAGE ||
                    filters.per_halaman !== DEFAULT_PER_PAGE) && (
                    <div className="flex items-center justify-end gap-2 text-sm text-muted-foreground">
                        <span id="per-page-label">Tampilkan</span>
                        <OptionSelect
                            aria-labelledby="per-page-label"
                            className="w-24"
                            value={String(filters.per_halaman)}
                            onValueChange={(perPage) =>
                                visit({ per_halaman: Number(perPage) })
                            }
                            options={options.per_page.map((size) => ({
                                value: size,
                                label: String(size),
                            }))}
                        />
                        <span>per halaman</span>
                    </div>
                )}

                <Pager page={registrants} noun="pendaftar" />
            </div>

            <RegistrantQuickView
                registrantId={quickId}
                open={quickOpen}
                onOpenChange={setQuickOpen}
                detailQuery={detailQuery}
            />

            <BulkBar
                count={selectAll ? registrants.total : selected.size}
                noun="pendaftar"
                onClear={() => {
                    setSelected(new Set());
                    setSelectAll(false);
                }}
            >
                {(
                    [
                        ['accept', 'Terima data', BadgeCheck, 'default'],
                        ['announce', 'Umumkan', Megaphone, 'outline'],
                        [
                            'revise',
                            'Minta revisi',
                            MessageSquareWarning,
                            'outline',
                        ],
                    ] as const
                ).map(([action, label, Icon, variant]) => {
                    const { disabled, title, suffix } = bulkButton(action);

                    return (
                        <span key={action} title={title}>
                            <Button
                                size="sm"
                                variant={variant}
                                disabled={disabled}
                                onClick={() => setConfirming(action)}
                            >
                                <Icon />
                                {label}
                                {suffix}
                            </Button>
                        </span>
                    );
                })}
                {can.export && (
                    <Button size="sm" variant="outline" asChild>
                        <a href={selectionExportUrl}>
                            <Download />
                            Ekspor
                        </a>
                    </Button>
                )}
                {tooManyTicked && (
                    <span className="text-xs">
                        Paling banyak {MAX_BULK} sekali proses.
                    </span>
                )}
            </BulkBar>

            <BulkActionDialog
                action={confirming}
                selection={bulkSelection}
                onClose={() => setConfirming(null)}
                onDone={() => {
                    setConfirming(null);
                    setSelected(new Set());
                    setSelectAll(false);
                }}
            />

            <BulkSkippedDialog
                skipped={bulkResult?.skipped ?? null}
                skippedTotal={bulkResult?.skippedTotal ?? 0}
                done={bulkResult?.done ?? 0}
                onClose={() => setBulkResult(null)}
            />
        </>
    );
}

RegistrantsIndex.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Pendaftar', href: index() },
    ],
};

/** Status of a registration, with the NIK flag next to it. Shown under the name on phones. */
function RowStatus({ registrant }: { registrant: RegistrantRow }) {
    return (
        <span className="flex flex-wrap items-center gap-1.5">
            <StatusBadge status={registrant.status} />
            {registrant.needs_identity_review && (
                <Badge variant="danger">
                    <ShieldAlert />
                    Cek NIK
                </Badge>
            )}
        </span>
    );
}
