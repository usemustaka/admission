import { Head, Link, usePage } from '@inertiajs/react';
import { Baby, Plus } from 'lucide-react';
import { ChildCard } from '@/components/child-card';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { currentStepIndex, stepsFor } from '@/lib/admission-steps';
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
            <div className="mx-auto grid w-full max-w-5xl gap-8 px-4 py-8">
                <PageHeader
                    title={`Halo, ${auth.user.name}!`}
                    description="Di sini kamu bisa memantau pendaftaran anak sampai pengumuman."
                    actions={
                        registrants.length > 0 && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus />
                                    Daftarkan anak
                                </Link>
                            </Button>
                        )
                    }
                />

                {registrants.length === 0 ? (
                    <EmptyState
                        icon={Baby}
                        title="Belum ada anak yang didaftarkan"
                        description="Mulai dengan mendaftarkan anak pertamamu. Cuma butuh beberapa menit kok."
                        action={
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus />
                                    Daftarkan anak
                                </Link>
                            </Button>
                        }
                    />
                ) : (
                    <section className="grid gap-4 md:grid-cols-2">
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
                                    organizationName={registrant.organization}
                                    waveName={registrant.wave}
                                    status={registrant.status}
                                    stepIndex={index}
                                    stepCount={steps.length}
                                    stepLabel={steps[index]?.label ?? ''}
                                    href={show(registrant.id)}
                                />
                            );
                        })}
                    </section>
                )}
            </div>
        </>
    );
}
