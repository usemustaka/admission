import { Head, Link, router } from '@inertiajs/react';
import {
    Check,
    Circle,
    ClipboardList,
    MessageCircle,
    Pencil,
    Phone,
    Star,
    StickyNote,
    Trash2,
    TriangleAlert,
    UserPlus,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DescriptionList } from '@/components/description-list';
import { EmptyState } from '@/components/empty-state';
import { NoteCard } from '@/components/note-card';
import { PageHeader } from '@/components/page-header';
import { PersonAvatar } from '@/components/person-avatar';
import { QuickView } from '@/components/quick-view';
import { AddParentDialog } from '@/components/people/add-parent-dialog';
import { NoteForm } from '@/components/people/note-form';
import { WhatsAppLink } from '@/components/people/whatsapp-link';
import { SectionCard } from '@/components/section-card';
import { StatusBadge } from '@/components/status-badge';
import { ValueTabs } from '@/components/section-tabs';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { formatDate, isCompleteNik, whatsappUrl } from '@/lib/format';
import { dashboard } from '@/routes';
import { edit, index, show } from '@/routes/admin/people';
import { show as showRegistrant } from '@/routes/admin/registrants';
import { destroy, update } from '@/routes/admin/notes';
import {
    destroy as destroyParentship,
    update as makePrimary,
} from '@/routes/admin/people/parentships';
import type {
    ChildLink,
    NoteItem,
    Option,
    ParentLink,
    PersonDetail,
    PersonRegistration,
} from '@/types/people';

type Tab = 'data' | 'keluarga' | 'catatan';

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
    can,
    options,
}: {
    person: PersonDetail;
    parents: ParentLink[];
    dependents: ChildLink[];
    notes: NoteItem[];
    roles: string[];
    registrations: PersonRegistration[];
    can: { update: boolean; manageFamily: boolean; createNote: boolean };
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
    const [tab, setTab] = useState<Tab>('data');
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
    const completed = checklist.filter((item) => item.done).length;

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

    const parentCount = parents.length + dependents.length;

    return (
        <>
            <Head title={person.name} />
            <div className="grid gap-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <PersonAvatar
                        name={person.name}
                        src={person.photo_url}
                        size="xl"
                    />
                    <div className="min-w-0 flex-1">
                        <PageHeader
                            title={person.name}
                            description={person.nik ?? 'NIK belum diisi'}
                            meta={
                                roles.length > 0 && (
                                    <div className="flex flex-wrap gap-1.5 pt-1">
                                        {roles.map((role) => (
                                            <Badge key={role}>{role}</Badge>
                                        ))}
                                        {!person.is_alive && (
                                            <Badge variant="muted">
                                                Almarhum
                                            </Badge>
                                        )}
                                    </div>
                                )
                            }
                            actions={
                                <>
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
                                </>
                            }
                        />
                    </div>
                </div>

                <div className="grid gap-6 @5xl:grid-cols-[minmax(0,1fr)_24rem] @5xl:items-start">
                    <div className="grid min-w-0 gap-6">
                        <ValueTabs
                            label="Bagian data orang"
                            value={tab}
                            onChange={setTab}
                            items={[
                                { value: 'data', label: 'Data diri' },
                                {
                                    value: 'keluarga',
                                    label: 'Keluarga',
                                    count: parentCount,
                                },
                                {
                                    value: 'catatan',
                                    label: 'Catatan',
                                    count: notes.length,
                                },
                            ]}
                        />

                        {tab === 'data' && (
                            <SectionCard title="Data diri">
                                <DescriptionList
                                    items={[
                                        {
                                            label: 'Jenis kelamin',
                                            value: person.gender,
                                        },
                                        {
                                            label: 'Tempat lahir',
                                            value: person.birth_place,
                                        },
                                        {
                                            label: 'Tanggal lahir',
                                            value: person.birth_date
                                                ? formatDate(person.birth_date)
                                                : null,
                                        },
                                        {
                                            label: 'Nomor HP',
                                            value: person.phone,
                                        },
                                        { label: 'Email', value: person.email },
                                        {
                                            label: 'Status',
                                            value: person.is_alive
                                                ? 'Masih hidup'
                                                : 'Sudah meninggal',
                                        },
                                    ]}
                                />
                            </SectionCard>
                        )}

                        {tab === 'keluarga' && (
                            <>
                                <SectionCard
                                    title="Orang tua dan wali"
                                    description="Yang jadi kontak utama akan dihubungi untuk pengumuman."
                                    actions={
                                        can.manageFamily && (
                                            <AddParentDialog
                                                childId={person.id}
                                                relations={options.relations}
                                                kinds={options.kinds}
                                            />
                                        )
                                    }
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
                                                    className="flex flex-wrap items-center gap-3 py-3 first:pt-0 last:pb-0"
                                                >
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
                                                    {parent.is_primary_contact && (
                                                        <Badge variant="success">
                                                            <Star />
                                                            Kontak utama
                                                        </Badge>
                                                    )}
                                                    <div className="flex flex-wrap items-center gap-1">
                                                        {parent.person
                                                            .phone && (
                                                            <WhatsAppLink
                                                                phone={
                                                                    parent
                                                                        .person
                                                                        .phone
                                                                }
                                                                label={
                                                                    parent
                                                                        .person
                                                                        .name
                                                                }
                                                                variant="ghost"
                                                            />
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
                                                                        kontak
                                                                        utama
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

                                {dependents.length > 0 && (
                                    <SectionCard title="Anak yang diampu">
                                        <ul className="grid gap-1">
                                            {dependents.map((child) => (
                                                <li key={child.id}>
                                                    <Link
                                                        href={show(
                                                            child.person.id,
                                                        )}
                                                        className="-mx-2 flex min-h-12 items-center gap-3 rounded-xl px-2 py-1.5 hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring focus-visible:outline-none"
                                                    >
                                                        <PersonAvatar
                                                            name={
                                                                child.person
                                                                    .name
                                                            }
                                                            src={
                                                                child.person
                                                                    .photo_url
                                                            }
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
                            </>
                        )}

                        {tab === 'catatan' && (
                            <>
                                {can.createNote && (
                                    <div className="flex justify-end">
                                        <Button onClick={writeNote}>
                                            <StickyNote />
                                            Tulis catatan
                                        </Button>
                                    </div>
                                )}

                                {notes.length === 0 ? (
                                    <EmptyState
                                        icon={StickyNote}
                                        title="Belum ada catatan"
                                        description="Catatan yang boleh kamu baca akan muncul di sini."
                                    />
                                ) : (
                                    <div className="grid gap-3">
                                        {notes.map((note) => (
                                            <NoteCard
                                                key={note.id}
                                                authorName={note.author.name}
                                                authorPhoto={
                                                    note.author.photo_url
                                                }
                                                createdAt={
                                                    note.created_at ?? ''
                                                }
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
                                                    note.can_update && (
                                                        <>
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
                                                        </>
                                                    )
                                                }
                                            />
                                        ))}
                                    </div>
                                )}
                            </>
                        )}
                    </div>

                    <aside className="grid gap-6 @5xl:sticky @5xl:top-20">
                        <SectionCard title="Kontak">
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

                        <SectionCard
                            title="Kelengkapan data"
                            description={`${completed} dari ${checklist.length} sudah lengkap`}
                        >
                            <div className="grid gap-4">
                                <Progress
                                    value={completed}
                                    max={checklist.length}
                                    aria-label="Kelengkapan data"
                                />
                                <ul className="grid gap-2 text-sm">
                                    {checklist.map((item) => (
                                        <li
                                            key={item.label}
                                            className="flex items-center gap-2.5"
                                        >
                                            {item.done ? (
                                                <Check className="size-4 text-emerald-600" />
                                            ) : (
                                                <Circle className="size-4 text-amber-600" />
                                            )}
                                            <span
                                                className={
                                                    item.done
                                                        ? 'text-muted-foreground'
                                                        : 'font-medium'
                                                }
                                            >
                                                {item.label}
                                                <span className="sr-only">
                                                    {item.done
                                                        ? ', sudah'
                                                        : ', belum'}
                                                </span>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </SectionCard>

                        <SectionCard
                            title="Pendaftaran terkait"
                            icon={ClipboardList}
                        >
                            {registrations.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada pendaftaran.
                                </p>
                            ) : (
                                <ul className="grid gap-1">
                                    {registrations.map((registration) => (
                                        <li key={registration.id}>
                                            <Link
                                                href={showRegistrant(
                                                    registration.id,
                                                )}
                                                className="-mx-2 flex min-h-12 items-center justify-between gap-3 rounded-xl px-2 py-1.5 text-sm hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring focus-visible:outline-none"
                                            >
                                                <span className="grid min-w-0 leading-tight">
                                                    <span className="truncate font-semibold">
                                                        {registration.is_self
                                                            ? registration.organization
                                                            : `${registration.child_name} · ${registration.organization}`}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {registration.wave}
                                                    </span>
                                                </span>
                                                <StatusBadge
                                                    status={registration.status}
                                                    label={
                                                        registration.status_label
                                                    }
                                                />
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </SectionCard>
                    </aside>
                </div>
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
