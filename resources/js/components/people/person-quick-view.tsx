import { Link } from '@inertiajs/react';
import {
    ExternalLink,
    MessageCircle,
    Phone,
    RotateCw,
    ShieldAlert,
    Star,
    TriangleAlert,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import { PersonAvatar } from '@/components/person-avatar';
import { NoteUrgencyBadge } from '@/components/note-card';
import { WhatsAppLink } from '@/components/people/whatsapp-link';
import { QuickView } from '@/components/quick-view';
import { StatusBadge } from '@/components/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { isCompleteNik, whatsappUrl } from '@/lib/format';
import { quickView, show } from '@/routes/admin/people';
import { show as showRegistrant } from '@/routes/admin/registrants';
import type { PersonQuickViewData } from '@/types/people';

type Loaded =
    | { id: number; data: PersonQuickViewData }
    | { id: number; failed: true };

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="grid gap-3 border-t border-line pt-5 first:border-t-0 first:pt-0">
            <h3 className="text-sm font-semibold">{title}</h3>
            {children}
        </section>
    );
}

function QuickViewSkeleton() {
    return (
        <div className="grid gap-6" aria-busy="true" aria-label="Memuat">
            <div className="flex items-center gap-4">
                <Skeleton className="size-14 rounded-full" />
                <div className="grid flex-1 gap-2">
                    <Skeleton className="h-5 w-2/3" />
                    <Skeleton className="h-4 w-1/2" />
                </div>
            </div>
            <Skeleton className="h-24 w-full rounded-2xl" />
            <Skeleton className="h-32 w-full rounded-2xl" />
            <Skeleton className="h-20 w-full rounded-2xl" />
        </div>
    );
}

/**
 * Side panel that peeks at one person from the people list: who they are, how to reach them, their family,
 * latest notes and registrations. Loads its data when `personId` changes.
 */
export function PersonQuickView({
    personId,
    fallbackName,
    onClose,
}: {
    personId: number | null;
    /** Name from the list row, shown in the title while the details load. */
    fallbackName?: string;
    onClose: () => void;
}) {
    const [loaded, setLoaded] = useState<Loaded | null>(null);
    const [attempt, setAttempt] = useState(0);

    useEffect(() => {
        if (personId === null) {
            return;
        }

        const controller = new AbortController();

        fetch(quickView(personId).url, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                return response.json();
            })
            .then((json: { data: PersonQuickViewData }) =>
                setLoaded({ id: personId, data: json.data }),
            )
            .catch((error: Error) => {
                if (error.name === 'AbortError') {
                    return;
                }

                setLoaded({ id: personId, failed: true });
                toast.error(
                    'Ringkasan belum bisa dimuat. Coba lagi, atau buka halaman detailnya.',
                );
            });

        return () => controller.abort();
    }, [personId, attempt]);

    const current = loaded !== null && loaded.id === personId ? loaded : null;
    const data = current !== null && 'data' in current ? current.data : null;
    const failed = current !== null && 'failed' in current;
    const person = data?.person;
    const contactPhone = person?.phone ?? data?.primary_contact?.phone ?? null;

    return (
        <QuickView
            open={personId !== null}
            onOpenChange={(open) => !open && onClose()}
            title={person?.name ?? fallbackName ?? 'Memuat…'}
            description="Ringkasan data orang"
            footer={
                personId !== null && (
                    <>
                        <Button asChild className="flex-1">
                            <Link href={show(personId)}>
                                <ExternalLink />
                                Buka detail
                            </Link>
                        </Button>
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
                )
            }
        >
            {failed && (
                <Alert variant="destructive">
                    <TriangleAlert />
                    <AlertTitle>Ringkasan tidak termuat</AlertTitle>
                    <AlertDescription>
                        <p>Mungkin koneksimu terputus sebentar.</p>
                        <Button
                            variant="outline"
                            size="sm"
                            className="mt-3 bg-card max-md:h-10"
                            onClick={() => {
                                setLoaded(null);
                                setAttempt((value) => value + 1);
                            }}
                        >
                            <RotateCw />
                            Coba lagi
                        </Button>
                    </AlertDescription>
                </Alert>
            )}

            {!failed && !data && <QuickViewSkeleton />}

            {data && person && (
                <div className="grid gap-5">
                    <div className="flex items-center gap-4">
                        <PersonAvatar
                            name={person.name}
                            src={person.photo_url}
                            size="lg"
                        />
                        <div className="grid min-w-0 gap-1.5">
                            <p className="text-sm text-muted-foreground tabular-nums">
                                {person.nik ?? 'NIK belum diisi'}
                            </p>
                            <div className="flex flex-wrap gap-1.5">
                                {data.roles.length === 0 && (
                                    <Badge variant="muted">
                                        Belum ada peran
                                    </Badge>
                                )}
                                {data.roles.map((role) => (
                                    <Badge key={role} variant="default">
                                        {role}
                                    </Badge>
                                ))}
                                {!person.is_alive && (
                                    <Badge variant="muted">Almarhum</Badge>
                                )}
                                {(person.nik === null ||
                                    !isCompleteNik(person.nik)) && (
                                    <Badge variant="warning">
                                        <ShieldAlert />
                                        NIK belum lengkap
                                    </Badge>
                                )}
                            </div>
                        </div>
                    </div>

                    <Section title="Data diri">
                        <dl className="grid grid-cols-[7.5rem_minmax(0,1fr)] gap-x-4 gap-y-2 text-sm">
                            {[
                                ['Jenis kelamin', person.gender],
                                ['Lahir', person.birth],
                                ['Nomor HP', person.phone],
                                ['Email', person.email],
                            ].map(([label, value]) => (
                                <div key={label} className="contents">
                                    <dt className="text-muted-foreground">
                                        {label}
                                    </dt>
                                    <dd className="break-words">
                                        {value ?? (
                                            <span className="text-muted-foreground">
                                                Belum diisi
                                            </span>
                                        )}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </Section>

                    <Section title="Kontak utama">
                        {data.primary_contact ? (
                            <div className="flex items-center gap-3">
                                <Star className="size-4 shrink-0 text-lime-700 dark:text-lime-300" />
                                <div className="grid min-w-0 flex-1 text-sm leading-tight">
                                    <Link
                                        href={show(data.primary_contact.id)}
                                        className="truncate font-semibold hover:underline"
                                    >
                                        {data.primary_contact.name}
                                    </Link>
                                    <span className="text-muted-foreground">
                                        {data.primary_contact.relation}
                                        {data.primary_contact.phone
                                            ? ` · ${data.primary_contact.phone}`
                                            : ' · belum ada nomor HP'}
                                    </span>
                                </div>
                                {data.primary_contact.phone && (
                                    <WhatsAppLink
                                        phone={data.primary_contact.phone}
                                        label={data.primary_contact.name}
                                    />
                                )}
                            </div>
                        ) : person.phone ? (
                            <p className="flex items-center gap-2 text-sm">
                                <Phone className="size-4 text-muted-foreground" />
                                Memakai nomor HP sendiri
                            </p>
                        ) : (
                            <Alert variant="warning">
                                <TriangleAlert />
                                <AlertTitle>Belum ada kontak</AlertTitle>
                                <AlertDescription>
                                    Isi nomor HP atau tentukan kontak utama
                                    keluarga supaya pengumuman bisa terkirim.
                                </AlertDescription>
                            </Alert>
                        )}
                    </Section>

                    <Section title="Keluarga">
                        {data.parents.length === 0 &&
                        data.dependents.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Belum ada orang tua, wali, atau anak yang
                                tercatat.
                            </p>
                        ) : (
                            <ul className="grid gap-3">
                                {data.parents.map((parent) => (
                                    <li
                                        key={`p-${parent.id}`}
                                        className="flex items-center gap-3"
                                    >
                                        <PersonAvatar
                                            name={parent.name}
                                            src={parent.photo_url}
                                            size="sm"
                                        />
                                        <div className="grid min-w-0 flex-1 text-sm leading-tight">
                                            <Link
                                                href={show(parent.id)}
                                                className="truncate font-semibold hover:underline"
                                            >
                                                {parent.name}
                                            </Link>
                                            <span className="text-muted-foreground">
                                                {parent.relation}
                                                {parent.phone
                                                    ? ` · ${parent.phone}`
                                                    : ''}
                                            </span>
                                        </div>
                                        {parent.is_primary_contact && (
                                            <Badge variant="success">
                                                Utama
                                            </Badge>
                                        )}
                                        {parent.phone && (
                                            <WhatsAppLink
                                                phone={parent.phone}
                                                label={parent.name}
                                                variant="ghost"
                                            />
                                        )}
                                    </li>
                                ))}
                                {data.dependents.map((child) => (
                                    <li
                                        key={`c-${child.id}`}
                                        className="flex items-center gap-3"
                                    >
                                        <PersonAvatar
                                            name={child.name}
                                            src={child.photo_url}
                                            size="sm"
                                        />
                                        <Link
                                            href={show(child.id)}
                                            className="min-w-0 flex-1 truncate text-sm font-semibold hover:underline"
                                        >
                                            {child.name}
                                        </Link>
                                        <Badge variant="muted">
                                            Anak ({child.relation})
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Section>

                    <Section title="Catatan terbaru">
                        {data.notes.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Belum ada catatan yang bisa kamu baca.
                            </p>
                        ) : (
                            <ul className="grid gap-3">
                                {data.notes.map((note) => (
                                    <li
                                        key={note.id}
                                        className="grid gap-1 text-sm"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="text-xs text-muted-foreground">
                                                {note.author} ·{' '}
                                                {note.created_at}
                                            </span>
                                            <NoteUrgencyBadge
                                                urgency={note.urgency}
                                            />
                                        </div>
                                        <p className="whitespace-pre-wrap">
                                            {note.body}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Section>

                    <Section title="Pendaftaran terkait">
                        {data.registrations.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Belum ada pendaftaran.
                            </p>
                        ) : (
                            <ul className="grid gap-2">
                                {data.registrations.map((registration) => (
                                    <li key={registration.id}>
                                        <Link
                                            href={showRegistrant(
                                                registration.id,
                                            )}
                                            className="-mx-2 flex min-h-10 items-center justify-between gap-3 rounded-xl px-2 py-1.5 text-sm hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring focus-visible:outline-none"
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
                    </Section>
                </div>
            )}
        </QuickView>
    );
}
