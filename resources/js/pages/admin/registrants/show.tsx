import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeftRight,
    BadgeCheck,
    CalendarDays,
    ClipboardCheck,
    GraduationCap,
    History,
    Megaphone,
    MessageSquareWarning,
    PencilLine,
    ShieldAlert,
    Users,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { CopyableText } from '@/components/copyable-text';
import { DescriptionList } from '@/components/description-list';
import { PageHeader } from '@/components/page-header';
import { PersonAvatar } from '@/components/person-avatar';
import { ExamScoresForm } from '@/components/registrants/exam-scores-form';
import { StatusTimeline } from '@/components/registrants/status-timeline';
import {
    BypassDialog,
    PlaceDialog,
    ReviseDialog,
} from '@/components/registrants/workflow-dialogs';
import { SectionCard } from '@/components/section-card';
import { SlotPicker } from '@/components/slot-picker';
import type { SlotDay } from '@/components/slot-picker';
import { StatusBadge } from '@/components/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatRupiah } from '@/lib/format';
import { dashboard } from '@/routes';
import { edit as editPerson } from '@/routes/admin/people';
import {
    accept,
    announce,
    examSession,
    index,
    resolveIdentity,
} from '@/routes/admin/registrants';
import { edit } from '@/routes/admin/registrants/data';
import type { BillData } from '@/types/billing';
import type { Option } from '@/types/people';
import type {
    ChosenSession,
    ExamResultData,
    TimelineEntry,
} from '@/types/registrant';

type Detail = {
    id: number;
    status: string;
    status_label: string;
    needs_identity_review: boolean;
    submitted_name: string;
    registered_by: string;
    registered_as: string;
    organization: string;
    wave: string;
    registered_at: string | null;
    submitted_at: string | null;
    formal_photo_url: string | null;
    child: {
        id: number;
        name: string;
        nik: string | null;
        gender: string | null;
        birth: string | null;
        religion: string | null;
        address: string | null;
        family_card_number: string | null;
        photo_url: string | null;
    };
};

type Can = {
    manage: boolean;
    accept: boolean;
    revise: boolean;
    announce: boolean;
    place: boolean;
    bypass: boolean;
};

export default function RegistrantShow({
    registrant,
    family,
    examSession: chosenSession,
    examDays,
    selectedSession,
    bills,
    result,
    scores,
    readiness,
    timeline,
    announcement,
    student,
    years,
    statuses,
    settlement,
    can,
}: {
    registrant: Detail;
    family: {
        id: number;
        person_id: number;
        name: string;
        relation: string;
        phone: string | null;
        is_primary_contact: boolean;
    }[];
    examSession: ChosenSession | null;
    examDays: SlotDay[];
    selectedSession: string | null;
    bills: BillData[];
    result: ExamResultData;
    scores: (number | null)[];
    readiness: string[];
    timeline: TimelineEntry[];
    announcement: {
        text: string;
        recipient: { name: string; phone: string } | null;
        whatsapp_url: string | null;
    } | null;
    student: { nis: string | null; year: string | null } | null;
    years: { value: number; label: string; is_active: boolean }[];
    statuses: Option[];
    settlement: { has_bill: boolean; paid: boolean };
    can: Can;
}) {
    const [revising, setRevising] = useState(false);
    const [bypassing, setBypassing] = useState(false);
    const [placing, setPlacing] = useState(false);
    const [accepting, setAccepting] = useState(false);
    const [resolving, setResolving] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [session, setSession] = useState(selectedSession ?? undefined);

    function post(url: string, onFinish?: () => void) {
        setProcessing(true);
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    onFinish?.();
                },
            },
        );
    }

    return (
        <>
            <Head title={registrant.child.name} />
            <div className="mx-auto grid w-full max-w-5xl gap-6 p-4 md:p-6">
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
                                <div className="flex flex-wrap items-center gap-2 pt-1">
                                    <StatusBadge status={registrant.status} />
                                    {registrant.needs_identity_review && (
                                        <Badge
                                            variant="danger"
                                            className="gap-1"
                                        >
                                            <ShieldAlert />
                                            Cek NIK
                                        </Badge>
                                    )}
                                </div>
                            }
                        />
                    </div>
                </div>

                {can.manage && (
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={edit(registrant.id)}>
                                <PencilLine />
                                Ubah data
                            </Link>
                        </Button>
                        {can.accept && (
                            <Button onClick={() => setAccepting(true)}>
                                <BadgeCheck />
                                Terima data
                            </Button>
                        )}
                        {can.revise && (
                            <Button
                                variant="outline"
                                onClick={() => setRevising(true)}
                            >
                                <MessageSquareWarning />
                                Minta revisi
                            </Button>
                        )}
                        {can.announce && (
                            <Button
                                disabled={processing}
                                onClick={() =>
                                    post(announce(registrant.id).url)
                                }
                            >
                                {processing && <Spinner />}
                                <Megaphone />
                                Tandai sudah diumumkan
                            </Button>
                        )}
                        {can.place && (
                            <Button onClick={() => setPlacing(true)}>
                                <GraduationCap />
                                Tempatkan siswa
                            </Button>
                        )}
                        {can.bypass && (
                            <Button
                                variant="ghost"
                                onClick={() => setBypassing(true)}
                            >
                                <ArrowLeftRight />
                                Lompati status
                            </Button>
                        )}
                    </div>
                )}

                {registrant.needs_identity_review && (
                    <Alert variant="warning">
                        <ShieldAlert />
                        <AlertTitle>
                            NIK sudah ada, tapi namanya berbeda
                        </AlertTitle>
                        <AlertDescription>
                            <p>
                                {registrant.registered_by} (
                                {registrant.registered_as}) mengetik nama &quot;
                                {registrant.submitted_name}&quot;, sedangkan di
                                data tercatat &quot;{registrant.child.name}
                                &quot;. Selama belum dicocokkan, orang tua belum
                                tertaut dan tidak melihat data anak ini.
                            </p>
                            {can.manage && (
                                <div className="flex flex-wrap gap-2 pt-2">
                                    <Button
                                        size="sm"
                                        onClick={() => setResolving(true)}
                                    >
                                        NIK sudah cocok
                                    </Button>
                                    <Button size="sm" variant="outline" asChild>
                                        <Link
                                            href={editPerson(
                                                registrant.child.id,
                                            )}
                                        >
                                            Koreksi nama atau NIK
                                        </Link>
                                    </Button>
                                </div>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                {readiness.length > 0 && !registrant.needs_identity_review && (
                    <Alert variant="info">
                        <ClipboardCheck />
                        <AlertTitle>
                            Data belum siap dikirim orang tua
                        </AlertTitle>
                        <AlertDescription>
                            Yang masih kurang: {readiness.join(', ')}.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                    <div className="grid content-start gap-6">
                        <SectionCard title="Data anak">
                            <div className="grid gap-6 sm:grid-cols-[auto_1fr]">
                                {registrant.formal_photo_url && (
                                    <img
                                        src={registrant.formal_photo_url}
                                        alt={`Pas foto ${registrant.child.name}`}
                                        className="h-40 w-30 rounded-xl border object-cover"
                                    />
                                )}
                                <DescriptionList
                                    items={[
                                        {
                                            label: 'NIK',
                                            value: registrant.child.nik,
                                        },
                                        {
                                            label: 'Jenis kelamin',
                                            value: registrant.child.gender,
                                        },
                                        {
                                            label: 'Tempat, tanggal lahir',
                                            value: registrant.child.birth,
                                        },
                                        {
                                            label: 'Agama',
                                            value: registrant.child.religion,
                                        },
                                        {
                                            label: 'Nomor KK',
                                            value: registrant.child
                                                .family_card_number,
                                        },
                                        {
                                            label: 'Mendaftar',
                                            value: registrant.registered_at
                                                ? formatDate(
                                                      registrant.registered_at,
                                                  )
                                                : null,
                                        },
                                        {
                                            label: 'Alamat',
                                            value: registrant.child.address,
                                            wide: true,
                                        },
                                    ]}
                                />
                            </div>
                        </SectionCard>

                        <SectionCard title="Ujian" icon={CalendarDays}>
                            <div className="grid gap-6">
                                <div className="grid gap-3">
                                    <p className="text-sm">
                                        Jadwal:{' '}
                                        <span className="font-semibold">
                                            {chosenSession
                                                ? `${formatDate(chosenSession.date, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}, ${chosenSession.label}`
                                                : 'belum memilih'}
                                        </span>
                                    </p>
                                    {can.manage && examDays.length > 0 && (
                                        <div className="grid gap-3">
                                            <SlotPicker
                                                days={examDays}
                                                value={session}
                                                onValueChange={setSession}
                                            />
                                            <div>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    disabled={
                                                        processing ||
                                                        session === undefined ||
                                                        session ===
                                                            selectedSession
                                                    }
                                                    onClick={() => {
                                                        setProcessing(true);
                                                        router.put(
                                                            examSession(
                                                                registrant.id,
                                                            ).url,
                                                            {
                                                                exam_session_id:
                                                                    session,
                                                            },
                                                            {
                                                                preserveScroll: true,
                                                                onFinish: () =>
                                                                    setProcessing(
                                                                        false,
                                                                    ),
                                                            },
                                                        );
                                                    }}
                                                >
                                                    Ubah jadwal
                                                </Button>
                                            </div>
                                        </div>
                                    )}
                                </div>

                                {result.aspects.length > 0 ? (
                                    <ExamScoresForm
                                        key={JSON.stringify(scores)}
                                        registrantId={registrant.id}
                                        aspects={result.aspects}
                                        initial={scores}
                                        canEdit={can.manage}
                                    />
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Aturan pendaftaran ini tidak punya aspek
                                        ujian.
                                    </p>
                                )}
                            </div>
                        </SectionCard>

                        {announcement && (
                            <SectionCard
                                title="Pengumuman"
                                description={
                                    announcement.recipient
                                        ? `Dikirim ke kontak utama: ${announcement.recipient.name} (${announcement.recipient.phone}).`
                                        : 'Belum ada kontak utama dengan nomor HP, jadi teks hanya bisa disalin.'
                                }
                                icon={Megaphone}
                            >
                                <div className="grid gap-4">
                                    {settlement.has_bill &&
                                        !settlement.paid && (
                                            <Alert variant="info">
                                                <Wallet />
                                                <AlertDescription>
                                                    Tagihan pelunasan belum
                                                    lunas.
                                                </AlertDescription>
                                            </Alert>
                                        )}
                                    <CopyableText
                                        text={announcement.text}
                                        whatsappHref={announcement.whatsapp_url}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Isi teks bisa diubah di templat
                                        pengumuman.
                                    </p>
                                </div>
                            </SectionCard>
                        )}

                        {student && (
                            <SectionCard
                                title="Penempatan"
                                icon={GraduationCap}
                            >
                                <DescriptionList
                                    items={[
                                        {
                                            label: 'Tahun ajaran',
                                            value: student.year,
                                        },
                                        { label: 'NIS', value: student.nis },
                                    ]}
                                />
                            </SectionCard>
                        )}
                    </div>

                    <div className="grid content-start gap-6">
                        <SectionCard title="Keluarga" icon={Users}>
                            {family.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada orang tua atau wali yang tertaut.
                                </p>
                            ) : (
                                <ul className="grid gap-3">
                                    {family.map((member) => (
                                        <li
                                            key={member.id}
                                            className="grid gap-0.5 text-sm"
                                        >
                                            <span className="flex flex-wrap items-center gap-2">
                                                <Link
                                                    href={editPerson(
                                                        member.person_id,
                                                    )}
                                                    className="font-semibold hover:underline"
                                                >
                                                    {member.name}
                                                </Link>
                                                <Badge variant="muted">
                                                    {member.relation}
                                                </Badge>
                                                {member.is_primary_contact && (
                                                    <Badge variant="info">
                                                        Kontak utama
                                                    </Badge>
                                                )}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {member.phone ??
                                                    'Belum ada nomor HP'}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </SectionCard>

                        <SectionCard title="Tagihan" icon={Wallet}>
                            {bills.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada tagihan terbit.
                                </p>
                            ) : (
                                <ul className="grid gap-3">
                                    {bills.map((bill) => (
                                        <li
                                            key={bill.id}
                                            className="flex items-center justify-between gap-3 text-sm"
                                        >
                                            <span className="grid">
                                                <span className="font-semibold">
                                                    {bill.type_label}
                                                </span>
                                                <span className="text-muted-foreground tabular-nums">
                                                    {formatRupiah(bill.paid)}{' '}
                                                    dari{' '}
                                                    {formatRupiah(bill.total)}
                                                </span>
                                            </span>
                                            <StatusBadge status={bill.status} />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </SectionCard>

                        <SectionCard title="Riwayat status" icon={History}>
                            <StatusTimeline entries={timeline} />
                        </SectionCard>
                    </div>
                </div>

                <div>
                    <Button variant="ghost" asChild>
                        <Link href={index()}>Kembali ke daftar pendaftar</Link>
                    </Button>
                </div>
            </div>

            <ReviseDialog
                registrantId={registrant.id}
                open={revising}
                onOpenChange={setRevising}
            />
            <BypassDialog
                registrantId={registrant.id}
                open={bypassing}
                onOpenChange={setBypassing}
                statuses={statuses}
            />
            <PlaceDialog
                registrantId={registrant.id}
                open={placing}
                onOpenChange={setPlacing}
                years={years}
            />
            <ConfirmDialog
                open={accepting}
                onOpenChange={setAccepting}
                title="Terima data ini?"
                description={
                    settlement.has_bill
                        ? 'Data dinyatakan valid dan tagihan pelunasan langsung terbit untuk orang tua.'
                        : 'Data dinyatakan valid dan pendaftaran lanjut ke tahap pengumuman.'
                }
                confirmLabel="Ya, terima"
                processing={processing}
                onConfirm={() =>
                    post(accept(registrant.id).url, () => setAccepting(false))
                }
            />
            <ConfirmDialog
                open={resolving}
                onOpenChange={setResolving}
                title="NIK sudah cocok?"
                description="Orang tua yang mendaftarkan akan tertaut ke anak ini dan bisa melihat serta mengisi datanya."
                confirmLabel="Ya, sudah cocok"
                processing={processing}
                onConfirm={() =>
                    post(resolveIdentity(registrant.id).url, () =>
                        setResolving(false),
                    )
                }
            />
        </>
    );
}

RegistrantShow.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Pendaftar', href: index() },
        { title: 'Detail', href: '#' },
    ],
};
