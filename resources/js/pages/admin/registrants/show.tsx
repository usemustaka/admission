import { Head, Link, router } from '@inertiajs/react';
import {
    CalendarDays,
    FileText,
    GraduationCap,
    HandHeart,
    History,
    Megaphone,
    Printer,
    ShieldAlert,
    StickyNote,
    Undo2,
    Wallet,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { CancelPlacementDialog } from '@/components/academic/cancel-placement-dialog';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { CopyableText } from '@/components/copyable-text';
import { DetailColumn, DetailColumns } from '@/components/detail-columns';
import { DocumentsCard } from '@/components/registrants/documents-card';
import type { CommitteeDocuments } from '@/components/registrants/documents-card';
import { ScholarshipCard } from '@/components/registrants/scholarship-card';
import type { ScholarshipData } from '@/components/registrants/scholarship-card';
import { DescriptionList } from '@/components/description-list';
import { NoteCard } from '@/components/note-card';
import type { NoteUrgency, NoteVisibility } from '@/components/note-card';
import { NoteForm } from '@/components/people/note-form';
import { QuickView } from '@/components/quick-view';
import { NextStepCard } from '@/components/registrants/next-step-card';
import { AnnounceWarnings } from '@/components/registrants/announce-warnings';
import { ContactCard } from '@/components/registrants/contact-card';
import type { ParentAccountData } from '@/components/registrants/contact-card';
import { OverviewCard } from '@/components/registrants/overview-card';
import { PaymentsCard } from '@/components/registrants/payments-card';
import type { RegistrantPayment } from '@/components/registrants/payments-card';
import {
    ChildDataCard,
    EducationCard,
    ParentsCard,
} from '@/components/registrants/registrant-data-view';
import type { RegistrantDetailData } from '@/components/registrants/registrant-data-view';
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
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { toastFirstError } from '@/hooks/use-flash-toast';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import { edit as editPerson, show as showPerson } from '@/routes/admin/people';
import registrantRoutes from '@/routes/admin/registrants';
import {
    accept,
    announce,
    index,
    resetPassword,
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
    ruleset: string | null;
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
    prints,
    payments,
    documents,
    scholarship,
    parentAccount,
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
    /** Uploaded files, or null while the NIK check is open. */
    /** Payments that touched this child's bills, for staff who may see payments. */
    payments: RegistrantPayment[];
    /** Which printable papers exist for this registration right now. */
    prints: { letter: boolean; settlement_receipt: boolean };
    documents: CommitteeDocuments | null;
    /** Evidence and the grant action, only for staff who may grant. */
    scholarship: ScholarshipData | null;
    /** The parent's login, when the staff member may send a password reset link. */
    parentAccount: ParentAccountData | null;
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
    const [resettingPassword, setResettingPassword] = useState(false);
    const [changingSession, setChangingSession] = useState(false);
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

    // A saved change closes the picker again.
    useEffect(() => setChangingSession(false), [selectedSession]);

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

    return (
        <>
            <Head title={registrant.child.name} />
            <div className="grid gap-4">
                <OverviewCard
                    registrant={registrant}
                    admissionTypeLabel={data.admission_type_label}
                    student={student}
                    queue={queue}
                    queueContext={queueContext}
                    canViewPerson={access.person}
                />

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

                <DetailColumns>
                    <DetailColumn>
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

                        <SectionCard title="Ujian" icon={CalendarDays} compact>
                            <div className="grid gap-5">
                                <div className="grid gap-3">
                                    <p className="text-sm">
                                        Jadwal:{' '}
                                        <span className="font-semibold">
                                            {chosenSession
                                                ? `${formatDate(chosenSession.date, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}, ${chosenSession.label}`
                                                : 'belum memilih'}
                                        </span>
                                    </p>
                                    {can.manage &&
                                        examDays.length > 0 &&
                                        (chosenSession && !changingSession ? (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="justify-self-start"
                                                onClick={() =>
                                                    setChangingSession(true)
                                                }
                                            >
                                                Ubah jadwal
                                            </Button>
                                        ) : (
                                            <ExamSessionForm
                                                registrantId={registrant.id}
                                                days={examDays}
                                                selected={selectedSession}
                                            />
                                        ))}
                                </div>

                                {result.aspects.length > 0 ? (
                                    <ExamScoresForm
                                        key={JSON.stringify(scores)}
                                        registrantId={registrant.id}
                                        aspects={result.aspects}
                                        initial={scores}
                                        canEdit={can.score}
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
                                compact
                                actions={
                                    prints.letter && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <a
                                                href={registrantRoutes.letter.url(
                                                    registrant.id,
                                                )}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <Printer />
                                                Surat penerimaan
                                            </a>
                                        </Button>
                                    )
                                }
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
                                compact
                            >
                                <DescriptionList
                                    compact
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

                        {access.notes && (
                            <SectionCard
                                title="Catatan"
                                icon={StickyNote}
                                compact
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
                                    <p className="text-sm text-muted-foreground">
                                        Belum ada catatan yang boleh kamu baca.
                                    </p>
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

                        <SectionCard
                            title="Riwayat status"
                            icon={History}
                            compact
                        >
                            <StatusTimeline entries={timeline} />
                        </SectionCard>
                    </DetailColumn>

                    <DetailColumn>
                        <ChildDataCard
                            data={data}
                            editHref={edit(registrant.id).url}
                            canEdit={can.manage}
                        />

                        <ParentsCard
                            data={data}
                            canViewPerson={access.person}
                        />

                        <EducationCard data={data} />

                        <ContactCard
                            contact={contact}
                            hasFamily={family.length > 0}
                            identityPending={registrant.needs_identity_review}
                            messages={messages}
                            account={parentAccount}
                            onResetPassword={() => setResettingPassword(true)}
                        />
                    </DetailColumn>

                    <DetailColumn wide>
                        <PaymentsCard
                            bills={bills}
                            payments={payments}
                            canViewPayments={access.payments}
                            settlementReceiptHref={
                                prints.settlement_receipt
                                    ? registrantRoutes.settlementReceipt.url(
                                          registrant.id,
                                      )
                                    : null
                            }
                        />

                        {documents && (
                            <SectionCard title="Berkas" icon={FileText} compact>
                                <DocumentsCard
                                    registrantId={registrant.id}
                                    documents={documents}
                                />
                            </SectionCard>
                        )}

                        {scholarship && (
                            <SectionCard
                                title="Beasiswa"
                                icon={HandHeart}
                                compact
                            >
                                <ScholarshipCard
                                    registrantId={registrant.id}
                                    data={scholarship}
                                />
                            </SectionCard>
                        )}
                    </DetailColumn>
                </DetailColumns>
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
                open={resettingPassword}
                onOpenChange={setResettingPassword}
                title="Kirim tautan atur ulang password?"
                description={`Orang tua menerima email berisi tautan untuk memilih password baru di ${parentAccount?.email ?? 'emailnya'}. Password lama tetap berlaku sampai mereka memakai tautan itu.`}
                confirmLabel="Ya, kirim tautan"
                processing={processing}
                onConfirm={() =>
                    post(resetPassword(registrant.id).url, {
                        onFinish: () => setResettingPassword(false),
                    })
                }
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
