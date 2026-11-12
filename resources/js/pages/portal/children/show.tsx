import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    CircleAlert,
    CircleCheck,
    CircleX,
    FileText,
    Hourglass,
    History,
    ListChecks,
    Megaphone,
    Send,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { PersonAvatar } from '@/components/person-avatar';
import { StickyActions } from '@/components/portal/sticky-actions';
import { TurnBadge } from '@/components/portal/turn-badge';
import { StatusTimeline } from '@/components/registrants/status-timeline';
import { SectionCard } from '@/components/section-card';
import { StatusBadge } from '@/components/status-badge';
import { Stepper } from '@/components/stepper';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Spinner } from '@/components/ui/spinner';
import { currentStepIndex, stepsFor } from '@/lib/admission-steps';
import { formatDate } from '@/lib/format';
import { nextAction } from '@/lib/portal-next-step';
import { cn } from '@/lib/utils';
import { edit } from '@/routes/portal/children/data';
import { edit as editDocuments } from '@/routes/portal/children/documents';
import { edit as editExam } from '@/routes/portal/children/exam';
import { submit as submitData } from '@/routes/portal/children';
import type { Completeness, PortalRegistrant } from '@/types/portal';
import type {
    ChosenSession,
    ExamResultData,
    TimelineEntry,
} from '@/types/registrant';

export default function ChildShow({
    registrant,
    completeness,
    family,
    readiness,
    examSession,
    hasExamSchedule,
    result,
    timeline,
    documents,
}: {
    registrant: PortalRegistrant;
    completeness: Completeness;
    family: { id: number; name: string; relation: string }[];
    readiness: string[];
    examSession: ChosenSession | null;
    hasExamSchedule: boolean;
    result: ExamResultData | null;
    timeline: TimelineEntry[];
    documents: { missing: string[]; asked: boolean; uploaded: number } | null;
}) {
    const [sending, setSending] = useState(false);

    function sendData() {
        setSending(true);
        router.post(
            submitData(registrant.id).url,
            {},
            { onFinish: () => setSending(false) },
        );
    }

    const steps = stepsFor(registrant.steps);
    const index =
        registrant.status === 'placed'
            ? steps.length
            : currentStepIndex(registrant.steps, registrant.current_step);
    const percent =
        completeness.total === 0
            ? 0
            : Math.round((completeness.filled / completeness.total) * 100);
    const next = nextAction(registrant, readiness);
    const readyToSend = registrant.can_edit && readiness.length === 0;
    // The send button lives in the sticky bar, so the card does not repeat it.
    const nextCta = next.kind === 'submit' ? null : next.cta;

    return (
        <>
            <Head title={registrant.child.name} />
            <div className="grid gap-4">
                <header className="flex items-center gap-4 pt-2">
                    <PersonAvatar
                        name={registrant.child.name}
                        src={registrant.child.photo_url}
                        size="lg"
                        className="size-16 text-xl"
                    />
                    <div className="grid min-w-0 gap-1">
                        <h1 className="text-[26px] leading-tight font-semibold tracking-tight text-balance">
                            {registrant.child.name}
                        </h1>
                        <p className="text-[15px] text-muted-foreground">
                            {registrant.organization} · {registrant.wave}
                        </p>
                        <div className="pt-0.5">
                            <StatusBadge status={registrant.status} />
                        </div>
                    </div>
                </header>

                {registrant.identity_pending && (
                    <Alert variant="info">
                        <Hourglass />
                        <AlertTitle>
                            Panitia sedang mencocokkan data anak
                        </AlertTitle>
                        <AlertDescription>
                            <p>
                                NIK yang kamu masukkan sudah tercatat di sistem
                                dengan nama yang berbeda, jadi panitia perlu
                                memastikan dulu bahwa datanya cocok.
                            </p>
                            <p>
                                Sampai selesai, data anak belum bisa dilihat
                                atau diisi. Kamu akan dikabari kalau sudah.
                            </p>
                        </AlertDescription>
                    </Alert>
                )}

                {registrant.revision_reason && (
                    <Alert variant="warning">
                        <CircleAlert />
                        <AlertTitle>Ada yang perlu diperbaiki</AlertTitle>
                        <AlertDescription>
                            <p className="whitespace-pre-wrap">
                                {registrant.revision_reason}
                            </p>
                            <p>
                                Perbaiki datanya, lalu kirim lagi untuk
                                diperiksa.
                            </p>
                        </AlertDescription>
                    </Alert>
                )}

                <section
                    aria-label="Langkah berikutnya"
                    className={cn(
                        'grid gap-3 rounded-2xl border p-4',
                        next.turn === 'parent'
                            ? 'border-lime-300 bg-lime-50 dark:border-lime-400/40 dark:bg-lime-400/10'
                            : 'border-line bg-card',
                    )}
                >
                    <div className="grid justify-items-start gap-3">
                        <TurnBadge turn={next.turn} label={next.turnLabel} />
                        <div className="grid gap-1">
                            <h2 className="text-xl font-semibold tracking-tight">
                                {next.title}
                            </h2>
                            <p className="text-[15px] text-muted-foreground">
                                {next.description}
                            </p>
                        </div>
                    </div>
                    {nextCta && (
                        <Button asChild size="lg" className="w-full">
                            <Link href={nextCta.href}>
                                {nextCta.label}
                                <ArrowRight />
                            </Link>
                        </Button>
                    )}
                </section>

                {result && result.aspects.length > 0 && (
                    <SectionCard title="Hasil ujian" icon={Megaphone}>
                        <ul className="grid divide-y divide-line">
                            {result.aspects.map((aspect) => (
                                <li
                                    key={aspect.name}
                                    className="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                                >
                                    <div className="grid gap-0.5">
                                        <span className="text-base font-semibold">
                                            {aspect.name}
                                        </span>
                                        <span className="text-[15px] text-muted-foreground tabular-nums">
                                            Nilai {aspect.score_label ?? '-'}{' '}
                                            (KKM {aspect.passing_label})
                                        </span>
                                    </div>
                                    {aspect.passed !== null && (
                                        <Badge
                                            variant={
                                                aspect.passed
                                                    ? 'success'
                                                    : 'danger'
                                            }
                                            className="gap-1.5 px-3 py-1 text-[13px]"
                                        >
                                            {aspect.passed ? (
                                                <CircleCheck />
                                            ) : (
                                                <CircleX />
                                            )}
                                            {aspect.passed
                                                ? 'Tuntas'
                                                : 'Belum tuntas'}
                                        </Badge>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </SectionCard>
                )}

                {registrant.can_edit && (
                    <SectionCard
                        title="Kelengkapan data"
                        icon={ListChecks}
                        description="Data disimpan sebagai draf, jadi boleh dilanjutkan kapan saja."
                    >
                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <div className="flex items-center justify-between gap-3 text-[15px]">
                                    <span className="font-semibold">
                                        Sudah terisi
                                    </span>
                                    <span className="text-muted-foreground tabular-nums">
                                        {completeness.filled} dari{' '}
                                        {completeness.total} bagian
                                    </span>
                                </div>
                                <Progress
                                    value={percent}
                                    aria-label="Kelengkapan data"
                                />
                            </div>

                            {readiness.length > 0 ? (
                                <div className="grid gap-2">
                                    <p className="text-[15px] font-semibold">
                                        Yang masih kurang
                                    </p>
                                    <ul className="grid gap-2">
                                        {readiness.map((item) => (
                                            <li
                                                key={item}
                                                className="flex items-start gap-2 text-[15px]"
                                            >
                                                <CircleAlert className="mt-0.5 size-4 shrink-0 text-warning" />
                                                {item}
                                            </li>
                                        ))}
                                    </ul>
                                    <p className="text-sm text-muted-foreground">
                                        Kalau semuanya sudah lengkap, tombol
                                        kirim akan muncul di bawah layar.
                                    </p>
                                </div>
                            ) : (
                                <p className="flex items-center gap-2 text-[15px] font-medium text-success">
                                    <CircleCheck className="size-5" />
                                    Semua sudah lengkap.
                                </p>
                            )}

                            <Button
                                asChild
                                size="lg"
                                variant="outline"
                                className="w-full"
                            >
                                <Link href={edit(registrant.id)}>
                                    {completeness.filled === 0
                                        ? 'Mulai mengisi'
                                        : 'Lanjutkan mengisi'}
                                    <ArrowRight />
                                </Link>
                            </Button>
                        </div>
                    </SectionCard>
                )}

                {!registrant.identity_pending && hasExamSchedule && (
                    <SectionCard title="Jadwal ujian" icon={CalendarDays}>
                        <div className="grid gap-4">
                            {examSession ? (
                                <div className="grid gap-0.5">
                                    <p className="text-lg font-semibold">
                                        {formatDate(examSession.date, {
                                            weekday: 'long',
                                            day: 'numeric',
                                            month: 'long',
                                            year: 'numeric',
                                        })}
                                    </p>
                                    <p className="text-[15px] text-muted-foreground">
                                        {examSession.label}
                                    </p>
                                </div>
                            ) : (
                                <p className="text-[15px] text-muted-foreground">
                                    Belum memilih. Pilih tanggal dan sesi ujian
                                    dulu sebelum data dikirim.
                                </p>
                            )}
                            <Button
                                asChild
                                size="lg"
                                variant={
                                    examSession || !registrant.can_edit
                                        ? 'outline'
                                        : 'default'
                                }
                                className="w-full"
                            >
                                <Link href={editExam(registrant.id)}>
                                    {examSession
                                        ? registrant.can_edit
                                            ? 'Ganti jadwal'
                                            : 'Lihat jadwal'
                                        : registrant.can_edit
                                          ? 'Pilih jadwal'
                                          : 'Lihat jadwal'}
                                </Link>
                            </Button>
                        </div>
                    </SectionCard>
                )}

                {documents && (
                    <SectionCard title="Berkas" icon={FileText}>
                        <div className="grid gap-3">
                            <p className="text-[15px] text-muted-foreground">
                                {documents.missing.length > 0
                                    ? `Masih kurang: ${documents.missing.join(', ')}.`
                                    : documents.uploaded > 0
                                      ? 'Berkas yang diminta sudah lengkap. Kamu masih bisa menambah bukti prestasi atau bantuan.'
                                      : 'Unggah berkas pendaftaran, dan bukti prestasi atau bantuan kalau ada.'}
                            </p>
                            <Button
                                variant={
                                    documents.missing.length > 0
                                        ? 'default'
                                        : 'outline'
                                }
                                asChild
                            >
                                <Link href={editDocuments(registrant.id)}>
                                    Kelola berkas
                                </Link>
                            </Button>
                        </div>
                    </SectionCard>
                )}

                <SectionCard
                    title="Tahapan pendaftaran"
                    description="Kamu bisa memantau sampai pengumuman."
                >
                    <Stepper
                        steps={steps}
                        currentIndex={index}
                        orientation="vertical"
                    />
                </SectionCard>

                {!registrant.identity_pending && family.length > 0 && (
                    <SectionCard title="Keluarga" icon={Users}>
                        <ul className="grid gap-3">
                            {family.map((member) => (
                                <li
                                    key={member.id}
                                    className="flex items-center gap-3"
                                >
                                    <PersonAvatar
                                        name={member.name}
                                        size="md"
                                    />
                                    <span className="min-w-0 flex-1 truncate text-[15px] font-semibold">
                                        {member.name}
                                    </span>
                                    <Badge variant="muted">
                                        {member.relation}
                                    </Badge>
                                </li>
                            ))}
                        </ul>
                    </SectionCard>
                )}

                {timeline.length > 0 && (
                    <SectionCard title="Riwayat" icon={History}>
                        <StatusTimeline entries={timeline} />
                    </SectionCard>
                )}

                {readyToSend && (
                    <StickyActions>
                        <p className="text-sm text-muted-foreground">
                            Setelah dikirim, data tidak bisa diubah sampai
                            panitia selesai memeriksa.
                        </p>
                        <Button
                            size="lg"
                            className="w-full"
                            onClick={sendData}
                            disabled={sending}
                        >
                            {sending ? <Spinner /> : <Send />}
                            Kirim data untuk diperiksa
                        </Button>
                    </StickyActions>
                )}
            </div>
        </>
    );
}
