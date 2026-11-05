import { Head, Link, usePage } from '@inertiajs/react';
import { Building2, UserRound, Users } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index as people } from '@/routes/admin/people';

export default function Dashboard({
    stats,
}: {
    stats: { people: number; organizations: number; users: number };
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Beranda" />
            <div className="mx-auto grid w-full max-w-5xl gap-8 p-4 md:p-6">
                <PageHeader
                    title={`Halo, ${auth.user.name}!`}
                    description="Ini ringkasan data yang tercatat di sistem."
                />

                <section className="grid gap-4 sm:grid-cols-3">
                    <StatCard
                        label="Orang tercatat"
                        value={stats.people}
                        icon={Users}
                    />
                    <StatCard
                        label="Lembaga aktif"
                        value={stats.organizations}
                        icon={Building2}
                    />
                    <StatCard
                        label="Akun pengguna"
                        value={stats.users}
                        icon={UserRound}
                    />
                </section>

                {auth.abilities.includes('core::person.view') && (
                    <SectionCard
                        title="Mulai dari sini"
                        description="Kelola data orang, keluarga, dan catatan."
                    >
                        <Button asChild>
                            <Link href={people()}>Lihat daftar orang</Link>
                        </Button>
                    </SectionCard>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Beranda', href: dashboard() }],
};
