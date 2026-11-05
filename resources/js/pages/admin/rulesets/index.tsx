import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    FlaskConical,
    ListChecks,
    Plus,
} from 'lucide-react';
import { useState } from 'react';
import { RulesetConditions } from '@/components/admission/ruleset-conditions';
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
    filters: { gelombang: string; lembaga: string; gender: string };
};

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

    return (
        <>
            <Head title="Aturan pendaftaran" />
            <div className="mx-auto grid w-full max-w-4xl gap-6 p-4 md:p-6">
                <PageHeader
                    icon={ListChecks}
                    title="Aturan pendaftaran"
                    description="Tiap aturan berisi tagihan, aspek ujian, dan jadwal ujian untuk pendaftar yang cocok dengan syaratnya. Aturan paling atas dicek lebih dulu."
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

                {rulesets.length === 0 ? (
                    <EmptyState
                        icon={ListChecks}
                        title="Belum ada aturan"
                        description="Tanpa aturan, pendaftar belum bisa dikelompokkan. Buat satu aturan umum dulu, lalu tambahkan yang lebih khusus."
                    />
                ) : (
                    <ol className="grid gap-3">
                        {rulesets.map((ruleset, position) => (
                            <li
                                key={ruleset.id}
                                className="flex flex-wrap items-center gap-4 rounded-2xl border bg-card p-4 shadow-xs"
                            >
                                <span
                                    className="flex size-9 shrink-0 items-center justify-center rounded-full bg-sage-100 text-sm font-bold text-sage-700 tabular-nums dark:bg-secondary dark:text-primary"
                                    aria-label={`Urutan ${position + 1}`}
                                >
                                    {position + 1}
                                </span>
                                <div className="grid min-w-0 flex-1 gap-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Link
                                            href={show(ruleset.id)}
                                            className="text-lg font-semibold tracking-tight hover:text-primary"
                                        >
                                            {ruleset.name}
                                        </Link>
                                        {!ruleset.is_active && (
                                            <Badge variant="muted">
                                                Tidak aktif
                                            </Badge>
                                        )}
                                    </div>
                                    <RulesetConditions ruleset={ruleset} />
                                </div>
                                {can.manage && (
                                    <div className="flex gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Naikkan urutan"
                                            disabled={position === 0}
                                            onClick={() =>
                                                router.patch(
                                                    move(ruleset.id).url,
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
                                            aria-label="Turunkan urutan"
                                            disabled={
                                                position === rulesets.length - 1
                                            }
                                            onClick={() =>
                                                router.patch(
                                                    move(ruleset.id).url,
                                                    { direction: 'down' },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <ArrowDown />
                                        </Button>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ol>
                )}

                <SectionCard
                    title="Coba pencocokan"
                    description="Pilih data pendaftar contoh untuk melihat aturan mana yang akan dipakai."
                    icon={FlaskConical}
                >
                    <div className="grid gap-5">
                        <div className="grid gap-4 sm:grid-cols-3">
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
                        </div>
                        <div>
                            <Button variant="outline" onClick={runTest}>
                                Cek aturan yang dipakai
                            </Button>
                        </div>

                        {test.ran &&
                            (test.match ? (
                                <Alert variant="success">
                                    <ListChecks />
                                    <AlertTitle>
                                        Aturan yang dipakai: {test.match.name}
                                    </AlertTitle>
                                    <AlertDescription>
                                        <Link
                                            href={show(test.match.id)}
                                            className="underline"
                                        >
                                            Lihat isinya
                                        </Link>
                                    </AlertDescription>
                                </Alert>
                            ) : (
                                <Alert variant="warning">
                                    <ListChecks />
                                    <AlertTitle>
                                        Belum ada aturan yang cocok
                                    </AlertTitle>
                                    <AlertDescription>
                                        Pendaftar dengan data ini belum punya
                                        aturan. Buat aturan umum atau yang
                                        sesuai.
                                    </AlertDescription>
                                </Alert>
                            ))}
                    </div>
                </SectionCard>
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
