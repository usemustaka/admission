import { Head, usePage } from '@inertiajs/react';
import { Baby } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';

export default function PortalHome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Beranda" />
            <div className="mx-auto grid w-full max-w-5xl gap-8 px-4 py-8">
                <PageHeader
                    title={`Halo, ${auth.user.name}!`}
                    description="Di sini kamu bisa memantau pendaftaran anak sampai pengumuman."
                />

                <EmptyState
                    icon={Baby}
                    title="Belum ada anak yang didaftarkan"
                    description="Pendaftaran anak akan bisa dilakukan dari sini. Tunggu kabar dari sekolah ya."
                />
            </div>
        </>
    );
}
