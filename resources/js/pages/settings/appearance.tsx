import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import { SectionCard } from '@/components/section-card';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title="Pengaturan tampilan" />

            <SectionCard
                title="Tampilan"
                description="Pilih tampilan terang atau gelap. Pilihan Otomatis mengikuti pengaturan di HP atau laptopmu."
            >
                <AppearanceTabs />
            </SectionCard>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Pengaturan tampilan',
            href: editAppearance(),
        },
    ],
};
