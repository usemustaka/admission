import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Star, StickyNote, Trash2, Users } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DescriptionList } from '@/components/description-list';
import { EmptyState } from '@/components/empty-state';
import { NoteCard } from '@/components/note-card';
import { PageHeader } from '@/components/page-header';
import { PersonAvatar } from '@/components/person-avatar';
import { AddParentDialog } from '@/components/people/add-parent-dialog';
import { NoteForm } from '@/components/people/note-form';
import { SectionCard } from '@/components/section-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import { edit, index, show } from '@/routes/admin/people';
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
} from '@/types/people';

type PendingDelete =
    | { kind: 'parent'; id: number; name: string }
    | { kind: 'note'; id: number }
    | null;

export default function PersonShow({
    person,
    parents,
    dependents,
    notes,
    can,
    options,
}: {
    person: PersonDetail;
    parents: ParentLink[];
    dependents: ChildLink[];
    notes: NoteItem[];
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
    const [processing, setProcessing] = useState(false);

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
            <div className="mx-auto grid w-full max-w-4xl gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <PersonAvatar
                        name={person.name}
                        src={person.photo_url}
                        size="xl"
                    />
                    <div className="flex-1">
                        <PageHeader
                            title={person.name}
                            description={person.nik ?? 'NIK belum diisi'}
                            actions={
                                can.update && (
                                    <Button variant="outline" asChild>
                                        <Link href={edit(person.id)}>
                                            <Pencil />
                                            Ubah data
                                        </Link>
                                    </Button>
                                )
                            }
                        />
                    </div>
                </div>

                <SectionCard title="Data diri">
                    <DescriptionList
                        items={[
                            { label: 'Jenis kelamin', value: person.gender },
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
                            { label: 'Nomor HP', value: person.phone },
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

                <SectionCard
                    title="Keluarga"
                    description="Orang tua dan wali. Yang jadi kontak utama akan dihubungi untuk pengumuman."
                    icon={Users}
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
                    <div className="grid gap-6">
                        {parents.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Belum ada orang tua atau wali yang tercatat.
                            </p>
                        ) : (
                            <ul className="grid gap-3">
                                {parents.map((parent) => (
                                    <li
                                        key={parent.id}
                                        className="flex flex-wrap items-center gap-3 rounded-2xl border p-3"
                                    >
                                        <PersonAvatar
                                            name={parent.person.name}
                                            src={parent.person.photo_url}
                                        />
                                        <div className="grid min-w-0 flex-1 leading-tight">
                                            <Link
                                                href={show(parent.person.id)}
                                                className="truncate font-semibold hover:text-primary"
                                            >
                                                {parent.person.name}
                                            </Link>
                                            <span className="text-sm text-muted-foreground">
                                                {[
                                                    parent.relation,
                                                    parent.kind,
                                                    parent.person.phone,
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
                                        {can.manageFamily && (
                                            <div className="flex gap-1">
                                                {!parent.is_primary_contact && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            router.put(
                                                                makePrimary({
                                                                    person: person.id,
                                                                    parentship:
                                                                        parent.id,
                                                                }).url,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Jadikan kontak utama
                                                    </Button>
                                                )}
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Hapus ${parent.person.name}`}
                                                    onClick={() =>
                                                        setPendingDelete({
                                                            kind: 'parent',
                                                            id: parent.id,
                                                            name: parent.person
                                                                .name,
                                                        })
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}

                        {dependents.length > 0 && (
                            <div className="grid gap-3">
                                <h3 className="text-sm font-semibold">
                                    Anak yang diampu
                                </h3>
                                <ul className="grid gap-2">
                                    {dependents.map((child) => (
                                        <li key={child.id}>
                                            <Link
                                                href={show(child.person.id)}
                                                className="flex items-center gap-3 rounded-xl p-2 hover:bg-sage-50 dark:hover:bg-muted"
                                            >
                                                <PersonAvatar
                                                    name={child.person.name}
                                                    src={child.person.photo_url}
                                                    size="sm"
                                                />
                                                <span className="text-sm font-semibold">
                                                    {child.person.name}
                                                </span>
                                                <Badge variant="muted">
                                                    {child.relation}
                                                </Badge>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>
                </SectionCard>

                <SectionCard
                    title="Catatan"
                    description="Catatan untuk sesama panitia. Hanya yang kamu izinkan yang bisa membacanya."
                    icon={StickyNote}
                >
                    <div className="grid gap-6">
                        {can.createNote && (
                            <NoteForm
                                personId={person.id}
                                urgencies={options.urgencies}
                                visibilities={options.visibilities}
                                users={options.users}
                                roles={options.roles}
                            />
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
                                            note.can_update && (
                                                <>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
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
                                                            setPendingDelete({
                                                                kind: 'note',
                                                                id: note.id,
                                                            })
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
                    </div>
                </SectionCard>
            </div>

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
                description="Yang dihapus tidak bisa dikembalikan."
                confirmLabel="Ya, hapus"
                onConfirm={confirmDelete}
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
