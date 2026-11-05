import type { PropsWithChildren } from 'react';
import { LinkTabs } from '@/components/section-tabs';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

const tabs = [
    { title: 'Profil', href: edit() },
    { title: 'Keamanan', href: editSecurity() },
    { title: 'Tampilan', href: editAppearance() },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="mx-auto max-w-2xl space-y-8">
            <div className="space-y-6">
                <h1 className="pt-2 text-[26px] leading-tight font-semibold tracking-tight @3xl:text-[32px]">
                    Pengaturan akun
                </h1>
                <LinkTabs
                    label="Pengaturan"
                    items={tabs.map((tab) => ({
                        href: tab.href,
                        label: tab.title,
                        selected: isCurrentOrParentUrl(tab.href),
                    }))}
                />
            </div>

            <section className="space-y-12">{children}</section>
        </div>
    );
}
