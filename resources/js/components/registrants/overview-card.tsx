import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, ShieldAlert } from 'lucide-react';
import { DescriptionList } from '@/components/description-list';
import { PersonAvatar } from '@/components/person-avatar';
import { RelativeTime } from '@/components/registrants/relative-time';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import { show as showPerson } from '@/routes/admin/people';
import { show } from '@/routes/admin/registrants';
import type {
    RegistrantQueue,
    RegistrantQueueContext,
} from '@/types/registrant';

export type RegistrantOverview = {
    id: number;
    status: string;
    needs_identity_review: boolean;
    submitted_name: string;
    registered_by: string;
    registered_as: string;
    organization: string;
    wave: string;
    ruleset: string | null;
    registered_at: string | null;
    submitted_at: string | null;
    child: {
        id: number;
        name: string;
        nik: string | null;
        photo_url: string | null;
    };
};

/**
 * Who this child is and where the registration stands, in one band at the top: name, status, the facts the committee
 * keeps asking for, and the previous and next registration of the queue the page was opened from.
 */
export function OverviewCard({
    registrant,
    admissionTypeLabel,
    student,
    queue,
    queueContext,
    canViewPerson,
}: {
    registrant: RegistrantOverview;
    admissionTypeLabel?: string;
    student: { nis: string | null; year: string | null } | null;
    queue: RegistrantQueue;
    queueContext: RegistrantQueueContext;
    canViewPerson: boolean;
}) {
    const queueLink = (id: number | null, forward: boolean) => {
        const Icon = forward ? ChevronRight : ChevronLeft;
        const label = forward ? 'Berikutnya' : 'Sebelumnya';

        return id === null ? (
            <Button variant="outline" size="icon" disabled aria-label={label}>
                <Icon />
            </Button>
        ) : (
            <Button variant="outline" size="icon" asChild>
                <Link
                    href={show(id, { query: queueContext.query })}
                    aria-label={label}
                    title={`${label} (${forward ? 'j' : 'k'})`}
                >
                    <Icon />
                </Link>
            </Button>
        );
    };

    return (
        <Card className="gap-4 @2xl:gap-4">
            <CardContent className="grid gap-5">
                <div className="flex items-start gap-4">
                    <PersonAvatar
                        name={registrant.child.name}
                        src={registrant.child.photo_url}
                        size="xl"
                        className="size-14 shrink-0 text-base @2xl:size-20 @2xl:text-2xl"
                    />
                    <div className="grid min-w-0 flex-1 gap-1.5">
                        <h1 className="text-xl font-semibold break-words @2xl:text-2xl">
                            {registrant.child.name}
                        </h1>
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusBadge status={registrant.status} />
                            {registrant.needs_identity_review && (
                                <Badge variant="danger">
                                    <ShieldAlert />
                                    Cek NIK
                                </Badge>
                            )}
                            {admissionTypeLabel && (
                                <Badge variant="muted">
                                    {admissionTypeLabel}
                                </Badge>
                            )}
                            {canViewPerson && (
                                <Link
                                    href={showPerson(registrant.child.id)}
                                    className="text-sm font-semibold underline underline-offset-4"
                                >
                                    Buka profil anak
                                </Link>
                            )}
                        </div>
                    </div>
                    <div className="grid shrink-0 justify-items-end gap-1">
                        <div className="flex gap-1.5">
                            {queueLink(queue.previous_id, false)}
                            {queueLink(queue.next_id, true)}
                        </div>
                        <p className="text-xs text-muted-foreground tabular-nums">
                            {queue.position} dari {queue.total} di antrean
                        </p>
                    </div>
                </div>

                <DescriptionList
                    compact
                    className="border-t border-line pt-4 @3xl:grid-cols-4 @6xl:grid-cols-6"
                    items={[
                        { label: 'Lembaga', value: registrant.organization },
                        { label: 'Gelombang', value: registrant.wave },
                        { label: 'Aturan', value: registrant.ruleset },
                        {
                            label: 'Didaftarkan oleh',
                            value: `${registrant.registered_by} (${registrant.registered_as})`,
                        },
                        {
                            label: 'Mendaftar',
                            value: (
                                <RelativeTime
                                    value={registrant.registered_at}
                                />
                            ),
                        },
                        {
                            label: 'Data dikirim',
                            value: registrant.submitted_at
                                ? formatDate(registrant.submitted_at, {
                                      day: 'numeric',
                                      month: 'short',
                                      year: 'numeric',
                                  })
                                : null,
                        },
                        ...(student
                            ? [
                                  {
                                      label: 'Penempatan',
                                      value: `${student.year ?? '—'} · NIS ${student.nis ?? '—'}`,
                                  },
                              ]
                            : []),
                    ]}
                />
            </CardContent>
        </Card>
    );
}
