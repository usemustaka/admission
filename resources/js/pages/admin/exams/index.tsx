import { Head, Link, router } from '@inertiajs/react';
import { ClipboardCheck, ListChecks, SearchX, UsersRound } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { SeatCount, formatExamDate } from '@/components/exams/session-summary';
import { FilterPills } from '@/components/filter-pills';
import { OptionSelect } from '@/components/option-select';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import { index } from '@/routes/admin/exams';
import { show as showRoster } from '@/routes/admin/exams/sessions';
import { edit as editScores } from '@/routes/admin/exams/sessions/scores';
import type { ExamFilters, ExamSessionRow } from '@/types/exam';

type Mode = 'mendatang' | 'semua' | 'perlu' | 'tanggal';

function modeOf(filters: ExamFilters): Mode {
    if (filters.perlu_dinilai) {
        return 'perlu';
    }

    if (filters.tanggal === 'semua') {
        return 'semua';
    }

    return filters.tanggal === '' ? 'mendatang' : 'tanggal';
}

export default function ExamsIndex({
    days,
    summary,
    filters,
    options,
    today,
    can,
}: {
    days: { date: string; sessions: ExamSessionRow[] }[];
    summary: { sessions: number; participants: number; needs_grading: number };
    filters: ExamFilters;
    options: { organizations: { value: number; label: string }[] };
    today: string;
    can: { manage: boolean };
}) {
    const [loading, setLoading] = useState(false);
    const mode = modeOf(filters);

    function visit(changes: Partial<ExamFilters>) {
        const next = { ...filters, ...changes };

        router.get(
            index().url,
            {
                tanggal: next.tanggal || undefined,
                sampai:
                    next.tanggal && next.tanggal !== 'semua'
                        ? next.sampai || undefined
                        : undefined,
                lembaga: next.lembaga || undefined,
                perlu_dinilai: next.perlu_dinilai ? 1 : undefined,
            },
            {
                preserveState: true,
                replace: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );
    }

    function pickMode(value: Mode) {
        visit({
            tanggal: value === 'semua' ? 'semua' : '',
            sampai: '',
            perlu_dinilai: value === 'perlu',
        });
    }

    const hasFilter = mode !== 'mendatang' || filters.lembaga !== '';
    const specificDate = filters.tanggal !== 'semua' ? filters.tanggal : '';

    return (
        <>
            <Head title="Ujian" />
            <div className="grid gap-6">
                <PageHeader
                    icon={ClipboardCheck}
                    title="Ujian"
                    description="Jadwal sesi ujian dari semua aturan pendaftaran, lengkap dengan peserta, daftar hadir, dan nilai yang belum masuk."
                />

                <div className="grid gap-3 @3xl:grid-cols-3">
                    <StatCard
                        label="Sesi"
                        value={summary.sessions}
                        icon={ClipboardCheck}
                    />
                    <StatCard
                        label="Peserta"
                        value={summary.participants}
                        icon={UsersRound}
                    />
                    <StatCard
                        label="Sesi perlu dinilai"
                        value={summary.needs_grading}
                        icon={ListChecks}
                        tone={summary.needs_grading > 0 ? 'warning' : 'default'}
                        hint={
                            summary.needs_grading > 0
                                ? 'Sudah lewat, nilai belum lengkap'
                                : undefined
                        }
                    />
                </div>

                <div className="grid gap-3">
                    <FilterPills<Mode>
                        label="Rentang jadwal"
                        value={mode}
                        onChange={pickMode}
                        items={[
                            { value: 'mendatang', label: 'Mendatang' },
                            { value: 'perlu', label: 'Perlu dinilai' },
                            { value: 'semua', label: 'Semua' },
                        ]}
                    />
                    <div className="grid gap-3 sm:grid-cols-[10rem_10rem_minmax(0,16rem)]">
                        <div className="grid gap-1.5">
                            <Label htmlFor="exam-from">Dari tanggal</Label>
                            <Input
                                id="exam-from"
                                type="date"
                                value={specificDate}
                                onChange={(event) =>
                                    visit({
                                        tanggal: event.target.value,
                                        perlu_dinilai: false,
                                    })
                                }
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="exam-to">Sampai tanggal</Label>
                            <Input
                                id="exam-to"
                                type="date"
                                value={filters.sampai}
                                min={specificDate || undefined}
                                disabled={specificDate === ''}
                                onChange={(event) =>
                                    visit({ sampai: event.target.value })
                                }
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="exam-organization">Lembaga</Label>
                            <OptionSelect
                                id="exam-organization"
                                value={filters.lembaga}
                                onValueChange={(value) =>
                                    visit({ lembaga: value })
                                }
                                anyLabel="Semua lembaga"
                                options={options.organizations}
                            />
                        </div>
                    </div>
                </div>

                {days.length === 0 ? (
                    <EmptyState
                        icon={hasFilter ? SearchX : ClipboardCheck}
                        title={
                            hasFilter
                                ? 'Tidak ada sesi yang cocok'
                                : 'Belum ada sesi ujian mendatang'
                        }
                        description={
                            hasFilter
                                ? 'Coba rentang tanggal lain atau longgarkan filternya.'
                                : 'Jadwal ujian diatur di aturan pendaftaran. Pilih "Semua" untuk melihat sesi yang sudah lewat.'
                        }
                        action={
                            hasFilter ? (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.get(
                                            index().url,
                                            {},
                                            { replace: true },
                                        )
                                    }
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
                                ? 'grid gap-4 opacity-60 transition-opacity'
                                : 'grid gap-4'
                        }
                    >
                        {days.map((day) => (
                            <SectionCard
                                key={day.date}
                                title={formatExamDate(day.date)}
                                description={`${day.sessions.length} sesi · ${day.sessions.reduce((sum, session) => sum + session.taken, 0)} peserta`}
                                actions={
                                    day.date === today ? (
                                        <Badge variant="default">
                                            Hari ini
                                        </Badge>
                                    ) : undefined
                                }
                                contentClassName="-mx-2 @2xl:mx-0"
                            >
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Sesi</TableHead>
                                            <TableHead className="hidden md:table-cell">
                                                Aturan
                                            </TableHead>
                                            <TableHead>Terisi</TableHead>
                                            <TableHead>Nilai</TableHead>
                                            <TableHead>
                                                <span className="sr-only">
                                                    Aksi
                                                </span>
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {day.sessions.map((session) => (
                                            <TableRow key={session.id}>
                                                <TableCell className="font-semibold">
                                                    <Link
                                                        href={showRoster(
                                                            session.id,
                                                        )}
                                                        className="rounded-lg outline-none hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                                    >
                                                        {session.label}
                                                    </Link>
                                                </TableCell>
                                                <TableCell className="hidden md:table-cell">
                                                    <span className="grid">
                                                        <span>
                                                            {session.ruleset}
                                                        </span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {session.organization ??
                                                                'Semua lembaga'}
                                                        </span>
                                                    </span>
                                                </TableCell>
                                                <TableCell>
                                                    <SeatCount
                                                        taken={session.taken}
                                                        quota={session.quota}
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <span className="flex flex-wrap gap-1.5">
                                                        {session.missing >
                                                            0 && (
                                                            <Badge
                                                                variant={
                                                                    session.needs_grading
                                                                        ? 'warning'
                                                                        : 'muted'
                                                                }
                                                            >
                                                                {
                                                                    session.missing
                                                                }{' '}
                                                                belum dinilai
                                                            </Badge>
                                                        )}
                                                        {session.passed > 0 && (
                                                            <Badge variant="success">
                                                                {session.passed}{' '}
                                                                tuntas
                                                            </Badge>
                                                        )}
                                                        {session.failed > 0 && (
                                                            <Badge variant="danger">
                                                                {session.failed}{' '}
                                                                belum tuntas
                                                            </Badge>
                                                        )}
                                                        {session.taken ===
                                                            0 && (
                                                            <span className="text-muted-foreground">
                                                                -
                                                            </span>
                                                        )}
                                                    </span>
                                                </TableCell>
                                                <TableCell>
                                                    <span className="flex flex-wrap justify-end gap-2">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="max-md:h-10"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={showRoster(
                                                                    session.id,
                                                                )}
                                                            >
                                                                Daftar hadir
                                                            </Link>
                                                        </Button>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            className="max-md:h-10"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={editScores(
                                                                    session.id,
                                                                )}
                                                            >
                                                                {can.manage
                                                                    ? 'Input nilai'
                                                                    : 'Lihat nilai'}
                                                            </Link>
                                                        </Button>
                                                    </span>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </SectionCard>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

ExamsIndex.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Ujian', href: index() },
    ],
};
