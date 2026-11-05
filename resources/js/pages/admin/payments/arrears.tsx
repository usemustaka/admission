import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import {
    CalendarClock,
    Download,
    MessageCircle,
    RotateCcw,
    Settings2,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { DataTable } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FilterPills } from '@/components/filter-pills';
import { OptionSelect } from '@/components/option-select';
import { PageHeader } from '@/components/page-header';
import { Pager } from '@/components/pager';
import { PersonAvatar } from '@/components/person-avatar';
import { SearchInput } from '@/components/search-input';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatDate, formatRupiah } from '@/lib/format';
import { dashboard } from '@/routes';
import { edit as admissionSettings } from '@/routes/admin/admission-settings';
import { arrears, index } from '@/routes/admin/payments';
import { exportMethod } from '@/routes/admin/payments/arrears';
import { show } from '@/routes/admin/registrants';
import type { Option, Paginated } from '@/types/people';

const SEARCH_DELAY = 300;

type Bucket = '0-7' | '8-30' | '31-60' | 'lebih-60';

type ArrearsRow = {
    id: number;
    child_name: string;
    organization: string;
    wave: string;
    types: string[];
    billed: number;
    paid: number;
    pending: number;
    outstanding: number;
    excess: number;
    oldest_issued_on: string;
    age_days: number;
    bucket: Bucket;
    whatsapp_url: string | null;
};

type ArrearsFilters = {
    umur: Bucket | '';
    lembaga: number | '';
    gelombang: number | '';
    cari: string;
    urut: 'sisa' | 'umur' | 'nama';
    arah: 'asc' | 'desc';
};

type Totals = {
    all: { count: number; outstanding: number };
    buckets: Record<Bucket, { count: number; outstanding: number }>;
};

const bucketTone: Record<Bucket, 'muted' | 'info' | 'warning' | 'danger'> = {
    '0-7': 'muted',
    '8-30': 'info',
    '31-60': 'warning',
    'lebih-60': 'danger',
};

const sortIds = { sisa: 'outstanding', umur: 'age', nama: 'name' } as const;

export default function Arrears({
    rows,
    filters,
    totals,
    buckets,
    organizations,
    waves,
    can,
}: {
    rows: Paginated<ArrearsRow>;
    filters: ArrearsFilters;
    totals: Totals;
    buckets: { value: Bucket; label: string }[];
    organizations: Option[];
    waves: Option[];
    can: { export: boolean; manageTemplates: boolean };
}) {
    const [term, setTerm] = useState(filters.cari);
    const isFirstRender = useRef(true);

    /** Asks for the list again with some filters changed; the page goes back to 1. */
    function visit(changes: Partial<ArrearsFilters>) {
        const next = { ...filters, cari: term, ...changes };

        router.get(
            arrears().url,
            {
                umur: next.umur || undefined,
                lembaga: next.lembaga || undefined,
                gelombang: next.gelombang || undefined,
                cari: next.cari || undefined,
                urut: next.urut === 'sisa' ? undefined : next.urut,
                arah:
                    next.arah === (next.urut === 'nama' ? 'asc' : 'desc')
                        ? undefined
                        : next.arah,
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

    const hasNarrowing =
        filters.cari !== '' ||
        filters.lembaga !== '' ||
        filters.gelombang !== '';
    const hasFilter = hasNarrowing || filters.umur !== '';
    const exportQuery = Object.fromEntries(
        Object.entries({
            umur: filters.umur,
            lembaga: filters.lembaga,
            gelombang: filters.gelombang,
            cari: filters.cari,
        }).filter(([, value]) => value !== ''),
    );

    const sorting: SortingState = [
        { id: sortIds[filters.urut], desc: filters.arah === 'desc' },
    ];

    const columns = useMemo<ColumnDef<ArrearsRow>[]>(
        () => [
            {
                id: 'name',
                header: 'Anak',
                enableSorting: true,
                cell: ({ row }) => (
                    <div className="flex min-w-0 items-center gap-3">
                        <PersonAvatar
                            name={row.original.child_name}
                            size="sm"
                        />
                        <span className="grid min-w-0">
                            <Link
                                href={show(row.original.id)}
                                className="w-fit max-w-full truncate rounded-md font-semibold underline-offset-4 outline-none hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                            >
                                {row.original.child_name}
                            </Link>
                            <span className="truncate text-xs text-muted-foreground">
                                {row.original.organization} ·{' '}
                                {row.original.wave}
                            </span>
                            <span className="text-xs text-muted-foreground lg:hidden">
                                {row.original.types.join(', ')}
                            </span>
                        </span>
                    </div>
                ),
            },
            {
                id: 'types',
                header: 'Tagihan terbuka',
                enableSorting: false,
                meta: { className: 'hidden lg:table-cell' },
                cell: ({ row }) => (
                    <span className="text-sm">
                        {row.original.types.join(', ')}
                    </span>
                ),
            },
            {
                id: 'money',
                header: 'Ditagih / dibayar',
                enableSorting: false,
                meta: {
                    className:
                        'hidden text-right whitespace-nowrap md:table-cell',
                },
                cell: ({ row }) => (
                    <span className="grid text-sm tabular-nums">
                        <span>{formatRupiah(row.original.billed)}</span>
                        <span className="text-xs text-muted-foreground">
                            Dibayar {formatRupiah(row.original.paid)}
                        </span>
                        {row.original.pending > 0 && (
                            <span className="text-xs text-amber-700 dark:text-amber-300">
                                Menunggu verifikasi{' '}
                                {formatRupiah(row.original.pending)}
                            </span>
                        )}
                    </span>
                ),
            },
            {
                id: 'outstanding',
                header: 'Sisa',
                enableSorting: true,
                meta: { className: 'text-right whitespace-nowrap' },
                cell: ({ row }) => (
                    <span className="grid">
                        <span className="font-semibold tabular-nums">
                            {formatRupiah(row.original.outstanding)}
                        </span>
                        {row.original.excess > 0 && (
                            <span className="text-xs font-normal text-muted-foreground">
                                Kelebihan bayar{' '}
                                {formatRupiah(row.original.excess)} di rincian
                                lain
                            </span>
                        )}
                    </span>
                ),
            },
            {
                id: 'age',
                header: 'Umur',
                enableSorting: true,
                meta: { className: 'whitespace-nowrap' },
                cell: ({ row }) => (
                    <span className="grid gap-0.5">
                        <Badge
                            variant={bucketTone[row.original.bucket]}
                            className="w-fit"
                        >
                            {row.original.age_days} hari
                        </Badge>
                        <span className="text-xs text-muted-foreground">
                            Terbit {formatDate(row.original.oldest_issued_on)}
                        </span>
                    </span>
                ),
            },
            {
                id: 'remind',
                header: () => <span className="sr-only">Ingatkan</span>,
                enableSorting: false,
                meta: { className: 'text-right whitespace-nowrap' },
                cell: ({ row }) =>
                    row.original.whatsapp_url ? (
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={row.original.whatsapp_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label={`Ingatkan wali ${row.original.child_name} lewat WhatsApp`}
                            >
                                <MessageCircle />
                                Ingatkan
                            </a>
                        </Button>
                    ) : (
                        <Badge variant="muted">Tanpa nomor HP</Badge>
                    ),
            },
        ],
        [],
    );

    const pillItems = [
        { value: '' as const, label: 'Semua', count: totals.all.count },
        ...buckets.map((bucket) => ({
            value: bucket.value,
            label: bucket.label,
            count: totals.buckets[bucket.value].count,
        })),
    ];

    return (
        <>
            <Head title="Tunggakan" />
            <div className="grid gap-6">
                <PageHeader
                    icon={CalendarClock}
                    title="Tunggakan"
                    description="Pendaftar yang masih punya tagihan terbuka. Umur dihitung dari tanggal terbit tagihan terbuka yang paling lama, zona waktu WIB. Pembayaran yang menunggu verifikasi belum mengurangi sisa."
                    actions={
                        <>
                            {can.manageTemplates && (
                                <Button variant="outline" asChild>
                                    <Link href={admissionSettings()}>
                                        <Settings2 />
                                        Templat pengingat
                                    </Link>
                                </Button>
                            )}
                            {can.export && (
                                <Button variant="outline" asChild>
                                    <a
                                        href={
                                            exportMethod({
                                                query: exportQuery,
                                            }).url
                                        }
                                    >
                                        <Download />
                                        Ekspor CSV
                                    </a>
                                </Button>
                            )}
                        </>
                    }
                />

                <section
                    aria-label="Ringkasan tunggakan"
                    className="grid grid-cols-1 gap-3 sm:grid-cols-2"
                >
                    <StatCard
                        label="Total sisa tagihan"
                        value={formatRupiah(totals.all.outstanding)}
                        hint={`${totals.all.count} pendaftar${hasNarrowing ? ' sesuai filter' : ''}`}
                        icon={CalendarClock}
                    />
                    <StatCard
                        label="Lebih dari 60 hari"
                        value={formatRupiah(
                            totals.buckets['lebih-60'].outstanding,
                        )}
                        hint={`${totals.buckets['lebih-60'].count} pendaftar`}
                        tone={
                            totals.buckets['lebih-60'].count > 0
                                ? 'danger'
                                : 'default'
                        }
                    />
                </section>

                <FilterPills
                    label="Filter umur tunggakan"
                    value={filters.umur}
                    onChange={(umur) => visit({ umur })}
                    items={pillItems}
                />

                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_minmax(0,1fr)_auto] xl:items-end">
                    <SearchInput
                        value={term}
                        onValueChange={setTerm}
                        placeholder="Cari nama anak…"
                        aria-label="Cari tunggakan"
                        className="md:col-span-2 xl:col-span-1"
                    />
                    <div className="grid gap-1.5">
                        <Label
                            htmlFor="lembaga"
                            className="text-xs text-muted-foreground"
                        >
                            Lembaga
                        </Label>
                        <OptionSelect
                            id="lembaga"
                            value={String(filters.lembaga)}
                            onValueChange={(value) =>
                                visit({
                                    lembaga: value === '' ? '' : Number(value),
                                })
                            }
                            options={organizations}
                            anyLabel="Semua lembaga"
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label
                            htmlFor="gelombang"
                            className="text-xs text-muted-foreground"
                        >
                            Gelombang
                        </Label>
                        <OptionSelect
                            id="gelombang"
                            value={String(filters.gelombang)}
                            onValueChange={(value) =>
                                visit({
                                    gelombang:
                                        value === '' ? '' : Number(value),
                                })
                            }
                            options={waves}
                            anyLabel="Semua gelombang"
                        />
                    </div>
                    {hasNarrowing && (
                        <Button
                            variant="outline"
                            className="h-12 md:col-span-2 xl:col-span-1"
                            onClick={() => {
                                setTerm('');
                                visit({ cari: '', lembaga: '', gelombang: '' });
                            }}
                        >
                            <RotateCcw />
                            Atur ulang
                        </Button>
                    )}
                </div>

                {rows.data.length === 0 ? (
                    <EmptyState
                        icon={CalendarClock}
                        title={
                            hasFilter
                                ? 'Tidak ada tunggakan yang cocok'
                                : 'Tidak ada tunggakan'
                        }
                        description={
                            hasFilter
                                ? 'Coba ubah atau hapus filternya.'
                                : 'Semua tagihan yang sudah terbit sudah lunas.'
                        }
                        action={
                            hasFilter ? (
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        setTerm('');
                                        visit({
                                            umur: '',
                                            cari: '',
                                            lembaga: '',
                                            gelombang: '',
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
                            data={rows.data}
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
                                            first.id === 'name'
                                                ? 'nama'
                                                : first.id === 'age'
                                                  ? 'umur'
                                                  : 'sisa',
                                        arah: first.desc ? 'desc' : 'asc',
                                    });
                                }
                            }}
                        />
                        <div className="flex flex-wrap justify-between gap-x-4 gap-y-1 px-1 text-sm text-muted-foreground tabular-nums">
                            <span>{rows.total} pendaftar</span>
                            <ul
                                aria-label="Sisa tagihan per umur"
                                className="flex flex-wrap gap-x-4 gap-y-1"
                            >
                                {buckets.map((bucket) => (
                                    <li key={bucket.value}>
                                        {bucket.label}{' '}
                                        <strong className="font-semibold text-foreground">
                                            {formatRupiah(
                                                totals.buckets[bucket.value]
                                                    .outstanding,
                                            )}
                                        </strong>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}

                <Pager page={rows} noun="pendaftar" />
            </div>
        </>
    );
}

Arrears.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Pembayaran', href: index() },
        { title: 'Tunggakan', href: arrears() },
    ],
};
