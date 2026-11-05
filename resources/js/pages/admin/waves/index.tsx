import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import { CalendarRange, Pencil, Plus, SearchX } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { WaveFormDialog } from '@/components/admission/wave-form-dialog';
import { DataTable } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FilterPills } from '@/components/filter-pills';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Switch } from '@/components/ui/switch';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import { index as registrantsIndex } from '@/routes/admin/registrants';
import { index, update } from '@/routes/admin/waves';
import type { Wave, WaveState } from '@/types/admission';

type StateFilter = 'semua' | WaveState;

const stateBadge = {
    upcoming: 'info',
    ongoing: 'success',
    ended: 'muted',
} as const;

const noSorting: SortingState = [];

function period(wave: Wave): string {
    if (!wave.starts_on && !wave.ends_on) {
        return 'Tanpa batas tanggal';
    }

    return [
        wave.starts_on ? formatDate(wave.starts_on) : 'Sejak awal',
        wave.ends_on ? formatDate(wave.ends_on) : 'tanpa batas akhir',
    ].join(' s.d. ');
}

function days(count: number): string {
    return count === 1 ? '1 hari' : `${count} hari`;
}

/**
 * One line that says where the wave is in time: how long until it starts, ends, or since it ended.
 */
function timeHint(wave: Wave): string {
    if (wave.state === 'upcoming' && wave.days_until_start !== null) {
        return wave.days_until_start === 1
            ? 'Mulai besok'
            : `Mulai ${days(wave.days_until_start)} lagi`;
    }

    if (wave.state === 'ended' && wave.days_since_end !== null) {
        return wave.days_since_end === 1
            ? 'Selesai kemarin'
            : `Selesai ${days(wave.days_since_end)} lalu`;
    }

    if (wave.days_left !== null) {
        return wave.days_left === 0
            ? 'Hari terakhir'
            : `Sisa ${days(wave.days_left)}`;
    }

    return 'Tanpa batas akhir';
}

export default function WavesIndex({
    waves,
    can,
}: {
    waves: Wave[];
    can: { manage: boolean };
}) {
    const [stateFilter, setStateFilter] = useState<StateFilter>('semua');
    const [togglingId, setTogglingId] = useState<number | null>(null);

    function toggle(wave: Wave, isOpen: boolean) {
        const failed = (message?: string): void => {
            toast.error(
                message ??
                    `Status ${wave.name} belum bisa diubah. Coba lagi ya.`,
            );
        };

        router.put(
            update(wave.id).url,
            {
                name: wave.name,
                starts_on: wave.starts_on ?? '',
                ends_on: wave.ends_on ?? '',
                is_open: isOpen,
            },
            {
                preserveScroll: true,
                onStart: () => setTogglingId(wave.id),
                onFinish: () => setTogglingId(null),
                onError: (errors) => failed(Object.values(errors)[0]),
                onHttpException: () => failed(),
                onNetworkError: () => failed(),
            },
        );
    }

    const columns = useMemo<ColumnDef<Wave>[]>(
        () => [
            {
                id: 'name',
                header: 'Gelombang',
                enableSorting: false,
                cell: ({ row }) => {
                    const wave = row.original;

                    return (
                        <span className="grid justify-items-start gap-1">
                            <span className="font-semibold">{wave.name}</span>
                            {wave.is_accepting ? (
                                <Badge variant="success">
                                    Menerima pendaftar
                                </Badge>
                            ) : wave.is_open ? (
                                <Badge variant="warning">
                                    Terbuka, di luar tanggalnya
                                </Badge>
                            ) : (
                                <Badge variant="muted">Ditutup</Badge>
                            )}
                        </span>
                    );
                },
            },
            {
                id: 'period',
                header: 'Jadwal',
                enableSorting: false,
                meta: { className: 'min-w-56' },
                cell: ({ row }) => {
                    const wave = row.original;

                    return (
                        <span className="grid gap-1.5">
                            <span className="flex flex-wrap items-center gap-2">
                                <Badge variant={stateBadge[wave.state]}>
                                    {wave.state_label}
                                </Badge>
                                <span className="text-sm text-muted-foreground">
                                    {timeHint(wave)}
                                </span>
                            </span>
                            {wave.progress !== null && (
                                <Progress
                                    value={wave.progress}
                                    className="h-1.5"
                                    aria-label={`Waktu berjalan ${wave.progress}%`}
                                />
                            )}
                            <span className="text-xs text-muted-foreground">
                                {period(wave)}
                            </span>
                        </span>
                    );
                },
            },
            {
                id: 'registrants',
                header: 'Pendaftar',
                enableSorting: false,
                cell: ({ row }) => {
                    const { registrants, id } = row.original;

                    if (registrants.total === 0) {
                        return (
                            <span className="text-sm text-muted-foreground">
                                Belum ada
                            </span>
                        );
                    }

                    return (
                        <span className="grid gap-0.5">
                            <Link
                                href={registrantsIndex({
                                    query: { gelombang: id },
                                })}
                                className="w-fit font-semibold tabular-nums hover:underline"
                            >
                                {registrants.total} pendaftar
                            </Link>
                            <span className="text-xs text-muted-foreground">
                                {registrants.by_status
                                    .map(
                                        (item) =>
                                            `${item.count} ${item.label.toLowerCase()}`,
                                    )
                                    .join(' · ')}
                            </span>
                        </span>
                    );
                },
            },
            {
                id: 'rulesets',
                header: 'Aturan',
                enableSorting: false,
                meta: { className: 'hidden tabular-nums lg:table-cell' },
                cell: ({ row }) => row.original.rulesets_count,
            },
            ...(can.manage
                ? [
                      {
                          id: 'open',
                          header: 'Buka',
                          enableSorting: false,
                          cell: ({ row }) => (
                              <Switch
                                  checked={row.original.is_open}
                                  disabled={togglingId === row.original.id}
                                  onCheckedChange={(checked) =>
                                      toggle(row.original, checked)
                                  }
                                  aria-label={`Buka ${row.original.name}`}
                              />
                          ),
                      } satisfies ColumnDef<Wave>,
                      {
                          id: 'actions',
                          header: () => <span className="sr-only">Aksi</span>,
                          enableSorting: false,
                          meta: { className: 'text-right' },
                          cell: ({ row }) => (
                              <WaveFormDialog
                                  wave={row.original}
                                  trigger={
                                      <Button
                                          variant="outline"
                                          size="sm"
                                          className="max-md:h-10"
                                      >
                                          <Pencil />
                                          Ubah
                                      </Button>
                                  }
                              />
                          ),
                      } satisfies ColumnDef<Wave>,
                  ]
                : []),
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [can.manage, togglingId],
    );

    const countOf = (state: WaveState) =>
        waves.filter((wave) => wave.state === state).length;
    const visible =
        stateFilter === 'semua'
            ? waves
            : waves.filter((wave) => wave.state === stateFilter);

    return (
        <>
            <Head title="Gelombang" />
            <div className="grid gap-6">
                <PageHeader
                    icon={CalendarRange}
                    title="Gelombang pendaftaran"
                    description="Kelompok pendaftar. Pendaftaran dibuka sepanjang tahun, jadi gelombang tidak terikat tahun ajaran."
                    actions={
                        can.manage && (
                            <WaveFormDialog
                                trigger={
                                    <Button>
                                        <Plus />
                                        Gelombang baru
                                    </Button>
                                }
                            />
                        )
                    }
                />

                {waves.length === 0 ? (
                    <EmptyState
                        icon={CalendarRange}
                        title="Belum ada gelombang"
                        description="Buat gelombang pertama supaya orang tua bisa mulai mendaftarkan anak."
                        action={
                            can.manage && (
                                <WaveFormDialog
                                    trigger={
                                        <Button>
                                            <Plus />
                                            Gelombang baru
                                        </Button>
                                    }
                                />
                            )
                        }
                    />
                ) : (
                    <>
                        <FilterPills<StateFilter>
                            label="Status gelombang"
                            value={stateFilter}
                            onChange={setStateFilter}
                            items={[
                                {
                                    value: 'semua',
                                    label: 'Semua',
                                    count: waves.length,
                                },
                                {
                                    value: 'ongoing',
                                    label: 'Berlangsung',
                                    count: countOf('ongoing'),
                                },
                                {
                                    value: 'upcoming',
                                    label: 'Akan datang',
                                    count: countOf('upcoming'),
                                },
                                {
                                    value: 'ended',
                                    label: 'Selesai',
                                    count: countOf('ended'),
                                },
                            ]}
                        />

                        {visible.length === 0 ? (
                            <EmptyState
                                icon={SearchX}
                                title="Tidak ada gelombang di sini"
                                description="Belum ada gelombang dengan status ini."
                                action={
                                    <Button
                                        variant="outline"
                                        onClick={() => setStateFilter('semua')}
                                    >
                                        Lihat semua gelombang
                                    </Button>
                                }
                            />
                        ) : (
                            <DataTable
                                columns={columns}
                                data={visible}
                                getRowId={(row) => String(row.id)}
                                sorting={noSorting}
                                onSortingChange={() => {}}
                            />
                        )}
                    </>
                )}
            </div>
        </>
    );
}

WavesIndex.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Gelombang', href: index() },
    ],
};
