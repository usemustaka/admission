import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    CircleAlert,
    Hourglass,
    History,
    Megaphone,
    Send,
    Users,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { PersonAvatar } from '@/components/person-avatar';
import { StatusTimeline } from '@/components/registrants/status-timeline';
import { SectionCard } from '@/components/section-card';
import { StatusBadge } from '@/components/status-badge';
import { Stepper } from '@/components/stepper';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Spinner } from '@/components/ui/spinner';
import { formatDate } from '@/lib/format';
import { currentStepIndex, stepsFor } from '@/lib/admission-steps';
import { portal } from '@/routes';
import { edit } from '@/routes/portal/children/data';
import { edit as editExam } from '@/routes/portal/children/exam';
import { bills, submit as submitData } from '@/routes/portal/children';
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
}: {
    registrant: PortalRegistrant;
    completeness: Completeness;
    family: { id: number; name: string; relation: string }[];
    readiness: string[];
    examSession: ChosenSession | null;
    hasExamSchedule: boolean;
    result: ExamResultData | null;
    timeline: TimelineEntry[];
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
    const index = currentStepIndex(registrant.steps, registrant.current_step);
    const percent = Math.round(
        (completeness.filled / completeness.total) * 100,
    );

    return (
        <>
            <Head title={registrant.child.name} />
            <div className="mx-auto grid w-full max-w-4xl gap-6 px-4 py-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <PersonAvatar
                        name={registrant.child.name}
                        src={registrant.child.photo_url}
                        size="xl"
                    />
                    <div className="flex-1">
                        <PageHeader
                            title={registrant.child.name}
                            description={`${registrant.organization} · ${registrant.wave}`}
                            meta={
                                <div className="pt-1">
                                    <StatusBadge status={registrant.status} />
                                </div>
                            }
                        />
                    </div>
                </div>

                <SectionCard
                    title="Tahapan pendaftaran"
                    description="Kamu bisa memantau sampai pengumuman."
                >
                    <div className="hidden lg:block">
                        <Stepper steps={steps} currentIndex={index} />
                    </div>
                    <div className="lg:hidden">
                        <Stepper
                            steps={steps}
                            currentIndex={index}
                            orientation="vertical"
                        />
                    </div>
                </SectionCard>

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

                {registrant.current_step === 'registration-bill' && (
                    <SectionCard
                        title="Bayar tagihan pendaftaran"
                        description="Selesaikan dulu supaya pendaftaran bisa lanjut ke tahap berikutnya. Datanya boleh diisi sambil menunggu."
                        icon={Wallet}
                    >
                        <Button asChild>
                            <Link href={bills(registrant.id)}>
                                Lihat tagihan
                                <ArrowRight />
                            </Link>
                        </Button>
                    </SectionCard>
                )}

                {registrant.can_edit && (
                    <SectionCard
                        title="Lengkapi data anak"
                        description="Data disimpan sebagai draf, jadi boleh dilanjutkan kapan saja."
                    >
                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <div className="flex items-center justify-between text-sm">
                                    <span className="font-medium">
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
                            <div>
                                <Button asChild>
                                    <Link href={edit(registrant.id)}>
                                        {completeness.filled === 0
                                            ? 'Mulai mengisi'
                                            : 'Lanjutkan mengisi'}
                                        <ArrowRight />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </SectionCard>
                )}

                {!registrant.identity_pending && hasExamSchedule && (
                    <SectionCard
                        title="Jadwal ujian"
                        icon={CalendarDays}
                        description={
                            examSession
                                ? undefined
                                : 'Pilih tanggal dan sesi ujian dulu sebelum data dikirim.'
                        }
                    >
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <p className="text-sm font-semibold">
                                {examSession
                                    ? `${formatDate(examSession.date, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}, ${examSession.label}`
                                    : 'Belum memilih'}
                            </p>
                            <Button
                                variant={examSession ? 'outline' : 'default'}
                                asChild
                            >
                                <Link href={editExam(registrant.id)}>
                                    {examSession
                                        ? registrant.can_edit
                                            ? 'Ganti jadwal'
                                            : 'Lihat jadwal'
                                        : 'Pilih jadwal'}
                                </Link>
                            </Button>
                        </div>
                    </SectionCard>
                )}

                {registrant.can_edit && (
                    <SectionCard
                        title="Kirim untuk diperiksa"
                        description="Kalau semuanya sudah lengkap, kirim datanya supaya panitia bisa memeriksa."
                        icon={Send}
                    >
                        <div className="grid gap-4">
                            {readiness.length > 0 ? (
                                <div className="grid gap-2">
                                    <p className="text-sm font-medium">
                                        Yang masih kurang:
                                    </p>
                                    <ul className="grid list-disc gap-1 pl-5 text-sm text-muted-foreground">
                                        {readiness.map((item) => (
                                            <li key={item}>{item}</li>
                                        ))}
                                    </ul>
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Semua sudah lengkap. Setelah dikirim, data
                                    tidak bisa diubah sampai panitia selesai
                                    memeriksa.
                                </p>
                            )}
                            <div>
                                <Button
                                    onClick={sendData}
                                    disabled={sending || readiness.length > 0}
                                >
                                    {sending && <Spinner />}
                                    Kirim data
                                </Button>
                            </div>
                        </div>
                    </SectionCard>
                )}

                {result && result.aspects.length > 0 && (
                    <SectionCard title="Hasil ujian" icon={Megaphone}>
                        <ul className="grid gap-3">
                            {result.aspects.map((aspect) => (
                                <li
                                    key={aspect.name}
                                    className="flex items-center justify-between gap-3 text-sm"
                                >
                                    <span className="font-semibold">
                                        {aspect.name}
                                    </span>
                                    <span className="flex items-center gap-2">
                                        <span className="tabular-nums">
                                            {aspect.score ?? '-'} (KKM{' '}
                                            {aspect.passing_score})
                                        </span>
                                        {aspect.passed !== null && (
                                            <Badge
                                                variant={
                                                    aspect.passed
                                                        ? 'success'
                                                        : 'danger'
                                                }
                                            >
                                                {aspect.passed
                                                    ? 'Tuntas'
                                                    : 'Belum tuntas'}
                                            </Badge>
                                        )}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </SectionCard>
                )}

                {!registrant.identity_pending && (
                    <SectionCard title="Keluarga" icon={Users}>
                        <ul className="grid gap-2">
                            {family.map((member) => (
                                <li
                                    key={member.id}
                                    className="flex items-center gap-3 text-sm"
                                >
                                    <PersonAvatar
                                        name={member.name}
                                        size="sm"
                                    />
                                    <span className="font-semibold">
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

                <div>
                    <Button variant="ghost" asChild>
                        <Link href={portal()}>Kembali ke semua anak</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
