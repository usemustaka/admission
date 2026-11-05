import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StickyActions } from '@/components/portal/sticky-actions';
import { SectionCard } from '@/components/section-card';
import { SlotPicker } from '@/components/slot-picker';
import type { SlotDay } from '@/components/slot-picker';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatDate } from '@/lib/format';
import { show } from '@/routes/portal/children';
import { update } from '@/routes/portal/children/exam';
import type { PortalRegistrant } from '@/types/portal';

export default function ChildExam({
    registrant,
    days,
    selected,
    canEdit,
    errors,
}: {
    registrant: PortalRegistrant;
    days: SlotDay[];
    selected: string | null;
    canEdit: boolean;
    errors: Record<string, string>;
}) {
    const [value, setValue] = useState(selected ?? undefined);
    const [processing, setProcessing] = useState(false);

    const chosen = days
        .flatMap((day) =>
            day.sessions.map((session) => ({ day: day.date, session })),
        )
        .find((entry) => entry.session.id === value);

    function save() {
        setProcessing(true);
        router.post(
            update(registrant.id).url,
            { exam_session_id: value },
            { onFinish: () => setProcessing(false) },
        );
    }

    return (
        <>
            <Head title="Jadwal ujian" />
            <div className="grid gap-4">
                <PageHeader
                    title="Pilih jadwal ujian"
                    description={`Untuk ${registrant.child.name}. Sesi yang sudah penuh tidak bisa dipilih.`}
                />

                {!canEdit && (
                    <Alert variant="info">
                        <CalendarDays />
                        <AlertTitle>Jadwal tidak bisa diubah lagi</AlertTitle>
                        <AlertDescription>
                            Data sudah dikirim ke panitia. Kalau perlu pindah
                            sesi, hubungi sekolah ya.
                        </AlertDescription>
                    </Alert>
                )}

                {days.length === 0 ? (
                    <EmptyState
                        icon={CalendarDays}
                        title="Belum ada jadwal ujian"
                        description="Panitia belum menyiapkan tanggal ujian. Coba lagi nanti ya."
                    />
                ) : (
                    <SectionCard title="Tanggal dan sesi">
                        <div className="grid gap-4">
                            <fieldset disabled={!canEdit}>
                                <SlotPicker
                                    days={days}
                                    value={value}
                                    onValueChange={setValue}
                                />
                            </fieldset>
                            {errors.exam_session_id && (
                                <p className="text-[15px] font-medium text-destructive-foreground">
                                    {errors.exam_session_id}
                                </p>
                            )}
                        </div>
                    </SectionCard>
                )}

                <StickyActions>
                    {canEdit && days.length > 0 && (
                        <p className="text-[15px]">
                            {chosen ? (
                                <>
                                    <span className="text-muted-foreground">
                                        Pilihanmu:{' '}
                                    </span>
                                    <span className="font-semibold">
                                        {formatDate(chosen.day, {
                                            weekday: 'long',
                                            day: 'numeric',
                                            month: 'long',
                                        })}
                                        , {chosen.session.label}
                                    </span>
                                </>
                            ) : (
                                <span className="text-muted-foreground">
                                    Pilih tanggal dan sesi dulu.
                                </span>
                            )}
                        </p>
                    )}
                    <div className="flex gap-3">
                        <Button
                            variant="outline"
                            size="lg"
                            className={
                                canEdit && days.length > 0
                                    ? undefined
                                    : 'w-full'
                            }
                            asChild
                        >
                            <Link href={show(registrant.id)}>Kembali</Link>
                        </Button>
                        {canEdit && days.length > 0 && (
                            <Button
                                size="lg"
                                className="flex-1"
                                onClick={save}
                                disabled={
                                    processing ||
                                    value === undefined ||
                                    value === selected
                                }
                            >
                                {processing && <Spinner />}
                                Simpan jadwal
                            </Button>
                        )}
                    </div>
                </StickyActions>
            </div>
        </>
    );
}
