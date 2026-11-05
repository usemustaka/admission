import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import {
    ArrowDown,
    ArrowUp,
    FlaskConical,
    ListChecks,
    Plus,
    TriangleAlert,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { RulesetConditions } from '@/components/admission/ruleset-conditions';
import { DataTable } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { OptionSelect } from '@/components/option-select';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { create, index, move, show } from '@/routes/admin/rulesets';
import type { RulesetOptions, RulesetSummary } from '@/types/admission';

type MatchTest = {
    ran: boolean;
    match: { id: number; name: string } | null;
    matches: { id: number; name: string; is_winner: boolean }[];
    filters: { gelombang: string; lembaga: string; gender: string };
};

const noSorting: SortingState = [];

export default function RulesetsIndex({
    rulesets,
    options,
    test,
    can,
}: {
    rulesets: RulesetSummary[];
    options: RulesetOptions;
    test: MatchTest;
    can: { manage: boolean };
}) {
    const [filters, setFilters] = useState(test.filters);

    function runTest() {
        router.get(
            index().url,
            { uji: 1, ...filters },
            { preserveScroll: true, preserveState: true, only: ['test'] },
        );
    }

    const columns = useMemo<ColumnDef<RulesetSummary>[]>(
        () => [
            {
                id: 'order',
                header: 'Urutan',
                enableSorting: false,
                meta: { className: 'w-20' },
                cell: ({ row }) => (
                    <span
                        className="flex size-9 items-center justify-center rounded-full bg-lime-100 text-sm font-bold text-lime-800 tabular-nums dark:bg-lime-400/15 dark:text-lime-300"
                        aria-label={`Urutan ${row.index + 1}`}
                    >
                        {row.index + 1}
                    </span>
                ),
            },
            {
                id: 'name',
                header: 'Aturan',
                enableSorting: false,
                cell: ({ row }) => (
                    <span className="grid justify-items-start gap-1.5">
                        <span className="flex flex-wrap items-center gap-2">
                            <Link
                                href={show(row.original.id)}
                                className="font-semibold hover:underline"
                            >
                                {row.original.name}
                            </Link>
                            {!row.original.is_active && (
                                <Badge variant="muted">Tidak aktif</Badge>
                            )}
                        </span>
                        {row.original.shadowed_by && (
                            <Badge
                                variant="warning"
                                className="h-auto max-w-full gap-1.5 py-1 whitespace-normal"
                            >
                                <TriangleAlert />
                                <span>
                                    Tidak pernah dipakai: tertutup oleh{' '}
                                    <Link
                                        href={show(row.original.shadowed_by.id)}
                                        className="underline"
                                    >
                                        {row.original.shadowed_by.name}
                                    </Link>
                                    . Naikkan urutan aturan ini di atasnya.
                                </span>
                            </Badge>
                        )}
                        <span className="md:hidden">
                            <RulesetConditions ruleset={row.original} />
                        </span>
                    </span>
                ),
            },
            {
                id: 'conditions',
                header: 'Berlaku untuk',
                enableSorting: false,
                meta: { className: 'hidden md:table-cell' },
                cell: ({ row }) => <RulesetConditions ruleset={row.original} />,
            },
            {
                id: 'registrants',
                header: 'Pendaftar',
                enableSorting: false,
                meta: { className: 'whitespace-nowrap tabular-nums' },
                cell: ({ row }) => row.original.registrants_count,
            },
            ...(can.manage
                ? [
                      {
                          id: 'move',
                          header: () => <span className="sr-only">Urutan</span>,
                          enableSorting: false,
                          meta: { className: 'text-right whitespace-nowrap' },
                          cell: ({ row }) => (
                              <span className="inline-flex gap-1">
                                  <Button
                                      variant="ghost"
                                      size="icon"
                                      aria-label={`Naikkan urutan ${row.original.name}`}
                                      disabled={row.index === 0}
                                      onClick={() =>
                                          router.patch(
                                              move(row.original.id).url,
                                              { direction: 'up' },
                                              { preserveScroll: true },
                                          )
                                      }
                                  >
                                      <ArrowUp />
                                  </Button>
                                  <Button
                                      variant="ghost"
                                      size="icon"
                                      aria-label={`Turunkan urutan ${row.original.name}`}
                                      disabled={
                                          row.index === rulesets.length - 1
                                      }
                                      onClick={() =>
                                          router.patch(
                                              move(row.original.id).url,
                                              { direction: 'down' },
                                              { preserveScroll: true },
                                          )
                                      }
                                  >
                                      <ArrowDown />
                                  </Button>
                              </span>
                          ),
                      } satisfies ColumnDef<RulesetSummary>,
                  ]
                : []),
        ],
        [can.manage, rulesets.length],
    );

    return (
        <>
            <Head title="Aturan pendaftaran" />
            <div className="grid gap-6">
                <PageHeader
                    icon={ListChecks}
                    title="Aturan pendaftaran"
                    description="Tiap aturan berisi tagihan, aspek ujian, dan jadwal ujian untuk pendaftar yang cocok dengan syaratnya. Aturan dicek dari atas ke bawah dan yang pertama cocok dipakai, jadi taruh aturan khusus di atas aturan umum."
                    actions={
                        can.manage && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus />
                                    Aturan baru
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="grid gap-6 @5xl:grid-cols-[minmax(0,1fr)_24rem] @5xl:items-start">
                    {rulesets.length === 0 ? (
                        <EmptyState
                            icon={ListChecks}
                            title="Belum ada aturan"
                            description="Tanpa aturan, pendaftar belum bisa dikelompokkan. Aturan dicek dari atas ke bawah dan yang pertama cocok dipakai, jadi taruh aturan yang khusus di atas aturan umum."
                            action={
                                can.manage && (
                                    <Button asChild>
                                        <Link href={create()}>
                                            <Plus />
                                            Aturan baru
                                        </Link>
                                    </Button>
                                )
                            }
                        />
                    ) : (
                        <DataTable
                            columns={columns}
                            data={rulesets}
                            getRowId={(row) => String(row.id)}
                            sorting={noSorting}
                            onSortingChange={() => {}}
                        />
                    )}

                    <SectionCard
                        title="Coba pencocokan"
                        description="Pilih data pendaftar contoh untuk melihat aturan mana yang akan dipakai."
                        icon={FlaskConical}
                    >
                        <div className="grid gap-5">
                            <FormField label="Gelombang">
                                {(control) => (
                                    <OptionSelect
                                        value={filters.gelombang}
                                        onValueChange={(value) =>
                                            setFilters({
                                                ...filters,
                                                gelombang: value,
                                            })
                                        }
                                        options={options.waves}
                                        anyLabel="Belum dipilih"
                                        {...control}
                                    />
                                )}
                            </FormField>
                            <FormField label="Lembaga">
                                {(control) => (
                                    <OptionSelect
                                        value={filters.lembaga}
                                        onValueChange={(value) =>
                                            setFilters({
                                                ...filters,
                                                lembaga: value,
                                            })
                                        }
                                        options={options.organizations}
                                        anyLabel="Belum dipilih"
                                        {...control}
                                    />
                                )}
                            </FormField>
                            <FormField label="Jenis kelamin">
                                {(control) => (
                                    <OptionSelect
                                        value={filters.gender}
                                        onValueChange={(value) =>
                                            setFilters({
                                                ...filters,
                                                gender: value,
                                            })
                                        }
                                        options={options.genders}
                                        anyLabel="Belum dipilih"
                                        {...control}
                                    />
                                )}
                            </FormField>
                            <Button variant="outline" onClick={runTest}>
                                Cek aturan yang dipakai
                            </Button>

                            {test.ran &&
                                (test.match ? (
                                    <Alert variant="success">
                                        <ListChecks />
                                        <AlertTitle>
                                            Aturan yang dipakai:{' '}
                                            {test.match.name}
                                        </AlertTitle>
                                        <AlertDescription>
                                            <Link
                                                href={show(test.match.id)}
                                                className="underline"
                                            >
                                                Lihat isinya
                                            </Link>
                                            {test.matches.length > 1 && (
                                                <span className="mt-2 grid gap-1">
                                                    <span>
                                                        Aturan lain yang juga
                                                        cocok, tapi kalah
                                                        urutan:
                                                    </span>
                                                    <span className="flex flex-wrap gap-1.5">
                                                        {test.matches
                                                            .filter(
                                                                (item) =>
                                                                    !item.is_winner,
                                                            )
                                                            .map((item) => (
                                                                <Badge
                                                                    key={
                                                                        item.id
                                                                    }
                                                                    variant="muted"
                                                                >
                                                                    {item.name}
                                                                </Badge>
                                                            ))}
                                                    </span>
                                                </span>
                                            )}
                                        </AlertDescription>
                                    </Alert>
                                ) : (
                                    <Alert variant="warning">
                                        <ListChecks />
                                        <AlertTitle>
                                            Belum ada aturan yang cocok
                                        </AlertTitle>
                                        <AlertDescription>
                                            Pendaftar dengan data ini belum
                                            punya aturan. Buat aturan umum atau
                                            yang sesuai.
                                        </AlertDescription>
                                    </Alert>
                                ))}
                        </div>
                    </SectionCard>
                </div>
            </div>
        </>
    );
}

RulesetsIndex.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Aturan pendaftaran', href: index() },
    ],
};
