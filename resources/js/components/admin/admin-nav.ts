import type { InertiaLinkProps } from '@inertiajs/react';
import {
    CalendarRange,
    ClipboardList,
    House,
    Landmark,
    ListChecks,
    Megaphone,
    PanelTop,
    Palette,
    Phone,
    Settings,
    Store,
    Users,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { dashboard } from '@/routes';
import { edit as admissionSettings } from '@/routes/admin/admission-settings';
import { index as payments } from '@/routes/admin/payments';
import { index as people } from '@/routes/admin/people';
import { index as registrants } from '@/routes/admin/registrants';
import { index as rulesets } from '@/routes/admin/rulesets';
import { edit as editSetting } from '@/routes/admin/settings';
import { index as waves } from '@/routes/admin/waves';

export type AdminPage = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
    /** Permission needed to see this page, e.g. `core::person.view`. */
    ability?: string;
};

export type AdminSection = {
    title: string;
    icon: LucideIcon;
    pages: AdminPage[];
};

/**
 * Top-level areas of the admin panel. A section with several pages shows them
 * as tabs under the header; the section itself links to its first page.
 */
export const adminSections: AdminSection[] = [
    {
        title: 'Beranda',
        icon: House,
        pages: [{ title: 'Beranda', href: dashboard(), icon: House }],
    },
    {
        title: 'Pendaftaran',
        icon: ClipboardList,
        pages: [
            {
                title: 'Pendaftar',
                href: registrants(),
                icon: ClipboardList,
                ability: 'admission::registrant.view',
            },
            {
                title: 'Gelombang',
                href: waves(),
                icon: CalendarRange,
                ability: 'admission::wave.view',
            },
            {
                title: 'Aturan pendaftaran',
                href: rulesets(),
                icon: ListChecks,
                ability: 'admission::ruleset.view',
            },
            {
                title: 'Templat pengumuman',
                href: admissionSettings(),
                icon: Megaphone,
                ability: 'admission::registrant.manage',
            },
        ],
    },
    {
        title: 'Keuangan',
        icon: Wallet,
        pages: [
            {
                title: 'Pembayaran',
                href: payments(),
                icon: Wallet,
                ability: 'admission::payment.view',
            },
        ],
    },
    {
        title: 'Data',
        icon: Users,
        pages: [
            {
                title: 'Orang',
                href: people(),
                icon: Users,
                ability: 'core::person.view',
            },
        ],
    },
    {
        title: 'Pengaturan',
        icon: Settings,
        pages: [
            {
                title: 'Identitas',
                href: editSetting({ group: 'identitas' }),
                icon: Store,
                ability: 'core::setting.manage',
            },
            {
                title: 'Tampilan',
                href: editSetting({ group: 'tampilan' }),
                icon: Palette,
                ability: 'core::setting.manage',
            },
            {
                title: 'Kontak',
                href: editSetting({ group: 'kontak' }),
                icon: Phone,
                ability: 'core::setting.manage',
            },
            {
                title: 'Rekening',
                href: editSetting({ group: 'pembayaran' }),
                icon: Landmark,
                ability: 'core::setting.manage',
            },
            {
                title: 'Halaman depan',
                href: editSetting({ group: 'halaman-depan' }),
                icon: PanelTop,
                ability: 'core::setting.manage',
            },
        ],
    },
];

/**
 * Sections and pages the signed-in staff member may open; empty sections drop
 * out so nobody sees a menu that leads nowhere.
 */
export function visibleAdminSections(abilities: string[]): AdminSection[] {
    return adminSections
        .map((section) => ({
            ...section,
            pages: section.pages.filter(
                (page) => !page.ability || abilities.includes(page.ability),
            ),
        }))
        .filter((section) => section.pages.length > 0);
}
