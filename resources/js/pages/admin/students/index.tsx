import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import { GraduationCap, Pencil, SearchX, Undo2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { CancelPlacementDialog } from '@/components/academic/cancel-placement-dialog';
import { StudentCorrectionDialog } from '@/components/academic/student-correction-dialog';
import { DataTable } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { OptionSelect } from '@/components/option-select';
import { PageHeader } from '@/components/page-header';
import { Pager } from '@/components/pager';
import { PersonAvatar } from '@/components/person-avatar';
import { SearchInput } from '@/components/search-input';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import { show as showPerson } from '@/routes/admin/people';
import { show as showRegistrant } from '@/routes/admin/registrants';
import { index } from '@/routes/admin/students';
import type { StudentRow } from '@/types/academic';
import type { Paginated } from '@/types/people';

type Filters = {
    cari: string;
    lembaga: string;
    tahun: string;
    urut: 'nama' | 'nis' | 'ditempatkan';
    arah: 'asc' | 'desc';
};

const sortIds = { name: 'nama', nis: 'nis', placed_at: 'ditempatkan' } as const;

export default function StudentsIndex({
    students,
    filters,
    options,
    can,
}: {
    students: Paginated<StudentRow>;
    filters: Filters;
    options: {
        organizations: { value: number; label: string }[];
        years: { value: number; label: string; is_active: boolean }[];
    };
    can: { manage: boolean; viewPerson: boolean; viewRegistrant: boolean };
}) {
    const [term, setTerm] = useState(filters.cari);
    const [loading, setLoading] = useState(false);
    const [correcting, setCorrecting] = useState<StudentRow | null>(null);
    const [cancelling, setCancelling] = useState<StudentRow | null>(null);
    const isFirstRender = useRef(true);

    function visit(changes: Partial<Filters>) {
        const next = { ...filters, cari: term, ...changes };
        const defaultDirection = next.urut === 'ditempatkan' ? 'desc' : 'asc';

        router.get(
            index().url,
            {
                cari: next.cari || undefined,
                lembaga: next.lembaga || undefined,
                tahun: next.tahun || undefined,
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
            id:
                filters.urut === 'nis'
                    ? 'nis'
                    : filters.urut === 'ditempatkan'
                      ? 'placed_at'
                      : 'name',
            desc: filters.arah === 'desc',
        },
    ];

    const columns = useMemo<ColumnDef<StudentRow>[]>(
        () => [
            {
                id: 'name',
                header: 'Siswa',
                accessorFn: (row) => row.name,
                enableSorting: true,
                cell: ({ row }) => {
                    const student = row.original;
                    const identity = (
                        <>
                            <PersonAvatar
                                name={student.name}
                                src={student.photo_url}
                                size="sm"
                            />
                            <span className="grid">
                                <span className="font-semibold">
                                    {student.name}
                                </span>
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    {student.nik ?? 'Belum ada NIK'}
                                </span>
                            </span>
                        </>
                    );

                    return can.viewPerson ? (
                        <Link
                            href={showPerson(student.person_id)}
                            className="flex min-h-10 items-center gap-3 rounded-lg outline-none hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        >
                            {identity}
                        </Link>
                    ) : (
                        <span className="flex min-h-10 items-center gap-3">
                            {identity}
                        </span>
                    );
                },
            },
            {
                id: 'nis',
                header: 'NIS',
                accessorFn: (row) => row.nis,
                enableSorting: true,
                meta: { className: 'tabular-nums' },
                cell: ({ row }) => row.original.nis ?? '-',
            },
            {
                id: 'organization',
                header: 'Lembaga',
                enableSorting: false,
                meta: { className: 'hidden md:table-cell' },
                cell: ({ row }) => row.original.organization,
            },
            {
                id: 'year',
                header: 'Tahun ajaran',
                enableSorting: false,
                meta: { className: 'hidden whitespace-nowrap sm:table-cell' },
                cell: ({ row }) => row.original.year ?? '-',
            },
            {
                id: 'placed_at',
                header: 'Ditempatkan',
                accessorFn: (row) => row.placed_at,
                enableSorting: true,
                meta: { className: 'hidden whitespace-nowrap lg:table-cell' },
                cell: ({ row }) =>
                    row.original.placed_at
                        ? formatDate(row.original.placed_at)
                        : '-',
            },
            {
                id: 'actions',
                header: () => <span className="sr-only">Aksi</span>,
                enableSorting: false,
                meta: { className: 'text-right' },
                cell: ({ row }) => {
                    const student = row.original;

                    return (
                        <span className="flex flex-wrap justify-end gap-2">
                            {can.viewRegistrant &&
                                student.registrant_id !== null && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="max-md:h-10"
                                        asChild
                                    >
                                        <Link
                                            href={showRegistrant(
                                                student.registrant_id,
                                            )}
                                        >
                                            Pendaftaran
                                        </Link>
                                    </Button>
                                )}
                            {can.manage && (
                                <>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="max-md:h-10"
                                        onClick={() => setCorrecting(student)}
                                    >
                                        <Pencil />
                                        Koreksi
                                    </Button>
                                    {student.registrant_id !== null && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="max-md:h-10"
                                            onClick={() =>
                                                setCancelling(student)
                                            }
                                        >
                                            <Undo2 />
                                            Batalkan
                                        </Button>
                                    )}
                                </>
                            )}
                        </span>
                    );
                },
            },
        ],
        [can.manage, can.viewPerson, can.viewRegistrant],
    );

    const hasFilter =
        filters.cari !== '' || filters.lembaga !== '' || filters.tahun !== '';

    return (
        <>
            <Head title="Siswa" />
            <div className="grid gap-6">
                <PageHeader
                    icon={GraduationCap}
                    title="Siswa"
                    description="Anak yang sudah ditempatkan, lengkap dengan NIS, lembaga, dan tahun ajarannya."
                />

                <div className="grid gap-3 sm:grid-cols-[minmax(0,28rem)_14rem_12rem]">
                    <SearchInput
                        value={term}
                        onValueChange={setTerm}
                        placeholder="Cari nama, NIS, atau NIK…"
                        aria-label="Cari nama, NIS, atau NIK"
                    />
                    <OptionSelect
                        aria-label="Lembaga"
                        value={filters.lembaga}
                        onValueChange={(value) => visit({ lembaga: value })}
                        anyLabel="Semua lembaga"
                        options={options.organizations}
                    />
                    <OptionSelect
                        aria-label="Tahun ajaran"
                        value={filters.tahun}
                        onValueChange={(value) => visit({ tahun: value })}
                        anyLabel="Semua tahun"
                        options={options.years}
                    />
                </div>

                {students.data.length === 0 ? (
                    <EmptyState
                        icon={hasFilter ? SearchX : GraduationCap}
                        title={
                            hasFilter
                                ? 'Tidak ada siswa yang cocok'
                                : 'Belum ada siswa'
                        }
                        description={
                            hasFilter
                                ? 'Coba kata kunci lain atau longgarkan filternya.'
                                : 'Siswa muncul di sini setelah pendaftar yang sudah diumumkan ditempatkan.'
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
                            data={students.data}
                            getRowId={(row) => String(row.id)}
                            sorting={sorting}
                            onSortingChange={(updater) => {
                                const next =
                                    typeof updater === 'function'
                                        ? updater(sorting)
                                        : updater;
                                const first = next[0];

                                if (first && first.id in sortIds) {
                                    visit({
                                        urut: sortIds[
                                            first.id as keyof typeof sortIds
                                        ],
                                        arah: first.desc ? 'desc' : 'asc',
                                    });
                                }
                            }}
                        />
                    </div>
                )}

                <Pager page={students} noun="siswa" />
            </div>

            {correcting && (
                <StudentCorrectionDialog
                    student={correcting}
                    years={options.years}
                    onClose={() => setCorrecting(null)}
                />
            )}
            {cancelling && (
                <CancelPlacementDialog
                    studentId={cancelling.id}
                    childName={cancelling.name}
                    onClose={() => setCancelling(null)}
                />
            )}
        </>
    );
}

StudentsIndex.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Siswa', href: index() },
    ],
};
