import { Head, Link, router } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    GraduationCap,
    History,
    ListOrdered,
    Megaphone,
    MessageCircle,
    Phone,
    ShieldAlert,
    StickyNote,
    Undo2,
    Users,
    Wallet,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { CancelPlacementDialog } from '@/components/academic/cancel-placement-dialog';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { CopyableText } from '@/components/copyable-text';
import { DescriptionList } from '@/components/description-list';
import { EmptyState } from '@/components/empty-state';
import { NoteCard } from '@/components/note-card';
import type { NoteUrgency, NoteVisibility } from '@/components/note-card';
import { PageHeader } from '@/components/page-header';
import { PersonAvatar } from '@/components/person-avatar';
import { NoteForm } from '@/components/people/note-form';
import { QuickView } from '@/components/quick-view';
import { MessageLinks } from '@/components/registrants/message-links';
import { NextStepCard } from '@/components/registrants/next-step-card';
import { AnnounceWarnings } from '@/components/registrants/announce-warnings';
import { RegistrantDataView } from '@/components/registrants/registrant-data-view';
import type { RegistrantDetailData } from '@/components/registrants/registrant-data-view';
import { RelativeTime } from '@/components/registrants/relative-time';
import { ExamScoresForm } from '@/components/registrants/exam-scores-form';
import { StatusTimeline } from '@/components/registrants/status-timeline';
import {
    BypassDialog,
    PlaceDialog,
    RepointIdentityDialog,
    ReviseDialog,
} from '@/components/registrants/workflow-dialogs';
import { SectionCard } from '@/components/section-card';
import { ExamSessionForm } from '@/components/registrants/exam-session-form';
import type { SlotDay } from '@/components/slot-picker';
import { StatusBadge } from '@/components/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { toastFirstError } from '@/hooks/use-flash-toast';
import { formatDate, formatRupiah, whatsappUrl } from '@/lib/format';
import { dashboard } from '@/routes';
import { edit as editPerson, show as showPerson } from '@/routes/admin/people';
import { index as paymentsIndex } from '@/routes/admin/payments';
import {
    accept,
    announce,
    index,
    resolveIdentity,
    show,
} from '@/routes/admin/registrants';
import { edit } from '@/routes/admin/registrants/data';
import type { BillData } from '@/types/billing';
import type { Option } from '@/types/people';
import type {
    ChosenSession,
    ExamResultData,
    RegistrantAbilities,
    RegistrantMessage,
    RegistrantQueue,
    RegistrantQueueContext,
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
    child: {
        id: number;
        name: string;
        nik: string | null;
        photo_url: string | null;
    };
};

type RegistrantNote = {
    id: number;
    body: string;
    urgency: NoteUrgency;
    visibility: NoteVisibility;
    shared_with_family: boolean;
    created_at: string | null;
    author: { name: string; photo_url: string | null };
    shared_with: string[];
};

type NoteOptions = {
    urgencies: Option[];
    visibilities: Option[];
    users: Option<number>[];
    roles: Option<number>[];
};

export default function RegistrantShow({
    registrant,
    data,
    family,
    contact,
    messages,
    examSession: chosenSession,
    examDays,
    selectedSession,
    bills,
    result,
    scores,
    readiness,
    timeline,
    announceWarnings,
    announcement,
    student,
    years,
    statuses,
    settlement,
    can,
    access,
    notes,
    noteOptions,
    queue,
    queueContext,
}: {
    registrant: Detail;
    data: RegistrantDetailData;
    contact: { name: string; phone: string; label: string } | null;
    family: {
        id: number;
        person_id: number;
        name: string;
        relation: string;
        phone: string | null;
        is_primary_contact: boolean;
    }[];
    messages: RegistrantMessage[];
    examSession: ChosenSession | null;
    examDays: SlotDay[];
    selectedSession: string | null;
    bills: BillData[];
    result: ExamResultData;
    scores: (number | null)[];
    readiness: string[];
    timeline: TimelineEntry[];
    announceWarnings: string[];
    announcement: {
        text: string;
        recipient: { name: string; phone: string } | null;
        whatsapp_url: string | null;
    } | null;
    student: { id: number; nis: string | null; year: string | null } | null;
    years: { value: number; label: string; is_active: boolean }[];
    statuses: Option[];
    settlement: { has_bill: boolean; paid: boolean };
    can: RegistrantAbilities;
    access: {
        person: boolean;
        payments: boolean;
        notes: boolean;
        createNote: boolean;
    };
    notes: RegistrantNote[];
    noteOptions: NoteOptions | null;
    queue: RegistrantQueue;
    queueContext: RegistrantQueueContext;
}) {
    const [revising, setRevising] = useState(false);
    const [bypassing, setBypassing] = useState(false);
    const [placing, setPlacing] = useState(false);
    const [cancellingPlacement, setCancellingPlacement] = useState(false);
    const [accepting, setAccepting] = useState<'stay' | 'next' | null>(null);
    const [announcing, setAnnouncing] = useState<'stay' | 'next' | null>(null);
    const [resolving, setResolving] = useState(false);
    const [repointing, setRepointing] = useState(false);
    const [writingNote, setWritingNote] = useState(false);
    const [processing, setProcessing] = useState(false);

    function post(
        url: string,
        {
            onFinish,
            afterSuccess,
        }: { onFinish?: () => void; afterSuccess?: () => void } = {},
    ) {
        setProcessing(true);
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onSuccess: afterSuccess,
                onError: (errors) => toastFirstError(errors),
                onFinish: () => {
                    setProcessing(false);
                    onFinish?.();
                },
            },
        );
    }

    /**
     * After a step succeeds the registrant leaves its queue, so "and next" moves on to who was next in line.
     */
    function openNext() {
        if (queue.next_id !== null) {
            router.visit(show(queue.next_id, { query: queueContext.query }));
        }
    }

    /**
     * j and k walk the queue, a opens the accept dialog. They stay out of the way while typing or while a dialog
     * or panel is open.
     */
    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            if (
                event.defaultPrevented ||
                event.metaKey ||
                event.ctrlKey ||
                event.altKey
            ) {
                return;
            }

            const target = event.target as HTMLElement | null;

            if (
                target?.closest(
                    'input, textarea, select, [contenteditable="true"]',
                ) ||
                document.querySelector('[role="dialog"], [role="alertdialog"]')
            ) {
                return;
            }

            if (event.key === 'j' && queue.next_id !== null) {
                router.visit(
                    show(queue.next_id, { query: queueContext.query }),
                );
            } else if (event.key === 'k' && queue.previous_id !== null) {
                router.visit(
                    show(queue.previous_id, { query: queueContext.query }),
                );
            } else if (event.key === 'a' && can.accept) {
                setAccepting('stay');
            }
        }

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [queue.next_id, queue.previous_id, queueContext.query, can.accept]);

    const primaryContact = family.find((member) => member.is_primary_contact);

    return (
        <>
            <Head title={registrant.child.name} />
            <div className="grid gap-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <PersonAvatar
                        name={registrant.child.name}
                        src={registrant.child.photo_url}
                        size="xl"
                    />
                    <PageHeader
                        title={registrant.child.name}
                        description={`${registrant.organization} · ${registrant.wave}`}
                        meta={
                            <div className="flex flex-wrap items-center gap-2 pt-1">
                                <StatusBadge status={registrant.status} />
                                {registrant.needs_identity_review && (
                                    <Badge variant="danger">
                                        <ShieldAlert />
                                        Cek NIK
                                    </Badge>
                                )}
                                <span className="text-sm text-muted-foreground">
                                    Mendaftar{' '}
                                    <RelativeTime
                                        value={registrant.registered_at}
                                    />
                                </span>
                                {access.person && (
                                    <Link
                                        href={showPerson(registrant.child.id)}
                                        className="text-sm font-semibold underline underline-offset-4"
                                    >
                                        Buka profil anak
                                    </Link>
                                )}
                            </div>
                        }
                    />
                </div>

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
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 @5xl:grid-cols-[minmax(0,1fr)_24rem] @5xl:items-start">
                    <div className="@5xl:col-start-2 @5xl:row-start-1">
                        <NextStepCard
                            status={registrant.status}
                            needsIdentityReview={
                                registrant.needs_identity_review
                            }
                            can={can}
                            readiness={readiness}
                            hasNext={queue.next_id !== null}
                            processing={processing}
                            editHref={edit(registrant.id).url}
                            correctIdentityHref={
                                editPerson(registrant.child.id).url
                            }
                            whatsappUrl={announcement?.whatsapp_url ?? null}
                            onAccept={(goNext) =>
                                setAccepting(goNext ? 'next' : 'stay')
                            }
                            onAnnounce={(goNext) =>
                                setAnnouncing(goNext ? 'next' : 'stay')
                            }
                            onRevise={() => setRevising(true)}
                            onPlace={() => setPlacing(true)}
                            onBypass={() => setBypassing(true)}
                            onResolveIdentity={() => setResolving(true)}
                            onRepointIdentity={() => setRepointing(true)}
                        />
                    </div>

                    <div className="grid content-start gap-6 @5xl:col-start-1 @5xl:row-span-2 @5xl:row-start-1">
                        <RegistrantDataView
                            data={data}
                            editHref={edit(registrant.id).url}
                            canEdit={can.manage}
                            canViewPerson={access.person}
                        />

                        {access.notes && (
                            <SectionCard
                                title="Catatan"
                                icon={StickyNote}
                                description="Catatan tentang anak ini yang boleh kamu baca, yang mendesak di atas. Catatan sistem seperti Cek NIK ikut tampil."
                                actions={
                                    access.createNote &&
                                    noteOptions && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setWritingNote(true)}
                                        >
                                            <StickyNote />
                                            Tulis catatan
                                        </Button>
                                    )
                                }
                                contentClassName="grid gap-3"
                            >
                                {notes.length === 0 ? (
                                    <EmptyState
                                        icon={StickyNote}
                                        title="Belum ada catatan"
                                        description="Catatan yang boleh kamu baca akan muncul di sini."
                                    />
                                ) : (
                                    notes.map((note) => (
                                        <NoteCard
                                            key={note.id}
                                            authorName={note.author.name}
                                            authorPhoto={note.author.photo_url}
                                            createdAt={note.created_at ?? ''}
                                            body={note.body}
                                            urgency={note.urgency}
                                            visibility={note.visibility}
                                            sharedWith={note.shared_with}
                                            sharedWithFamily={
                                                note.shared_with_family
                                            }
                                        />
                                    ))
                                )}
                                {access.person && notes.length > 0 && (
                                    <Link
                                        href={showPerson(registrant.child.id)}
                                        className="w-fit text-sm font-semibold underline underline-offset-4"
                                    >
                                        Ubah atau hapus catatan di profil anak
                                    </Link>
                                )}
                            </SectionCard>
                        )}

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
                                        <ExamSessionForm
                                            registrantId={registrant.id}
                                            days={examDays}
                                            selected={selectedSession}
                                        />
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
                                {can.cancel_placement && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="mt-4 max-md:h-10"
                                        onClick={() =>
                                            setCancellingPlacement(true)
                                        }
                                    >
                                        <Undo2 />
                                        Batalkan penempatan
                                    </Button>
                                )}
                            </SectionCard>
                        )}

                        <SectionCard title="Riwayat status" icon={History}>
                            <StatusTimeline entries={timeline} />
                        </SectionCard>
                    </div>

                    <div className="grid content-start gap-6 @5xl:col-start-2 @5xl:row-start-2">
                        <SectionCard
                            title="Berikutnya dalam antrean"
                            icon={ListOrdered}
                            description={`${queue.position} dari ${queue.total} yang berstatus ${registrant.status_label.toLowerCase()}${Object.keys(queueContext.query).length > 0 ? ' sesuai filter daftar' : ''}, urut ${queueContext.order}.`}
                            contentClassName="grid grid-cols-2 gap-2"
                        >
                            {queue.previous_id !== null ? (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={show(queue.previous_id, {
                                            query: queueContext.query,
                                        })}
                                    >
                                        <ChevronLeft />
                                        Sebelumnya
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" disabled>
                                    <ChevronLeft />
                                    Sebelumnya
                                </Button>
                            )}
                            {queue.next_id !== null ? (
                                <Button variant="dark" asChild>
                                    <Link
                                        href={show(queue.next_id, {
                                            query: queueContext.query,
                                        })}
                                    >
                                        Berikutnya
                                        <ChevronRight />
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="dark" disabled>
                                    Berikutnya
                                    <ChevronRight />
                                </Button>
                            )}
                            <p className="col-span-2 text-xs text-muted-foreground">
                                Pintasan papan ketik:{' '}
                                <kbd className="rounded border border-line px-1 font-mono">
                                    j
                                </kbd>{' '}
                                berikutnya,{' '}
                                <kbd className="rounded border border-line px-1 font-mono">
                                    k
                                </kbd>{' '}
                                sebelumnya
                                {can.accept && (
                                    <>
                                        ,{' '}
                                        <kbd className="rounded border border-line px-1 font-mono">
                                            a
                                        </kbd>{' '}
                                        terima data
                                    </>
                                )}
                                .
                            </p>
                        </SectionCard>

                        <SectionCard title="Keluarga" icon={Users}>
                            {registrant.needs_identity_review ? (
                                <p className="text-sm text-muted-foreground">
                                    {contact
                                        ? `${contact.label}: ${contact.name}, ${contact.phone}. `
                                        : ''}
                                    Keluarga yang tercatat disembunyikan sampai
                                    NIK dicocokkan.
                                </p>
                            ) : family.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada orang tua atau wali yang tertaut.
                                </p>
                            ) : (
                                <ul className="grid gap-4">
                                    {family.map((member) => (
                                        <li
                                            key={member.id}
                                            className="grid gap-1.5 text-sm"
                                        >
                                            <span className="flex flex-wrap items-center gap-2">
                                                {access.person ? (
                                                    <Link
                                                        href={showPerson(
                                                            member.person_id,
                                                        )}
                                                        className="font-semibold hover:underline"
                                                    >
                                                        {member.name}
                                                    </Link>
                                                ) : (
                                                    <span className="font-semibold">
                                                        {member.name}
                                                    </span>
                                                )}
                                                <Badge variant="muted">
                                                    {member.relation}
                                                </Badge>
                                                {member.is_primary_contact && (
                                                    <Badge variant="info">
                                                        Kontak utama
                                                    </Badge>
                                                )}
                                            </span>
                                            {member.phone ? (
                                                <span className="flex items-center gap-1.5">
                                                    <span className="text-muted-foreground tabular-nums">
                                                        {member.phone}
                                                    </span>
                                                    <Button
                                                        variant="outline"
                                                        size="icon"
                                                        className="size-10 md:size-8"
                                                        asChild
                                                    >
                                                        <a
                                                            href={`tel:${member.phone}`}
                                                            aria-label={`Telepon ${member.name}`}
                                                        >
                                                            <Phone />
                                                        </a>
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="icon"
                                                        className="size-10 md:size-8"
                                                        asChild
                                                    >
                                                        <a
                                                            href={whatsappUrl(
                                                                member.phone,
                                                            )}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            aria-label={`WhatsApp ${member.name}`}
                                                        >
                                                            <MessageCircle />
                                                        </a>
                                                    </Button>
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    Belum ada nomor HP
                                                </span>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {primaryContact === undefined &&
                                family.length > 0 &&
                                !registrant.needs_identity_review && (
                                    <p className="pt-3 text-xs text-muted-foreground">
                                        Belum ada kontak utama, jadi pengumuman
                                        tidak bisa dikirim otomatis.
                                    </p>
                                )}
                            {messages.length > 0 && (
                                <div className="border-t pt-4">
                                    <MessageLinks messages={messages} />
                                </div>
                            )}
                        </SectionCard>

                        <SectionCard
                            title="Tagihan"
                            icon={Wallet}
                            actions={
                                access.payments && (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={paymentsIndex({
                                                query: {
                                                    cari: registrant.child.name,
                                                },
                                            })}
                                        >
                                            Lihat pembayaran
                                        </Link>
                                    </Button>
                                )
                            }
                        >
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
                    </div>
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
            {cancellingPlacement && student && (
                <CancelPlacementDialog
                    studentId={student.id}
                    childName={registrant.child.name}
                    onClose={() => setCancellingPlacement(false)}
                />
            )}
            <RepointIdentityDialog
                registrantId={registrant.id}
                open={repointing}
                onOpenChange={setRepointing}
                typedName={registrant.submitted_name}
            />
            {access.createNote && noteOptions && (
                <QuickView
                    open={writingNote}
                    onOpenChange={setWritingNote}
                    title="Tulis catatan"
                    description="Catatan untuk sesama panitia"
                >
                    <p className="mb-5 text-sm text-muted-foreground">
                        Catatan untuk sesama panitia. Hanya yang kamu izinkan
                        yang bisa membacanya.
                    </p>
                    <NoteForm
                        personId={registrant.child.id}
                        urgencies={noteOptions.urgencies}
                        visibilities={noteOptions.visibilities}
                        users={noteOptions.users}
                        roles={noteOptions.roles}
                        onSaved={() => setWritingNote(false)}
                    />
                </QuickView>
            )}
            <PlaceDialog
                registrantId={registrant.id}
                open={placing}
                onOpenChange={setPlacing}
                years={years}
            />
            <ConfirmDialog
                open={accepting !== null}
                onOpenChange={(open) => !open && setAccepting(null)}
                title="Terima data ini?"
                description={
                    settlement.has_bill
                        ? 'Data dinyatakan valid dan tagihan pelunasan langsung terbit untuk orang tua.'
                        : 'Data dinyatakan valid dan pendaftaran lanjut ke tahap pengumuman.'
                }
                confirmLabel={
                    accepting === 'next'
                        ? 'Ya, terima dan lanjut'
                        : 'Ya, terima'
                }
                processing={processing}
                onConfirm={() =>
                    post(accept(registrant.id).url, {
                        afterSuccess:
                            accepting === 'next' ? openNext : undefined,
                        onFinish: () => setAccepting(null),
                    })
                }
            />
            <ConfirmDialog
                open={announcing !== null}
                onOpenChange={(open) => !open && setAnnouncing(null)}
                title="Tandai sudah diumumkan?"
                description="Pastikan pengumumannya sudah dikirim ke kontak utama. Setelah ditandai, pendaftar lanjut ke tahap penempatan."
                confirmLabel={
                    announcing === 'next'
                        ? 'Ya, tandai dan lanjut'
                        : 'Ya, tandai'
                }
                processing={processing}
                onConfirm={() =>
                    post(announce(registrant.id).url, {
                        afterSuccess:
                            announcing === 'next' ? openNext : undefined,
                        onFinish: () => setAnnouncing(null),
                    })
                }
            >
                <AnnounceWarnings warnings={announceWarnings} />
            </ConfirmDialog>
            <ConfirmDialog
                open={resolving}
                onOpenChange={setResolving}
                title="NIK sudah cocok?"
                description="Orang tua yang mendaftarkan akan tertaut ke anak ini dan bisa melihat serta mengisi datanya."
                confirmLabel="Ya, sudah cocok"
                processing={processing}
                onConfirm={() =>
                    post(resolveIdentity(registrant.id).url, {
                        onFinish: () => setResolving(false),
                    })
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
