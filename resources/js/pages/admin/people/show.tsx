import { Head, Link, router } from '@inertiajs/react';
import {
    MessageCircle,
    Pencil,
    Phone,
    Star,
    StickyNote,
    Users,
    Trash2,
    TriangleAlert,
    UserPlus,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DetailColumn, DetailColumns } from '@/components/detail-columns';
import { DuplicateCandidates } from '@/components/people/duplicate-candidates';
import {
    AccountCard,
    birthLine,
    ChecklistCard,
    FinanceCard,
    ProfileCard,
    RegistrationsCard,
    RolesCard,
    SchoolsCard,
    SiblingsCard,
} from '@/components/people/person-profile-cards';
import { Card, CardContent } from '@/components/ui/card';
import { DescriptionList } from '@/components/description-list';
import { NoteCard } from '@/components/note-card';
import { PersonAvatar } from '@/components/person-avatar';
import { QuickView } from '@/components/quick-view';
import { AddParentDialog } from '@/components/people/add-parent-dialog';
import { NoteForm } from '@/components/people/note-form';
import { WhatsAppLink } from '@/components/people/whatsapp-link';
import { SectionCard } from '@/components/section-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { isCompleteNik, whatsappUrl } from '@/lib/format';
import { dashboard } from '@/routes';
import { edit, index, show } from '@/routes/admin/people';
import { destroy, update } from '@/routes/admin/notes';
import {
    destroy as destroyParentship,
    update as makePrimary,
} from '@/routes/admin/people/parentships';
import type {
    ChildLink,
    DuplicateCandidate,
    NoteItem,
    Option,
    ParentLink,
    PersonDetail,
    PersonFinance,
    PersonProfileData,
    PersonRegistration,
} from '@/types/people';

type PendingDelete =
    | { kind: 'parent'; id: number; name: string; isPrimaryContact: boolean }
    | { kind: 'note'; id: number }
    | null;

export default function PersonShow({
    person,
    parents,
    dependents,
    notes,
    roles,
    registrations,
    profile,
    finance,
    duplicates,
    can,
    options,
}: {
    person: PersonDetail;
    parents: ParentLink[];
    dependents: ChildLink[];
    notes: NoteItem[];
    roles: string[];
    registrations: PersonRegistration[];
    profile: PersonProfileData;
    finance: PersonFinance | null;
    duplicates: DuplicateCandidate[];
    can: {
        viewAccount: boolean;
        viewPayments: boolean;
        update: boolean;
        manageFamily: boolean;
        createNote: boolean;
        viewRegistrations: boolean;
    };
    options: {
        relations: Option[];
        kinds: Option[];
        urgencies: Option[];
        visibilities: Option[];
        users: Option<number>[];
        roles: Option<number>[];
    };
}) {
    const [pendingDelete, setPendingDelete] = useState<PendingDelete>(null);
    const [pendingPrimary, setPendingPrimary] = useState<{
        id: number;
        name: string;
    } | null>(null);
    const [processing, setProcessing] = useState(false);
    const [noteOpen, setNoteOpen] = useState(false);

    const primaryParent = parents.find((parent) => parent.is_primary_contact);
    const contactPhone = person.phone ?? primaryParent?.person.phone ?? null;
    const isChild = roles.includes('Calon siswa') || roles.includes('Siswa');
    const checklist = [
        {
            label: 'NIK 16 digit',
            done: person.nik !== null && isCompleteNik(person.nik),
        },
        { label: 'Jenis kelamin', done: person.gender !== null },
        {
            label: 'Tempat dan tanggal lahir',
            done: person.birth_place !== null && person.birth_date !== null,
        },
        { label: 'Bisa dihubungi', done: contactPhone !== null },
        ...(isChild
            ? [
                  {
                      label: 'Orang tua atau wali tercatat',
                      done: parents.length > 0,
                  },
              ]
            : []),
    ];

    function writeNote() {
        setNoteOpen(true);
    }

    function makePrimaryContact(parentshipId: number) {
        router.put(
            makePrimary({ person: person.id, parentship: parentshipId }).url,
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setPendingPrimary(null);
                },
            },
        );
    }

    function confirmDelete() {
        if (!pendingDelete) {
            return;
        }

        const target =
            pendingDelete.kind === 'parent'
                ? destroyParentship({
                      person: person.id,
                      parentship: pendingDelete.id,
                  })
                : destroy(pendingDelete.id);

        router.delete(target.url, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setPendingDelete(null);
            },
        });
    }

    function toggleFamilyShare(note: NoteItem) {
        router.put(
            update(note.id).url,
            {
                body: note.body,
                urgency: note.urgency,
                visibility: note.visibility,
                shared_with_family: !note.shared_with_family,
                user_ids: note.user_ids,
                role_ids: note.role_ids,
            },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title={person.name} />
            <div className="grid gap-4">
                <Card className="gap-4 @2xl:gap-4">
                    <CardContent className="grid gap-5">
                        <div className="flex flex-col gap-4 @2xl:flex-row @2xl:items-start">
                            <div className="flex min-w-0 flex-1 items-start gap-4">
                                <PersonAvatar
                                    name={person.name}
                                    src={person.photo_url}
                                    size="xl"
                                    className="size-16 shrink-0 text-lg @2xl:size-20 @2xl:text-2xl"
                                />
                                <div className="grid min-w-0 flex-1 gap-1.5">
                                    <h1 className="text-xl font-semibold break-words @2xl:text-2xl">
                                        {person.name}
                                    </h1>
                                    <p className="text-sm text-muted-foreground tabular-nums">
                                        {person.nik ?? 'NIK belum diisi'}
                                    </p>
                                    <div className="flex flex-wrap gap-1.5">
                                        {roles.map((role) => (
                                            <Badge key={role}>{role}</Badge>
                                        ))}
                                        {!person.is_alive && (
                                            <Badge variant="muted">
                                                Almarhum
                                            </Badge>
                                        )}
                                        {profile.account && (
                                            <Badge variant="muted">
                                                Punya akun
                                            </Badge>
                                        )}
                                    </div>
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-2 @2xl:max-w-sm @2xl:justify-end">
                                {can.update && (
                                    <Button asChild>
                                        <Link href={edit(person.id)}>
                                            <Pencil />
                                            Ubah data
                                        </Link>
                                    </Button>
                                )}
                                {can.manageFamily && (
                                    <AddParentDialog
                                        childId={person.id}
                                        relations={options.relations}
                                        kinds={options.kinds}
                                        trigger={
                                            <Button variant="outline">
                                                <UserPlus />
                                                Tambah orang tua
                                            </Button>
                                        }
                                    />
                                )}
                                {can.createNote && (
                                    <Button
                                        variant="outline"
                                        onClick={writeNote}
                                    >
                                        <StickyNote />
                                        Tulis catatan
                                    </Button>
                                )}
                                {contactPhone && (
                                    <Button variant="outline" asChild>
                                        <a
                                            href={whatsappUrl(contactPhone)}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <MessageCircle />
                                            WhatsApp
                                        </a>
                                    </Button>
                                )}
                            </div>
                        </div>
                        <DescriptionList
                            compact
                            className="border-t border-line pt-4 @3xl:grid-cols-3"
                            items={[
                                {
                                    label: 'Jenis kelamin',
                                    value: person.gender,
                                },
                                {
                                    label: 'Tempat, tanggal lahir',
                                    value: birthLine(person),
                                },
                                {
                                    label: 'Alamat',
                                    value: profile.address,
                                },
                            ]}
                        />
                    </CardContent>
                </Card>

                {duplicates.length > 0 && (
                    <DuplicateCandidates
                        title="Kemungkinan ada catatan ganda"
                        message="Orang berikut mirip dengan yang sedang kamu lihat. Periksa apakah mereka orang yang sama sebelum data makin bercabang."
                        candidates={duplicates}
                        action={(candidate) => (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={show(candidate.id)}>Buka</Link>
                            </Button>
                        )}
                    />
                )}

                <DetailColumns>
                    <DetailColumn>
                        <ProfileCard person={person} profile={profile} />

                        <SectionCard title="Kontak" icon={Phone} compact>
                            {contactPhone || person.email ? (
                                <ul className="grid gap-3 text-sm">
                                    {person.phone && (
                                        <li className="flex items-center gap-3">
                                            <Phone className="size-4 shrink-0 text-muted-foreground" />
                                            <a
                                                href={`tel:${person.phone}`}
                                                className="min-w-0 flex-1 tabular-nums hover:underline"
                                            >
                                                {person.phone}
                                            </a>
                                            <WhatsAppLink
                                                phone={person.phone}
                                                label={person.name}
                                            />
                                        </li>
                                    )}
                                    {!person.phone &&
                                        primaryParent?.person.phone && (
                                            <li className="flex items-center gap-3">
                                                <Star className="size-4 shrink-0 text-lime-700 dark:text-lime-300" />
                                                <span className="grid min-w-0 flex-1 leading-tight">
                                                    <span className="truncate font-semibold">
                                                        {
                                                            primaryParent.person
                                                                .name
                                                        }
                                                    </span>
                                                    <span className="text-muted-foreground tabular-nums">
                                                        {primaryParent.relation}{' '}
                                                        ·{' '}
                                                        {
                                                            primaryParent.person
                                                                .phone
                                                        }
                                                    </span>
                                                </span>
                                                <WhatsAppLink
                                                    phone={
                                                        primaryParent.person
                                                            .phone
                                                    }
                                                    label={
                                                        primaryParent.person
                                                            .name
                                                    }
                                                />
                                            </li>
                                        )}
                                    {person.email && (
                                        <li className="break-all">
                                            <a
                                                href={`mailto:${person.email}`}
                                                className="hover:underline"
                                            >
                                                {person.email}
                                            </a>
                                        </li>
                                    )}
                                </ul>
                            ) : (
                                <Alert variant="warning">
                                    <TriangleAlert />
                                    <AlertTitle>Belum ada kontak</AlertTitle>
                                    <AlertDescription>
                                        Isi nomor HP lewat Ubah data, atau
                                        tentukan kontak utama keluarga.
                                    </AlertDescription>
                                </Alert>
                            )}
                        </SectionCard>

                        <AccountCard
                            profile={profile}
                            canViewAccount={can.viewAccount}
                        />
                    </DetailColumn>

                    <DetailColumn>
                        <ChecklistCard items={checklist} />

                        {isChild && <SchoolsCard profile={profile} />}

                        {(isChild || parents.length > 0) && (
                            <SectionCard
                                title="Orang tua dan wali"
                                description="Yang jadi kontak utama akan dihubungi untuk pengumuman."
                                icon={Users}
                                compact
                            >
                                {parents.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Belum ada orang tua atau wali yang
                                        tercatat.
                                    </p>
                                ) : (
                                    <ul className="grid divide-y divide-line">
                                        {parents.map((parent) => (
                                            <li
                                                key={parent.id}
                                                className="grid gap-2 py-3 first:pt-0 last:pb-0"
                                            >
                                                <div className="flex items-center gap-3">
                                                    <PersonAvatar
                                                        name={
                                                            parent.person.name
                                                        }
                                                        src={
                                                            parent.person
                                                                .photo_url
                                                        }
                                                    />
                                                    <div className="grid min-w-0 flex-1 leading-tight">
                                                        <Link
                                                            href={show(
                                                                parent.person
                                                                    .id,
                                                            )}
                                                            className="truncate font-semibold hover:underline"
                                                        >
                                                            {parent.person.name}
                                                        </Link>
                                                        <span className="text-sm text-muted-foreground">
                                                            {[
                                                                parent.relation,
                                                                parent.kind,
                                                                parent.person
                                                                    .phone,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(' · ')}
                                                        </span>
                                                    </div>
                                                    {parent.person.phone && (
                                                        <WhatsAppLink
                                                            phone={
                                                                parent.person
                                                                    .phone
                                                            }
                                                            label={
                                                                parent.person
                                                                    .name
                                                            }
                                                            variant="ghost"
                                                        />
                                                    )}
                                                </div>
                                                <div className="flex flex-wrap items-center gap-1">
                                                    {parent.is_primary_contact && (
                                                        <Badge variant="success">
                                                            <Star />
                                                            Kontak utama
                                                        </Badge>
                                                    )}
                                                    {can.manageFamily && (
                                                        <>
                                                            {!parent.is_primary_contact && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="max-md:h-10"
                                                                    onClick={() =>
                                                                        parent
                                                                            .person
                                                                            .phone
                                                                            ? makePrimaryContact(
                                                                                  parent.id,
                                                                              )
                                                                            : setPendingPrimary(
                                                                                  {
                                                                                      id: parent.id,
                                                                                      name: parent
                                                                                          .person
                                                                                          .name,
                                                                                  },
                                                                              )
                                                                    }
                                                                >
                                                                    Jadikan
                                                                    kontak utama
                                                                </Button>
                                                            )}
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`Hapus ${parent.person.name}`}
                                                                onClick={() =>
                                                                    setPendingDelete(
                                                                        {
                                                                            kind: 'parent',
                                                                            id: parent.id,
                                                                            name: parent
                                                                                .person
                                                                                .name,
                                                                            isPrimaryContact:
                                                                                parent.is_primary_contact,
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 />
                                                            </Button>
                                                        </>
                                                    )}
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </SectionCard>
                        )}

                        {dependents.length > 0 && (
                            <SectionCard title="Anak yang diampu" compact>
                                <ul className="grid gap-1">
                                    {dependents.map((child) => (
                                        <li key={child.id}>
                                            <Link
                                                href={show(child.person.id)}
                                                className="-mx-2 flex min-h-12 items-center gap-3 rounded-xl px-2 py-1.5 hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring focus-visible:outline-none"
                                            >
                                                <PersonAvatar
                                                    name={child.person.name}
                                                    src={child.person.photo_url}
                                                    size="sm"
                                                />
                                                <span className="min-w-0 flex-1 truncate text-sm font-semibold">
                                                    {child.person.name}
                                                </span>
                                                <Badge variant="muted">
                                                    {child.relation}
                                                </Badge>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </SectionCard>
                        )}

                        <SiblingsCard profile={profile} />

                        <RolesCard
                            profile={profile}
                            canViewRegistrations={can.viewRegistrations}
                        />
                    </DetailColumn>

                    <DetailColumn wide>
                        {can.viewRegistrations && (
                            <RegistrationsCard registrations={registrations} />
                        )}

                        {finance && (
                            <FinanceCard
                                finance={finance}
                                canViewPayments={can.viewPayments}
                            />
                        )}

                        <SectionCard
                            title="Catatan"
                            icon={StickyNote}
                            compact
                            actions={
                                can.createNote && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={writeNote}
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
                                        sharedWith={note.shared_with.map(
                                            (share) => share.name,
                                        )}
                                        sharedWithFamily={
                                            note.shared_with_family
                                        }
                                        actions={
                                            (note.can_update ||
                                                note.can_delete) && (
                                                <>
                                                    {note.can_update && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="max-md:h-10"
                                                            onClick={() =>
                                                                toggleFamilyShare(
                                                                    note,
                                                                )
                                                            }
                                                        >
                                                            {note.shared_with_family
                                                                ? 'Berhenti share ke keluarga'
                                                                : 'Share ke keluarga anak'}
                                                        </Button>
                                                    )}
                                                    {note.can_delete && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label="Hapus catatan"
                                                            onClick={() =>
                                                                setPendingDelete(
                                                                    {
                                                                        kind: 'note',
                                                                        id: note.id,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <Trash2 />
                                                        </Button>
                                                    )}
                                                </>
                                            )
                                        }
                                    />
                                ))
                            )}
                        </SectionCard>
                    </DetailColumn>
                </DetailColumns>
            </div>

            {can.createNote && (
                <QuickView
                    open={noteOpen}
                    onOpenChange={setNoteOpen}
                    title="Tulis catatan"
                    description="Catatan untuk sesama panitia"
                >
                    <p className="mb-5 text-sm text-muted-foreground">
                        Catatan untuk sesama panitia. Hanya yang kamu izinkan
                        yang bisa membacanya.
                    </p>
                    <NoteForm
                        personId={person.id}
                        urgencies={options.urgencies}
                        visibilities={options.visibilities}
                        users={options.users}
                        roles={options.roles}
                        onSaved={() => setNoteOpen(false)}
                    />
                </QuickView>
            )}

            <ConfirmDialog
                open={pendingDelete !== null}
                onOpenChange={(open) => !open && setPendingDelete(null)}
                tone="danger"
                processing={processing}
                title={
                    pendingDelete?.kind === 'parent'
                        ? `Hapus ${pendingDelete.name} dari keluarga?`
                        : 'Hapus catatan ini?'
                }
                description={
                    pendingDelete?.kind === 'parent'
                        ? `${pendingDelete.name} tidak akan bisa lagi melihat data anak ini di portal orang tua.${
                              pendingDelete.isPrimaryContact
                                  ? ' Dia kontak utama, jadi kontak utama dialihkan ke orang tua lain yang punya nomor telepon dan penerima pengumuman WhatsApp bisa berubah.'
                                  : ' Penerima pengumuman WhatsApp bisa berubah jika dia satu-satunya yang punya nomor telepon.'
                          } Yang dihapus tidak bisa dikembalikan.`
                        : 'Yang dihapus tidak bisa dikembalikan.'
                }
                confirmLabel="Ya, hapus"
                onConfirm={confirmDelete}
            />

            <ConfirmDialog
                open={pendingPrimary !== null}
                onOpenChange={(open) => !open && setPendingPrimary(null)}
                processing={processing}
                title={`Jadikan ${pendingPrimary?.name ?? ''} kontak utama?`}
                description="Orang ini belum punya nomor telepon, pengumuman WhatsApp tidak bisa dikirim."
                confirmLabel="Tetap jadikan kontak utama"
                onConfirm={() =>
                    pendingPrimary && makePrimaryContact(pendingPrimary.id)
                }
            />
        </>
    );
}

PersonShow.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Orang', href: index() },
    ],
};
