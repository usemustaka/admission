import { useForm } from '@inertiajs/react';
import { CalendarPlus, CircleAlert, Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { newRowKey } from '@/components/admission/row-key';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/admin/exams/schedule';
import type { ExamDayRow, ExamSessionRow } from '@/types/admission';

/**
 * Edits the dates and sessions of one schedule: the sessions offered to registrations of `organizationId` and
 * `waveId`, where null means all of them. Remount it (with a `key`) when the scope changes.
 */
export function ExamScheduleEditor({
    organizationId,
    waveId,
    initial,
    canEdit,
}: {
    organizationId: number | null;
    waveId: number | null;
    initial: ExamDayRow[];
    canEdit: boolean;
}) {
    const form = useForm<{
        organization_id: number | null;
        wave_id: number | null;
        days: ExamDayRow[];
    }>({ organization_id: organizationId, wave_id: waveId, days: initial });
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

    const generalErrors = Object.entries(errors)
        .filter(([key]) => !key.includes('.'))
        .map(([, message]) => message);

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(update().url, {
                    preserveScroll: true,
                    onError: () =>
                        toast.error(
                            'Jadwal belum tersimpan. Cek isian yang ditandai merah.',
                        ),
                });
            }}
            className="grid gap-5"
        >
            <p className="text-sm text-muted-foreground">
                Tanggal dan sesi baru bisa dipilih pendaftar setelah jadwal
                disimpan. Perubahan jam atau tanggal sesi yang sudah dipilih
                pendaftar ikut berubah untuk mereka, jadi kabari mereka kalau
                perlu.
            </p>

            {generalErrors.length > 0 && (
                <Alert variant="destructive">
                    <CircleAlert />
                    <AlertDescription>
                        {generalErrors.map((message) => (
                            <p key={message}>{message}</p>
                        ))}
                    </AlertDescription>
                </Alert>
            )}

            {form.data.days.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    Belum ada tanggal untuk jadwal ini. Tambahkan tanggal, lalu
                    sesinya.
                </p>
            )}

            {form.data.days.map((day, dayIndex) => (
                <div
                    key={day.id ?? day.key ?? `new-${dayIndex}`}
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
                                disabled={day.sessions.some(
                                    (session) => (session.taken ?? 0) > 0,
                                )}
                                title={
                                    day.sessions.some(
                                        (session) => (session.taken ?? 0) > 0,
                                    )
                                        ? 'Ada sesi di tanggal ini yang sudah dipilih pendaftar'
                                        : undefined
                                }
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
                                    key={
                                        session.id ??
                                        session.key ??
                                        `new-${sessionIndex}`
                                    }
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
                                            min={Math.max(
                                                1,
                                                session.taken ?? 0,
                                            )}
                                            max={65535}
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
                                        {session.taken !== undefined && (
                                            <p className="text-xs text-muted-foreground tabular-nums">
                                                terisi {session.taken}
                                            </p>
                                        )}
                                        {fieldError(`${base}.quota`)}
                                    </div>
                                    {canEdit && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Hapus sesi"
                                            disabled={(session.taken ?? 0) > 0}
                                            title={
                                                (session.taken ?? 0) > 0
                                                    ? 'Sesi ini sudah dipilih pendaftar'
                                                    : undefined
                                            }
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
                                                key: newRowKey(),
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
                                { key: newRowKey(), date: '', sessions: [] },
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
