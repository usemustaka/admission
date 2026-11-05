import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Hourglass, Users } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { PersonAvatar } from '@/components/person-avatar';
import { SectionCard } from '@/components/section-card';
import { StatusBadge } from '@/components/status-badge';
import { Stepper } from '@/components/stepper';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { currentStepIndex, stepsFor } from '@/lib/admission-steps';
import { portal } from '@/routes';
import { edit } from '@/routes/portal/children/data';
import type { Completeness, PortalRegistrant } from '@/types/portal';

export default function ChildShow({
    registrant,
    completeness,
    family,
}: {
    registrant: PortalRegistrant;
    completeness: Completeness;
    family: { id: number; name: string; relation: string }[];
}) {
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

                <div>
                    <Button variant="ghost" asChild>
                        <Link href={portal()}>Kembali ke semua anak</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
