import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import { ChevronRight, Plus, SearchX, UserCog } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { DataTable } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FilterPills } from '@/components/filter-pills';
import { PageHeader } from '@/components/page-header';
import { Pager } from '@/components/pager';
import { PersonAvatar } from '@/components/person-avatar';
import { SearchInput } from '@/components/search-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { create, index, show } from '@/routes/admin/users';
import type { StaffRow } from '@/types/access';
import type { Paginated } from '@/types/people';

type Filters = {
    cari: string;
    peran: string;
    status: '' | 'aktif' | 'nonaktif';
};

type Counts = {
    roles: Record<string, number>;
    status: Record<string, number>;
};

const ALL = 'semua';
const noSorting: SortingState = [];

export default function UsersIndex({
    users,
    filters,
    options,
    counts,
}: {
    users: Paginated<StaffRow>;
    filters: Filters;
    options: { roles: string[] };
    counts: Counts;
}) {
    const [term, setTerm] = useState(filters.cari);
    const [loading, setLoading] = useState(false);
    const isFirstRender = useRef(true);

    function visit(changes: Partial<Filters>) {
        const next = { ...filters, cari: term, ...changes };

        router.get(
            index().url,
            {
                cari: next.cari || undefined,
                peran: next.peran || undefined,
                status: next.status || undefined,
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

    const columns = useMemo<ColumnDef<StaffRow>[]>(
        () => [
            {
                id: 'name',
                header: 'Nama',
                enableSorting: false,
                cell: ({ row }) => (
                    <span className="flex items-center gap-3">
                        <PersonAvatar
                            name={row.original.name}
                            src={row.original.photo_url}
                            size="sm"
                        />
                        <span className="grid">
                            <span className="font-semibold">
                                {row.original.name}
                                {row.original.is_self && (
                                    <span className="ml-1.5 text-xs font-medium text-muted-foreground">
                                        (kamu)
                                    </span>
                                )}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {row.original.email}
                            </span>
                        </span>
                    </span>
                ),
            },
            {
                id: 'roles',
                header: 'Peran',
                enableSorting: false,
                cell: ({ row }) => (
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
                id: 'two_factor',
                header: '2FA',
                enableSorting: false,
                meta: { className: 'hidden md:table-cell' },
                cell: ({ row }) =>
                    row.original.has_two_factor ? (
                        <Badge variant="success">Aktif</Badge>
                    ) : (
                        <Badge variant="muted">Belum</Badge>
                    ),
            },
            {
                id: 'email_verified',
                header: 'Email',
                enableSorting: false,
                meta: { className: 'hidden lg:table-cell' },
                cell: ({ row }) =>
                    row.original.email_verified ? (
                        <Badge variant="success">Terverifikasi</Badge>
                    ) : (
                        <Badge variant="warning">Belum verifikasi</Badge>
                    ),
            },
            {
                id: 'status',
                header: 'Status',
                enableSorting: false,
                cell: ({ row }) =>
                    row.original.is_active ? (
                        <Badge variant="success">Aktif</Badge>
                    ) : (
                        <Badge variant="danger">Nonaktif</Badge>
                    ),
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
                            aria-label={`Buka ${row.original.name}`}
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
        filters.cari !== '' || filters.peran !== '' || filters.status !== '';

    return (
        <>
            <Head title="Staf" />
            <div className="grid gap-6">
                <PageHeader
                    icon={UserCog}
                    title="Staf"
                    description="Akun yang bisa masuk ke panel admin. Atur peran, nonaktifkan akun, atau undang staf baru."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus />
                                Tambah staf
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-3">
                    <FilterPills
                        label="Peran"
                        value={filters.peran || ALL}
                        onChange={(value) =>
                            visit({ peran: value === ALL ? '' : value })
                        }
                        items={[
                            {
                                value: ALL,
                                label: 'Semua',
                                count: counts.roles[''],
                            },
                            ...options.roles.map((role) => ({
                                value: role,
                                label: role,
                                count: counts.roles[role],
                            })),
                        ]}
                    />
                    <FilterPills
                        label="Status"
                        value={filters.status || ALL}
                        onChange={(value) =>
                            visit({
                                status:
                                    value === ALL
                                        ? ''
                                        : (value as Filters['status']),
                            })
                        }
                        items={[
                            {
                                value: ALL,
                                label: 'Semua status',
                                count: counts.status[''],
                            },
                            {
                                value: 'aktif',
                                label: 'Aktif',
                                count: counts.status.aktif,
                            },
                            {
                                value: 'nonaktif',
                                label: 'Nonaktif',
                                count: counts.status.nonaktif,
                            },
                        ]}
                    />
                    <div className="max-w-md">
                        <SearchInput
                            value={term}
                            onValueChange={setTerm}
                            placeholder="Cari nama atau email…"
                            aria-label="Cari nama atau email"
                        />
                    </div>
                </div>

                {users.data.length === 0 ? (
                    <EmptyState
                        icon={hasFilter ? SearchX : UserCog}
                        title={
                            hasFilter
                                ? 'Tidak ada yang cocok'
                                : 'Belum ada staf'
                        }
                        description={
                            hasFilter
                                ? 'Coba kata kunci lain atau longgarkan filternya.'
                                : 'Undang staf pertama lewat tombol Tambah staf.'
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
                            data={users.data}
                            getRowId={(row) => String(row.id)}
                            sorting={noSorting}
                            onSortingChange={() => {}}
                            onRowOpen={(row) => router.visit(show(row.id))}
                        />
                    </div>
                )}

                <Pager page={users} noun="staf" />
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Staf', href: index() },
    ],
};
