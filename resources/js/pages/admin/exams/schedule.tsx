import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarClock, Info, Lock, UsersRound } from 'lucide-react';
import { useState } from 'react';
import { ExamScheduleEditor } from '@/components/admission/exam-schedule-editor';
import { EmptyState } from '@/components/empty-state';
import { OptionSelect } from '@/components/option-select';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { edit } from '@/routes/admin/exams/schedule';
import type {
    ExamDayRow,
    ExamScheduleScope,
    ExamScheduleSummary,
} from '@/types/admission';

type Option = { value: number; label: string };

function scopeQuery(organizationId: number | null, waveId: number | null) {
    return {
        ...(organizationId !== null && { lembaga: organizationId }),
        ...(waveId !== null && { gelombang: waveId }),
    };
}

function count(total: number, unit: string): string {
    return `${total} ${unit}`;
}

export default function ExamSchedule({
    scope,
    days,
    schedules,
    audience,
    options,
}: {
    scope: ExamScheduleScope;
    days: ExamDayRow[];
    schedules: ExamScheduleSummary[];
    audience: { label: string; registrants: number };
    options: { organizations: Option[]; waves: Option[] };
}) {
    const { abilities } = usePage().props.auth;
    const [loading, setLoading] = useState(false);

    if (!abilities.includes('admission::exam.manage')) {
        return (
            <>
                <Head title="Jadwal ujian" />
                <EmptyState
                    icon={Lock}
                    title="Kamu belum punya izin mengatur jadwal ujian"
                    description="Minta admin menambahkan izin Mengatur jadwal ujian ke perananmu."
                />
            </>
        );
    }

    function visit(organizationId: number | null, waveId: number | null) {
        router.get(edit().url, scopeQuery(organizationId, waveId), {
            preserveState: true,
            preserveScroll: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    }

    const scopeKey = `${scope.organization_id ?? 'all'}-${scope.wave_id ?? 'all'}`;
    const isBroad = scope.organization_id === null || scope.wave_id === null;

    return (
        <>
            <Head title="Jadwal ujian" />
            <div className="grid gap-6">
                <PageHeader
                    icon={CalendarClock}
                    title="Jadwal ujian"
                    description="Jadwal dipakai bersama oleh semua pendaftar di lembaga dan gelombang yang sama, apa pun aturan pendaftarannya."
                />

                <SectionCard
                    title="Untuk siapa jadwal ini"
                    description="Pilih lembaga dan gelombang. Kosongkan salah satunya untuk berlaku ke semua."
                >
                    <div className="grid gap-5">
                        <div className="grid gap-3 @2xl:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="schedule-organization">
                                    Lembaga
                                </Label>
                                <OptionSelect
                                    id="schedule-organization"
                                    value={
                                        scope.organization_id === null
                                            ? ''
                                            : String(scope.organization_id)
                                    }
                                    onValueChange={(value) =>
                                        visit(
                                            value === '' ? null : Number(value),
                                            scope.wave_id,
                                        )
                                    }
                                    anyLabel="Semua lembaga"
                                    options={options.organizations}
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="schedule-wave">Gelombang</Label>
                                <OptionSelect
                                    id="schedule-wave"
                                    value={
                                        scope.wave_id === null
                                            ? ''
                                            : String(scope.wave_id)
                                    }
                                    onValueChange={(value) =>
                                        visit(
                                            scope.organization_id,
                                            value === '' ? null : Number(value),
                                        )
                                    }
                                    anyLabel="Semua gelombang"
                                    options={options.waves}
                                />
                            </div>
                        </div>

                        <div className="grid gap-2 text-sm">
                            <p className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <UsersRound className="size-4 text-muted-foreground" />
                                <span>
                                    Berlaku untuk{' '}
                                    <strong className="font-semibold">
                                        {audience.label}
                                    </strong>
                                </span>
                                <span className="text-muted-foreground tabular-nums">
                                    · {count(audience.registrants, 'pendaftar')}
                                </span>
                            </p>
                            {isBroad && (
                                <p className="flex gap-2 text-muted-foreground">
                                    <Info className="mt-0.5 size-4 shrink-0" />
                                    <span>
                                        Jadwal yang memakai &ldquo;Semua&rdquo;
                                        berlaku untuk semua pendaftar yang tidak
                                        punya jadwal lebih khusus. Sesi dari
                                        semua jadwal yang cocok ditawarkan
                                        bersamaan, jadi pakai satu jadwal luas
                                        atau beberapa jadwal khusus, dan hindari
                                        sesi yang sama di dua jadwal.
                                    </span>
                                </p>
                            )}
                        </div>

                        {schedules.length > 0 && (
                            <nav
                                aria-label="Jadwal yang sudah ada"
                                className="grid gap-2"
                            >
                                <p className="text-sm font-semibold">
                                    Jadwal yang sudah ada
                                </p>
                                <ul className="flex flex-wrap gap-2">
                                    {schedules.map((schedule) => {
                                        const selected =
                                            schedule.organization_id ===
                                                scope.organization_id &&
                                            schedule.wave_id === scope.wave_id;

                                        return (
                                            <li
                                                key={`${schedule.organization_id ?? 'all'}-${schedule.wave_id ?? 'all'}`}
                                            >
                                                <Link
                                                    href={edit({
                                                        query: scopeQuery(
                                                            schedule.organization_id,
                                                            schedule.wave_id,
                                                        ),
                                                    })}
                                                    preserveScroll
                                                    aria-current={
                                                        selected
                                                            ? 'page'
                                                            : undefined
                                                    }
                                                    className={cn(
                                                        'inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold whitespace-nowrap transition-colors outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                                                        selected
                                                            ? 'border-neutral-900 bg-neutral-900 text-white dark:border-neutral-100 dark:bg-neutral-100 dark:text-neutral-900'
                                                            : 'border-neutral-300 hover:border-foreground dark:border-neutral-600',
                                                    )}
                                                >
                                                    {schedule.label}
                                                    <span
                                                        className={cn(
                                                            'font-medium tabular-nums',
                                                            selected
                                                                ? 'text-neutral-400 dark:text-neutral-500'
                                                                : 'text-muted-foreground',
                                                        )}
                                                    >
                                                        {count(
                                                            schedule.days,
                                                            'tanggal',
                                                        )}{' '}
                                                        ·{' '}
                                                        {count(
                                                            schedule.sessions,
                                                            'sesi',
                                                        )}
                                                    </span>
                                                </Link>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </nav>
                        )}
                    </div>
                </SectionCard>

                {schedules.length === 0 && (
                    <EmptyState
                        icon={CalendarClock}
                        title="Belum ada jadwal ujian"
                        description="Pilih lembaga dan gelombang di atas, lalu tambah tanggal."
                    />
                )}

                <div
                    aria-busy={loading}
                    className={cn(loading && 'opacity-60 transition-opacity')}
                >
                    <SectionCard
                        title="Tanggal dan sesi"
                        description="Kuota membatasi jumlah pendaftar yang bisa memilih satu sesi."
                        icon={CalendarClock}
                    >
                        <ExamScheduleEditor
                            key={`${scopeKey}:${JSON.stringify(days)}`}
                            organizationId={scope.organization_id}
                            waveId={scope.wave_id}
                            initial={days}
                            canEdit
                        />
                    </SectionCard>
                </div>
            </div>
        </>
    );
}

ExamSchedule.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Jadwal ujian', href: edit() },
    ],
};
