import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import { ClipboardList, Settings2, ShieldAlert } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { DataTable } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { OptionSelect } from '@/components/option-select';
import { PageHeader } from '@/components/page-header';
import { PersonAvatar } from '@/components/person-avatar';
import { SearchInput } from '@/components/search-input';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import { edit as admissionSettings } from '@/routes/admin/admission-settings';
import { index, show } from '@/routes/admin/registrants';
import type { Option, Paginated } from '@/types/people';
import type { RegistrantRow } from '@/types/registrant';

type Filters = {
    cari: string;
    status: string;
    lembaga: number | '';
    gelombang: number | '';
    perlu_dicek: boolean;
    urut: 'nama' | 'terbaru';
    arah: 'asc' | 'desc';
};

export default function RegistrantsIndex({
    registrants,
    filters,
    options,
    counts,
}: {
    registrants: Paginated<RegistrantRow>;
    filters: Filters;
    options: {
        statuses: Option[];
        organizations: Option<number>[];
        waves: Option<number>[];
    };
    counts: { needs_review: number; in_review: number };
}) {
    const [term, setTerm] = useState(filters.cari);
    const isFirstRender = useRef(true);

    function visit(changes: Partial<Filters>) {
        const next = { ...filters, cari: term, ...changes };

        router.get(
            index().url,
            {
                cari: next.cari || undefined,
                status: next.status || undefined,
                lembaga: next.lembaga || undefined,
                gelombang: next.gelombang || undefined,
                perlu_dicek: next.perlu_dicek ? 1 : undefined,
                urut: next.urut === 'terbaru' ? undefined : next.urut,
                arah:
                    next.urut === 'nama' && next.arah === 'asc'
                        ? undefined
                        : next.urut === 'terbaru' && next.arah === 'desc'
                          ? undefined
                          : next.arah,
            },
            { preserveState: true, replace: true },
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
                        <Link
                            href={show(registrant.id)}
                            className="flex items-center gap-3 rounded-lg focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
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
                        </Link>
                    );
                },
            },
            {
                id: 'program',
                header: 'Lembaga',
                enableSorting: false,
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
                            <Badge variant="danger" className="gap-1">
                                <ShieldAlert />
                                Cek NIK
                            </Badge>
                        )}
                    </span>
                ),
            },
            {
                id: 'exam',
                header: 'Ujian',
                enableSorting: false,
                meta: { className: 'whitespace-nowrap' },
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
                                    className={
                                        passed
                                            ? 'text-xs font-semibold text-success'
                                            : 'text-xs font-semibold text-destructive-foreground'
                                    }
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
                meta: { className: 'whitespace-nowrap' },
                cell: ({ row }) =>
                    row.original.registered_at
                        ? formatDate(row.original.registered_at)
                        : '-',
            },
            {
                id: 'actions',
                header: () => <span className="sr-only">Aksi</span>,
                enableSorting: false,
                meta: { className: 'text-right' },
                cell: ({ row }) => (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={show(row.original.id)}>Buka</Link>
                    </Button>
                ),
            },
        ],
        [],
    );

    const hasFilter =
        filters.cari !== '' ||
        filters.status !== '' ||
        filters.lembaga !== '' ||
        filters.gelombang !== '' ||
        filters.perlu_dicek;

    return (
        <>
            <Head title="Pendaftar" />
            <div className="mx-auto grid w-full max-w-6xl gap-6 p-4 md:p-6">
                <PageHeader
                    icon={ClipboardList}
                    title="Pendaftar"
                    description="Semua pendaftaran siswa baru. Buka satu pendaftar untuk memeriksa, menilai, mengumumkan, atau menempatkannya."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={admissionSettings()}>
                                <Settings2 />
                                Templat pengumuman
                            </Link>
                        </Button>
                    }
                />

                <div className="flex flex-wrap gap-2">
                    <Badge variant="info">
                        {counts.in_review} menunggu diperiksa
                    </Badge>
                    {counts.needs_review > 0 && (
                        <Badge variant="danger">
                            {counts.needs_review} perlu cek NIK
                        </Badge>
                    )}
                </div>

                <div className="grid gap-3 md:grid-cols-[minmax(0,1.5fr)_repeat(3,minmax(0,1fr))]">
                    <SearchInput
                        value={term}
                        onValueChange={setTerm}
                        placeholder="Cari nama atau NIK…"
                    />
                    <OptionSelect
                        aria-label="Status"
                        value={filters.status}
                        onValueChange={(status) => visit({ status })}
                        options={options.statuses}
                        anyLabel="Semua status"
                    />
                    <OptionSelect
                        aria-label="Lembaga"
                        value={String(filters.lembaga)}
                        onValueChange={(lembaga) =>
                            visit({ lembaga: lembaga ? Number(lembaga) : '' })
                        }
                        options={options.organizations}
                        anyLabel="Semua lembaga"
                    />
                    <OptionSelect
                        aria-label="Gelombang"
                        value={String(filters.gelombang)}
                        onValueChange={(gelombang) =>
                            visit({
                                gelombang: gelombang ? Number(gelombang) : '',
                            })
                        }
                        options={options.waves}
                        anyLabel="Semua gelombang"
                    />
                </div>

                <label className="flex w-fit items-center gap-2.5 text-sm">
                    <Checkbox
                        checked={filters.perlu_dicek}
                        onCheckedChange={(checked) =>
                            visit({ perlu_dicek: checked === true })
                        }
                    />
                    Hanya yang perlu cek NIK
                </label>

                {registrants.data.length === 0 ? (
                    <EmptyState
                        icon={ClipboardList}
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
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={registrants.data}
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
                                        first.id === 'child'
                                            ? 'nama'
                                            : 'terbaru',
                                    arah: first.desc ? 'desc' : 'asc',
                                });
                            }
                        }}
                    />
                )}

                {registrants.last_page > 1 && (
                    <nav
                        className="flex items-center justify-between gap-4"
                        aria-label="Halaman"
                    >
                        <p className="text-sm text-muted-foreground">
                            Halaman {registrants.current_page} dari{' '}
                            {registrants.last_page} · {registrants.total}{' '}
                            pendaftar
                        </p>
                        <div className="flex gap-2">
                            {registrants.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={registrants.prev_page_url}>
                                        Sebelumnya
                                    </Link>
                                </Button>
                            )}
                            {registrants.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={registrants.next_page_url}>
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

RegistrantsIndex.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Pendaftar', href: index() },
    ],
};
