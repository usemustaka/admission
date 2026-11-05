import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import {
    BadgeCheck,
    ClipboardCheck,
    Download,
    GraduationCap,
    Megaphone,
    MessageCircle,
    RotateCcw,
    Settings2,
    ShieldAlert,
    UsersRound,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { BulkBar } from '@/components/bulk-bar';
import { DataTable } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FilterPills } from '@/components/filter-pills';
import { OptionSelect } from '@/components/option-select';
import { PageHeader } from '@/components/page-header';
import { Pager } from '@/components/pager';
import { PersonAvatar } from '@/components/person-avatar';
import { BulkSkippedDialog } from '@/components/registrants/bulk-skipped-dialog';
import { FilterToggle } from '@/components/registrants/filter-toggle';
import { RegistrantQuickView } from '@/components/registrants/registrant-quick-view';
import { RelativeTime } from '@/components/registrants/relative-time';
import { SearchInput } from '@/components/search-input';
import { StatCard } from '@/components/stat-card';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import { edit as admissionSettings } from '@/routes/admin/admission-settings';
import registrantRoutes, {
    bulk,
    index,
    show,
} from '@/routes/admin/registrants';
import type { Option, Paginated } from '@/types/people';
import type {
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
    bayar: '' | 'lunas' | 'belum';
    belum_ujian: boolean;
    urut: 'nama' | 'terbaru';
    arah: 'asc' | 'desc';
};

type BulkAction = 'accept' | 'announce';

const MAX_BULK = 200;

const paymentOptions: Option[] = [
    { value: 'lunas', label: 'Lunas' },
    { value: 'belum', label: 'Belum lunas' },
];

/**
 * The query string a set of filters stands for; defaults are left out so URLs stay short.
 */
function toQuery(filters: Filters) {
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
            filters.urut === 'nama' && filters.arah === 'asc'
                ? undefined
                : filters.urut === 'terbaru' && filters.arah === 'desc'
                  ? undefined
                  : filters.arah,
    };
}

export default function RegistrantsIndex({
    registrants,
    filters,
    options,
    counts,
    statusCounts,
    canManage,
}: {
    registrants: Paginated<RegistrantRow>;
    filters: Filters;
    options: {
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
    canManage: boolean;
}) {
    const [term, setTerm] = useState(filters.cari);
    const [loading, setLoading] = useState(false);
    const [quickId, setQuickId] = useState<number | null>(null);
    const [quickOpen, setQuickOpen] = useState(false);
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const [confirming, setConfirming] = useState<BulkAction | null>(null);
    const [bulkProcessing, setBulkProcessing] = useState(false);
    const [bulkResult, setBulkResult] = useState<{
        done: number;
        skipped: BulkSkipped[];
    } | null>(null);
    const isFirstRender = useRef(true);

    function visit(changes: Partial<Filters>) {
        const next = { ...filters, cari: term, ...changes };

        if (!('urut' in changes) && !('arah' in changes)) {
            setSelected(new Set());
        }

        router.get(index().url, toQuery(next), {
            preserveState: true,
            replace: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    }

    function resetFilters() {
        setTerm('');
        setSelected(new Set());
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
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );
    }

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
                    | { done: number; skipped: BulkSkipped[] }
                    | undefined;

                if (result && result.skipped.length > 0) {
                    setBulkResult({
                        done: result.done,
                        skipped: result.skipped,
                    });
                }
            }),
        [],
    );

    function openQuickView(row: RegistrantRow) {
        setQuickId(row.id);
        setQuickOpen(true);
    }

    function runBulk(action: BulkAction) {
        setBulkProcessing(true);
        router.post(
            bulk().url,
            { action, ids: [...selected].map(Number) },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setSelected(new Set()),
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            'Pendaftar belum bisa diproses. Coba lagi ya.',
                    ),
                onFinish: () => {
                    setBulkProcessing(false);
                    setConfirming(null);
                },
            },
        );
    }

    const sorting: SortingState = [
        {
            id: filters.urut === 'nama' ? 'child' : 'registered_at',
            desc: filters.arah === 'desc',
        },
    ];

    const columns = useMemo<ColumnDef<RegistrantRow>[]>(
        () => [
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
                                    {registrant.child.nik ?? 'Belum ada NIK'}
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
                        <span className="text-xs text-muted-foreground">
                            {row.original.wave}
                        </span>
                    </span>
                ),
            },
            {
                id: 'status',
                header: 'Status',
                enableSorting: false,
                cell: ({ row }) => (
                    <span className="flex flex-wrap items-center gap-1.5">
                        <StatusBadge status={row.original.status} />
                        {row.original.needs_identity_review && (
                            <Badge variant="danger">
                                <ShieldAlert />
                                Cek NIK
                            </Badge>
                        )}
                    </span>
                ),
            },
            {
                id: 'payment',
                header: 'Pembayaran',
                enableSorting: false,
                meta: { className: 'hidden lg:table-cell' },
                cell: ({ row }) =>
                    row.original.payment === 'none' ? (
                        <span className="text-sm text-muted-foreground">
                            Tanpa tagihan
                        </span>
                    ) : (
                        <StatusBadge status={row.original.payment} />
                    ),
            },
            {
                id: 'exam',
                header: 'Ujian',
                enableSorting: false,
                meta: { className: 'hidden whitespace-nowrap xl:table-cell' },
                cell: ({ row }) => {
                    const { exam_session, exam_date, passed } = row.original;

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
                meta: { className: 'hidden whitespace-nowrap md:table-cell' },
                cell: ({ row }) => (
                    <span className="text-sm">
                        <RelativeTime value={row.original.registered_at} />
                    </span>
                ),
            },
            {
                id: 'actions',
                header: () => <span className="sr-only">Aksi</span>,
                enableSorting: false,
                meta: { className: 'text-right' },
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
                            <Button variant="ghost" className="md:h-8" asChild>
                                <Link href={show(row.original.id)}>Detail</Link>
                            </Button>
                        </span>
                    );
                },
            },
        ],
        [],
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
        query: { ...toQuery({ ...filters, cari: term }) },
    });

    const statusItems = [
        { value: '', label: 'Semua', count: statusCounts.all },
        ...options.statuses.map((status) => ({
            value: status.value,
            label: status.label,
            count: statusCounts[status.value] ?? 0,
        })),
    ];

    const confirmCopy: Record<
        BulkAction,
        { title: string; description: string; label: string }
    > = {
        accept: {
            title: `Terima data ${selected.size} pendaftar?`,
            description:
                'Data dinyatakan valid dan tagihan pelunasan terbit kalau aturannya punya. Yang bukan berstatus Sedang diperiksa atau masih perlu cek NIK akan dilewati.',
            label: 'Ya, terima',
        },
        announce: {
            title: `Tandai ${selected.size} pendaftar sudah diumumkan?`,
            description:
                'Pastikan pengumumannya sudah dikirim ke kontak utama. Yang bukan berstatus Data valid atau masih perlu cek NIK akan dilewati.',
            label: 'Ya, tandai',
        },
    };

    return (
        <>
            <Head title="Pendaftar" />
            <div className="grid gap-6">
                <PageHeader
                    title="Pendaftar"
                    description="Semua pendaftaran siswa baru. Klik satu baris untuk melihat ringkasannya tanpa meninggalkan daftar."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a href={exportUrl}>
                                    <Download />
                                    Ekspor CSV
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={admissionSettings()}>
                                    <Settings2 />
                                    Templat pengumuman
                                </Link>
                            </Button>
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

                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1.6fr)_repeat(3,minmax(0,1fr))]">
                        <SearchInput
                            value={term}
                            onValueChange={setTerm}
                            placeholder="Cari nama atau NIK…"
                            aria-label="Cari pendaftar"
                            className="md:col-span-2 xl:col-span-1"
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
                        {hasFilter && (
                            <Button variant="ghost" onClick={resetFilters}>
                                <RotateCcw />
                                Reset filter
                            </Button>
                        )}
                    </div>
                </div>

                {registrants.data.length === 0 ? (
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
                    <div
                        aria-busy={loading}
                        className={cn(
                            'transition-opacity',
                            loading && 'pointer-events-none opacity-60',
                        )}
                    >
                        <DataTable
                            columns={columns}
                            data={registrants.data}
                            getRowId={(row) => String(row.id)}
                            sorting={sorting}
                            selection={
                                canManage
                                    ? { selected, onChange: setSelected }
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
                                                : 'terbaru',
                                        arah: first.desc ? 'desc' : 'asc',
                                    });
                                }
                            }}
                        />
                    </div>
                )}

                <Pager page={registrants} noun="pendaftar" />
            </div>

            <RegistrantQuickView
                registrantId={quickId}
                open={quickOpen}
                onOpenChange={setQuickOpen}
            />

            <BulkBar
                count={selected.size}
                noun="pendaftar"
                onClear={() => setSelected(new Set())}
            >
                <Button
                    size="sm"
                    disabled={selected.size > MAX_BULK}
                    onClick={() => setConfirming('accept')}
                >
                    <BadgeCheck />
                    Terima data
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    disabled={selected.size > MAX_BULK}
                    onClick={() => setConfirming('announce')}
                >
                    <Megaphone />
                    Umumkan
                </Button>
                {selected.size > MAX_BULK && (
                    <span className="text-xs">
                        Paling banyak {MAX_BULK} sekali proses.
                    </span>
                )}
            </BulkBar>

            <ConfirmDialog
                open={confirming !== null}
                onOpenChange={(open) => !open && setConfirming(null)}
                title={confirming ? confirmCopy[confirming].title : ''}
                description={
                    confirming ? confirmCopy[confirming].description : undefined
                }
                confirmLabel={confirming ? confirmCopy[confirming].label : ''}
                processing={bulkProcessing}
                onConfirm={() => confirming && runBulk(confirming)}
            />

            <BulkSkippedDialog
                skipped={bulkResult?.skipped ?? null}
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
