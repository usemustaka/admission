import { Link, usePage } from '@inertiajs/react';
import {
    CalendarRange,
    ClipboardList,
    LayoutGrid,
    ListChecks,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as payments } from '@/routes/admin/payments';
import { index as people } from '@/routes/admin/people';
import { index as registrants } from '@/routes/admin/registrants';
import { index as rulesets } from '@/routes/admin/rulesets';
import { index as waves } from '@/routes/admin/waves';
import type { NavItem } from '@/types';

type NavGroup = {
    label: string;
    items: NavItem[];
};

const navGroups: NavGroup[] = [
    {
        label: 'Menu',
        items: [{ title: 'Beranda', href: dashboard(), icon: LayoutGrid }],
    },
    {
        label: 'Data',
        items: [
            {
                title: 'Orang',
                href: people(),
                icon: Users,
                ability: 'core::person.view',
            },
        ],
    },
    {
        label: 'Pendaftaran',
        items: [
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
        ],
    },
    {
        label: 'Keuangan',
        items: [
            {
                title: 'Pembayaran',
                href: payments(),
                icon: Wallet,
                ability: 'admission::payment.view',
            },
        ],
    },
];

export function AppSidebar() {
    const { abilities } = usePage().props.auth;

    const visibleGroups = navGroups
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) => !item.ability || abilities.includes(item.ability),
            ),
        }))
        .filter((group) => group.items.length > 0);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {visibleGroups.map((group) => (
                    <NavMain
                        key={group.label}
                        label={group.label}
                        items={group.items}
                    />
                ))}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
