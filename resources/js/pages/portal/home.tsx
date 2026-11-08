import { Head, Link, usePage } from '@inertiajs/react';
import { Baby, Plus } from 'lucide-react';
import { ChildCard } from '@/components/child-card';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { ContactCard } from '@/components/portal/contact-card';
import { EmailVerificationReminder } from '@/components/portal/email-verification-reminder';
import { Button } from '@/components/ui/button';
import { currentStepIndex, stepsFor } from '@/lib/admission-steps';
import { nextAction } from '@/lib/portal-next-step';
import { create, show } from '@/routes/portal/children';
import type { PortalRegistrant } from '@/types/portal';

export default function PortalHome({
    registrants,
}: {
    registrants: PortalRegistrant[];
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Beranda" />
            <div className="grid gap-4">
                <EmailVerificationReminder />
                <PageHeader
                    title={`Halo, ${auth.user.name}!`}
                    description={
                        registrants.length > 0
                            ? 'Ini perkembangan pendaftaran anak-anakmu dan langkah yang perlu dilakukan.'
                            : 'Di sini kamu bisa memantau pendaftaran anak sampai pengumuman.'
                    }
                />

                {registrants.length === 0 ? (
                    <EmptyState
                        icon={Baby}
                        title="Belum ada anak yang didaftarkan"
                        description="Mulai dengan mendaftarkan anak pertamamu. Cuma butuh beberapa menit kok."
                        action={
                            <Button asChild size="lg">
                                <Link href={create()}>
                                    <Plus />
                                    Daftarkan anak
                                </Link>
                            </Button>
                        }
                    />
                ) : (
                    <>
                        <section
                            aria-label="Anak yang didaftarkan"
                            className="grid gap-3"
                        >
                            {registrants.map((registrant) => {
                                const steps = stepsFor(registrant.steps);
                                const index = currentStepIndex(
                                    registrant.steps,
                                    registrant.current_step,
                                );

                                return (
                                    <ChildCard
                                        key={registrant.id}
                                        name={registrant.child.name}
                                        photo={registrant.child.photo_url}
                                        organizationName={
                                            registrant.organization
                                        }
                                        waveName={registrant.wave}
                                        status={registrant.status}
                                        stepIndex={index}
                                        stepCount={steps.length}
                                        stepLabel={steps[index]?.label ?? ''}
                                        href={show(registrant.id)}
                                        next={nextAction(registrant)}
                                    />
                                );
                            })}
                        </section>

                        <Button
                            asChild
                            size="lg"
                            variant="outline"
                            className="w-full"
                        >
                            <Link href={create()}>
                                <Plus />
                                Daftarkan anak lain
                            </Link>
                        </Button>
                    </>
                )}

                <ContactCard />
            </div>
        </>
    );
}
