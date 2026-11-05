import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import { ChevronRight, Plus, SearchX, ShieldAlert, Users } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { DataTable } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FilterPills } from '@/components/filter-pills';
import { OptionSelect } from '@/components/option-select';
import { PageHeader } from '@/components/page-header';
import { Pager } from '@/components/pager';
import { PersonAvatar } from '@/components/person-avatar';
import { PersonQuickView } from '@/components/people/person-quick-view';
import { SearchInput } from '@/components/search-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import { create, index, show } from '@/routes/admin/people';
import type { Option, Paginated, PersonRow } from '@/types/people';

type Filters = {
    cari: string;
    jenis: string;
    data: '' | 'nik' | 'kontak' | 'duplikat';
    urut: 'nama' | 'terbaru';
    arah: 'asc' | 'desc';
};

type Counts = {
    kinds: Record<string, number>;
    data: { nik: number; kontak: number; duplikat: number };
};

const ALL = 'semua';

export default function PeopleIndex({
    people,
    filters,
    options,
    counts,
    can,
}: {
    people: Paginated<PersonRow>;
    filters: Filters;
    options: { kinds: Option[] };
    counts: Counts;
    can: { create: boolean };
}) {
    const [term, setTerm] = useState(filters.cari);
    const [loading, setLoading] = useState(false);
    const [openId, setOpenId] = useState<number | null>(null);
    const isFirstRender = useRef(true);

    function visit(changes: Partial<Filters>) {
        const next = { ...filters, cari: term, ...changes };
        const defaultDirection = next.urut === 'nama' ? 'asc' : 'desc';

        router.get(
            index().url,
            {
                cari: next.cari || undefined,
                jenis: next.jenis || undefined,
                data: next.data || undefined,
                urut: next.urut === 'nama' ? undefined : next.urut,
                arah: next.arah === defaultDirection ? undefined : next.arah,
            },
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

    const sorting: SortingState = [
        {
            id: filters.urut === 'nama' ? 'name' : 'created_at',
            desc: filters.arah === 'desc',
        },
    ];

    const columns = useMemo<ColumnDef<PersonRow>[]>(
        () => [
            {
                id: 'name',
                header: 'Nama',
                accessorFn: (row) => row.name,
                enableSorting: true,
                cell: ({ row }) => {
                    const person = row.original;

                    return (
                        <button
                            type="button"
                            onClick={() => setOpenId(person.id)}
                            aria-label={`Lihat ringkasan ${person.name}`}
                            className="flex min-h-10 items-center gap-3 rounded-lg text-left outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        >
                            <PersonAvatar
                                name={person.name}
                                src={person.photo_url}
                                size="sm"
                            />
                            <span className="grid">
                                <span className="font-semibold">
                                    {person.name}
                                </span>
                                <span className="flex items-center gap-1.5 text-xs text-muted-foreground tabular-nums">
                                    {person.nik ?? 'Belum ada NIK'}
                                    {!person.nik_complete && (
                                        <ShieldAlert
                                            className="size-3.5 text-amber-600"
                                            aria-label="NIK belum lengkap"
                                        />
                                    )}
                                </span>
                            </span>
                        </button>
                    );
                },
            },
            {
                id: 'roles',
                header: 'Peran',
                enableSorting: false,
                meta: { className: 'hidden md:table-cell' },
                cell: ({ row }) =>
                    row.original.roles.length === 0 ? (
                        <span className="text-muted-foreground">-</span>
                    ) : (
                        <span className="flex flex-wrap gap-1.5">
                            {row.original.roles.map((role) => (
                                <Badge key={role} variant="muted">
                                    {role}
                                </Badge>
                            ))}
                        </span>
                    ),
            },
            {
                id: 'contact',
                header: 'Kontak',
                enableSorting: false,
                meta: { className: 'hidden sm:table-cell' },
                cell: ({ row }) => {
                    const person = row.original;

                    if (person.phone) {
                        return (
                            <span className="tabular-nums">{person.phone}</span>
                        );
                    }

                    return person.has_contact ? (
                        <span className="text-sm text-muted-foreground">
                            Lewat orang tua
                        </span>
                    ) : (
                        <Badge variant="warning">Belum ada kontak</Badge>
                    );
                },
            },
            {
                id: 'created_at',
                header: 'Ditambahkan',
                accessorFn: (row) => row.created_at,
                enableSorting: true,
                meta: { className: 'hidden whitespace-nowrap lg:table-cell' },
                cell: ({ row }) =>
                    row.original.created_at
                        ? formatDate(row.original.created_at)
                        : '-',
            },
            {
                id: 'actions',
                header: () => <span className="sr-only">Aksi</span>,
                enableSorting: false,
                meta: { className: 'w-12 text-right' },
                cell: ({ row }) => (
                    <Button
                        variant="ghost"
                        size="icon"
                        asChild
                        className="max-md:size-10"
                    >
                        <Link
                            href={show(row.original.id)}
                            aria-label={`Buka detail ${row.original.name}`}
                        >
                            <ChevronRight />
                        </Link>
                    </Button>
                ),
            },
        ],
        [],
    );

    const hasFilter =
        filters.cari !== '' || filters.jenis !== '' || filters.data !== '';
    const openRow = people.data.find((person) => person.id === openId);

    return (
        <>
            <Head title="Orang" />
            <div className="grid gap-6">
                <PageHeader
                    icon={Users}
                    title="Orang"
                    description="Semua orang yang tercatat: calon siswa, siswa, orang tua, wali, dan pegawai."
                    actions={
                        can.create && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus />
                                    Tambah orang
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="grid gap-3">
                    <FilterPills
                        label="Peran"
                        value={filters.jenis || ALL}
                        onChange={(value) =>
                            visit({ jenis: value === ALL ? '' : value })
                        }
                        items={[
                            {
                                value: ALL,
                                label: 'Semua',
                                count: counts.kinds.all,
                            },
                            ...options.kinds.map((kind) => ({
                                value: String(kind.value),
                                label: kind.label,
                                count: counts.kinds[kind.value],
                            })),
                        ]}
                    />

                    <div className="grid gap-3 sm:grid-cols-[minmax(0,28rem)_16rem]">
                        <SearchInput
                            value={term}
                            onValueChange={setTerm}
                            placeholder="Cari nama, NIK, KK, atau nomor HP…"
                            aria-label="Cari nama, NIK, KK, atau nomor HP"
                        />
                        <OptionSelect
                            aria-label="Kelengkapan data"
                            value={filters.data}
                            onValueChange={(value) =>
                                visit({ data: value as Filters['data'] })
                            }
                            anyLabel="Semua data"
                            options={[
                                {
                                    value: 'nik',
                                    label: `NIK belum lengkap (${counts.data.nik})`,
                                },
                                {
                                    value: 'kontak',
                                    label: `Belum ada kontak (${counts.data.kontak})`,
                                },
                                {
                                    value: 'duplikat',
                                    label: `Kemungkinan duplikat (${counts.data.duplikat})`,
                                },
                            ]}
                        />
                    </div>
                </div>

                {people.data.length === 0 ? (
                    <EmptyState
                        icon={hasFilter ? SearchX : Users}
                        title={
                            hasFilter
                                ? 'Tidak ada yang cocok'
                                : 'Belum ada orang tercatat'
                        }
                        description={
                            hasFilter
                                ? 'Coba kata kunci lain atau longgarkan filternya.'
                                : can.create
                                  ? 'Mulai dengan menambahkan orang pertama.'
                                  : 'Belum ada orang yang tercatat.'
                        }
                        action={
                            hasFilter ? (
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        setTerm('');
                                        router.get(
                                            index().url,
                                            {},
                                            { replace: true },
                                        );
                                    }}
                                >
                                    Hapus semua filter
                                </Button>
                            ) : can.create ? (
                                <Button asChild>
                                    <Link href={create()}>Tambah orang</Link>
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <div
                        aria-busy={loading}
                        className={
                            loading
                                ? 'opacity-60 transition-opacity'
                                : undefined
                        }
                    >
                        <DataTable
                            columns={columns}
                            data={people.data}
                            getRowId={(row) => String(row.id)}
                            sorting={sorting}
                            activeRowId={
                                openId === null ? null : String(openId)
                            }
                            onRowOpen={(row) => setOpenId(row.id)}
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
                                                : 'terbaru',
                                        arah: first.desc ? 'desc' : 'asc',
                                    });
                                }
                            }}
                        />
                    </div>
                )}

                <Pager page={people} noun="orang" />
            </div>

            <PersonQuickView
                personId={openId}
                fallbackName={openRow?.name}
                onClose={() => setOpenId(null)}
            />
        </>
    );
}

PeopleIndex.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Orang', href: index() },
    ],
};
