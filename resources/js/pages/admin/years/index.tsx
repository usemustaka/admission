import { Head, router } from '@inertiajs/react';
import { CalendarDays, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { PeriodFormDialog } from '@/components/academic/period-form-dialog';
import { YearFormDialog } from '@/components/academic/year-form-dialog';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import { activate, destroy, index } from '@/routes/admin/years';
import {
    destroy as destroyPeriod,
    update as updatePeriod,
} from '@/routes/admin/years/periods';
import type { PeriodRow, YearRow, YearSuggestion } from '@/types/academic';

type FormTarget = { year?: YearRow };
type PeriodTarget = { year: YearRow; period?: PeriodRow };

export default function YearsIndex({
    years,
    suggestion,
}: {
    years: YearRow[];
    suggestion: YearSuggestion;
}) {
    const [yearForm, setYearForm] = useState<FormTarget | null>(null);
    const [periodForm, setPeriodForm] = useState<PeriodTarget | null>(null);
    const [deletingYear, setDeletingYear] = useState<YearRow | null>(null);
    const [deletingPeriod, setDeletingPeriod] = useState<PeriodTarget | null>(
        null,
    );
    const [busy, setBusy] = useState<string | null>(null);
    const hasActiveYear = years.some((year) => year.is_active);

    function run(
        key: string,
        send: (options: Parameters<typeof router.post>[2]) => void,
        failure: string,
        done?: () => void,
    ) {
        send({
            preserveScroll: true,
            onStart: () => setBusy(key),
            onFinish: () => setBusy(null),
            onSuccess: done,
            onError: (errors) => {
                done?.();
                toast.error(Object.values(errors)[0] ?? failure);
            },
            onHttpException: () => {
                toast.error(failure);
            },
            onNetworkError: () => {
                toast.error(failure);
            },
        });
    }

    function activateYear(year: YearRow) {
        run(
            `year-${year.id}`,
            (options) => router.post(activate(year.id).url, {}, options),
            'Tahun ajaran belum bisa diaktifkan. Coba lagi ya.',
        );
    }

    function togglePeriod(year: YearRow, period: PeriodRow, active: boolean) {
        run(
            `period-${period.id}`,
            (options) =>
                router.put(
                    updatePeriod({ year: year.id, period: period.id }).url,
                    {
                        name: period.name,
                        sequence: period.sequence,
                        starts_on: period.starts_on,
                        ends_on: period.ends_on,
                        is_active: active,
                    },
                    options,
                ),
            'Periode belum bisa diubah. Coba lagi ya.',
        );
    }

    function removeYear(year: YearRow) {
        run(
            `delete-year-${year.id}`,
            (options) => router.delete(destroy(year.id).url, options),
            'Tahun ajaran belum bisa dihapus.',
            () => setDeletingYear(null),
        );
    }

    function removePeriod(target: PeriodTarget) {
        if (!target.period) {
            return;
        }

        run(
            `delete-period-${target.period.id}`,
            (options) =>
                router.delete(
                    destroyPeriod({
                        year: target.year.id,
                        period: target.period!.id,
                    }).url,
                    options,
                ),
            'Periode belum bisa dihapus.',
            () => setDeletingPeriod(null),
        );
    }

    const newYearButton = (
        <Button onClick={() => setYearForm({})}>
            <Plus />
            Tahun ajaran baru
        </Button>
    );

    return (
        <>
            <Head title="Tahun ajaran" />
            <div className="grid gap-6">
                <PageHeader
                    icon={CalendarDays}
                    title="Tahun ajaran"
                    description="Tahun ajaran dan periodenya. Siswa ditempatkan ke tahun ajaran, dan hanya satu yang aktif."
                    actions={newYearButton}
                />

                {years.length === 0 ? (
                    <EmptyState
                        icon={CalendarDays}
                        title="Belum ada tahun ajaran"
                        description="Buat tahun ajaran pertama supaya pendaftar yang sudah diumumkan bisa ditempatkan menjadi siswa."
                        action={newYearButton}
                    />
                ) : (
                    years.map((year) => (
                        <SectionCard
                            key={year.id}
                            title={year.name}
                            description={`${formatDate(year.starts_on)} s.d. ${formatDate(year.ends_on)} · ${year.periods.length} periode · ${year.students_count} siswa`}
                            icon={CalendarDays}
                            contentClassName="grid gap-4"
                        >
                            <div className="flex flex-wrap items-center gap-2">
                                {year.is_active ? (
                                    <Badge variant="success">Aktif</Badge>
                                ) : (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="max-md:h-10"
                                        disabled={busy === `year-${year.id}`}
                                        onClick={() => activateYear(year)}
                                    >
                                        Jadikan aktif
                                    </Button>
                                )}
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="max-md:h-10"
                                    onClick={() => setYearForm({ year })}
                                >
                                    <Pencil />
                                    Ubah
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="max-md:h-10"
                                    disabled={year.delete_blocked !== null}
                                    title={year.delete_blocked ?? undefined}
                                    onClick={() => setDeletingYear(year)}
                                >
                                    <Trash2 />
                                    Hapus
                                </Button>
                                {year.delete_blocked && (
                                    <span className="text-sm text-muted-foreground">
                                        {year.delete_blocked}
                                    </span>
                                )}
                            </div>

                            {year.periods.length === 0 ? (
                                <p className="rounded-xl bg-wash px-4 py-3 text-sm text-muted-foreground">
                                    Belum ada periode di tahun ajaran ini.
                                </p>
                            ) : (
                                <ul className="divide-y divide-line rounded-xl border border-line">
                                    {year.periods.map((period) => (
                                        <li
                                            key={period.id}
                                            className="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3"
                                        >
                                            <span className="grid min-w-40 flex-1">
                                                <span className="font-semibold">
                                                    {period.sequence}.{' '}
                                                    {period.name}
                                                </span>
                                                <span className="text-sm text-muted-foreground">
                                                    {formatDate(
                                                        period.starts_on,
                                                    )}{' '}
                                                    s.d.{' '}
                                                    {formatDate(period.ends_on)}
                                                </span>
                                            </span>
                                            <label className="flex items-center gap-2 text-sm">
                                                <Switch
                                                    checked={period.is_active}
                                                    disabled={
                                                        busy ===
                                                        `period-${period.id}`
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        togglePeriod(
                                                            year,
                                                            period,
                                                            checked,
                                                        )
                                                    }
                                                    aria-label={`Periode ${period.name} sedang berjalan`}
                                                />
                                                Berjalan
                                            </label>
                                            <span className="flex gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    className="max-md:size-10"
                                                    aria-label={`Ubah periode ${period.name}`}
                                                    onClick={() =>
                                                        setPeriodForm({
                                                            year,
                                                            period,
                                                        })
                                                    }
                                                >
                                                    <Pencil />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    className="max-md:size-10"
                                                    aria-label={`Hapus periode ${period.name}`}
                                                    onClick={() =>
                                                        setDeletingPeriod({
                                                            year,
                                                            period,
                                                        })
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}

                            <div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="max-md:h-10"
                                    onClick={() => setPeriodForm({ year })}
                                >
                                    <Plus />
                                    Tambah periode
                                </Button>
                            </div>
                        </SectionCard>
                    ))
                )}
            </div>

            {yearForm && (
                <YearFormDialog
                    year={yearForm.year}
                    suggestion={suggestion}
                    hasActiveYear={hasActiveYear}
                    onClose={() => setYearForm(null)}
                />
            )}
            {periodForm && (
                <PeriodFormDialog
                    year={periodForm.year}
                    period={periodForm.period}
                    onClose={() => setPeriodForm(null)}
                />
            )}
            <ConfirmDialog
                open={deletingYear !== null}
                onOpenChange={(open) => !open && setDeletingYear(null)}
                title={`Hapus tahun ajaran ${deletingYear?.name ?? ''}?`}
                description="Periode di dalamnya ikut terhapus. Tindakan ini tidak bisa dibatalkan."
                confirmLabel="Ya, hapus"
                tone="danger"
                processing={busy?.startsWith('delete-year') ?? false}
                onConfirm={() => deletingYear && removeYear(deletingYear)}
            />
            <ConfirmDialog
                open={deletingPeriod !== null}
                onOpenChange={(open) => !open && setDeletingPeriod(null)}
                title={`Hapus periode ${deletingPeriod?.period?.name ?? ''}?`}
                description="Tindakan ini tidak bisa dibatalkan."
                confirmLabel="Ya, hapus"
                tone="danger"
                processing={busy?.startsWith('delete-period') ?? false}
                onConfirm={() => deletingPeriod && removePeriod(deletingPeriod)}
            />
        </>
    );
}

YearsIndex.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Tahun ajaran', href: index() },
    ],
};
