import { useForm } from '@inertiajs/react';
import { CalendarPlus, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { examSchedule } from '@/routes/admin/rulesets';
import type { ExamDayRow, ExamSessionRow } from '@/types/admission';

export function ExamScheduleEditor({
    rulesetId,
    initial,
    canEdit,
}: {
    rulesetId: number;
    initial: ExamDayRow[];
    canEdit: boolean;
}) {
    const form = useForm<{ days: ExamDayRow[] }>({ days: initial });
    const errors = form.errors as Record<string, string>;

    function setDays(days: ExamDayRow[]) {
        form.setData('days', days);
    }

    function updateDay(index: number, changes: Partial<ExamDayRow>) {
        setDays(
            form.data.days.map((day, i) =>
                i === index ? { ...day, ...changes } : day,
            ),
        );
    }

    function updateSession(
        dayIndex: number,
        sessionIndex: number,
        changes: Partial<ExamSessionRow>,
    ) {
        updateDay(dayIndex, {
            sessions: form.data.days[dayIndex].sessions.map((session, i) =>
                i === sessionIndex ? { ...session, ...changes } : session,
            ),
        });
    }

    function fieldError(path: string) {
        return errors[path] ? (
            <p className="text-sm font-medium text-destructive-foreground">
                {errors[path]}
            </p>
        ) : null;
    }

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(examSchedule(rulesetId).url, { preserveScroll: true });
            }}
            className="grid gap-5"
        >
            {form.data.days.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    Belum ada jadwal ujian. Tambahkan tanggal, lalu sesinya.
                </p>
            )}

            {form.data.days.map((day, dayIndex) => (
                <div
                    key={day.id ?? `new-${dayIndex}`}
                    className="grid gap-4 rounded-2xl border bg-neutral-50 p-4 dark:bg-card"
                >
                    <div className="flex flex-wrap items-start gap-3">
                        <div className="grid gap-1">
                            <Input
                                aria-label="Tanggal ujian"
                                type="date"
                                value={day.date}
                                disabled={!canEdit}
                                className="w-48"
                                aria-invalid={Boolean(
                                    errors[`days.${dayIndex}.date`],
                                )}
                                onChange={(e) =>
                                    updateDay(dayIndex, {
                                        date: e.target.value,
                                    })
                                }
                            />
                            {fieldError(`days.${dayIndex}.date`)}
                        </div>
                        {canEdit && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="ml-auto max-md:h-10"
                                onClick={() =>
                                    setDays(
                                        form.data.days.filter(
                                            (_, i) => i !== dayIndex,
                                        ),
                                    )
                                }
                            >
                                <Trash2 />
                                Hapus tanggal
                            </Button>
                        )}
                    </div>

                    <div className="grid gap-3">
                        {day.sessions.map((session, sessionIndex) => {
                            const base = `days.${dayIndex}.sessions.${sessionIndex}`;

                            return (
                                <div
                                    key={session.id ?? `new-${sessionIndex}`}
                                    className="grid gap-2 sm:grid-cols-[1fr_7rem_7rem_6rem_auto] sm:items-start"
                                >
                                    <div className="grid gap-1">
                                        <Input
                                            aria-label="Nama sesi"
                                            placeholder="Nama sesi, mis. Sesi 1"
                                            value={session.label}
                                            disabled={!canEdit}
                                            onChange={(e) =>
                                                updateSession(
                                                    dayIndex,
                                                    sessionIndex,
                                                    { label: e.target.value },
                                                )
                                            }
                                        />
                                        {fieldError(`${base}.label`)}
                                    </div>
                                    <div className="grid gap-1">
                                        <Input
                                            aria-label="Jam mulai"
                                            type="time"
                                            value={session.start_time}
                                            disabled={!canEdit}
                                            onChange={(e) =>
                                                updateSession(
                                                    dayIndex,
                                                    sessionIndex,
                                                    {
                                                        start_time:
                                                            e.target.value,
                                                    },
                                                )
                                            }
                                        />
                                        {fieldError(`${base}.start_time`)}
                                    </div>
                                    <div className="grid gap-1">
                                        <Input
                                            aria-label="Jam selesai"
                                            type="time"
                                            value={session.end_time}
                                            disabled={!canEdit}
                                            onChange={(e) =>
                                                updateSession(
                                                    dayIndex,
                                                    sessionIndex,
                                                    {
                                                        end_time:
                                                            e.target.value,
                                                    },
                                                )
                                            }
                                        />
                                        {fieldError(`${base}.end_time`)}
                                    </div>
                                    <div className="grid gap-1">
                                        <Input
                                            aria-label="Kuota"
                                            type="number"
                                            min={1}
                                            placeholder="Kuota"
                                            value={session.quota}
                                            disabled={!canEdit}
                                            onChange={(e) =>
                                                updateSession(
                                                    dayIndex,
                                                    sessionIndex,
                                                    { quota: e.target.value },
                                                )
                                            }
                                        />
                                        {fieldError(`${base}.quota`)}
                                    </div>
                                    {canEdit && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Hapus sesi"
                                            onClick={() =>
                                                updateDay(dayIndex, {
                                                    sessions:
                                                        day.sessions.filter(
                                                            (_, i) =>
                                                                i !==
                                                                sessionIndex,
                                                        ),
                                                })
                                            }
                                        >
                                            <Trash2 />
                                        </Button>
                                    )}
                                </div>
                            );
                        })}

                        {canEdit && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="w-fit max-md:h-10"
                                onClick={() =>
                                    updateDay(dayIndex, {
                                        sessions: [
                                            ...day.sessions,
                                            {
                                                label: `Sesi ${day.sessions.length + 1}`,
                                                start_time: '08:00',
                                                end_time: '10:00',
                                                quota: 30,
                                            },
                                        ],
                                    })
                                }
                            >
                                <Plus />
                                Tambah sesi
                            </Button>
                        )}
                    </div>
                </div>
            ))}

            {canEdit && (
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="max-md:h-10"
                        onClick={() =>
                            setDays([
                                ...form.data.days,
                                { date: '', sessions: [] },
                            ])
                        }
                    >
                        <CalendarPlus />
                        Tambah tanggal
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        Simpan jadwal
                    </Button>
                </div>
            )}
        </form>
    );
}
