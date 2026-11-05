import { Link, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AdminSearch } from '@/components/admin/admin-search';
import { visibleAdminSections } from '@/components/admin/admin-nav';
import AppLogo from '@/components/app-logo';
import { LinkTabs } from '@/components/section-tabs';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInitials } from '@/hooks/use-initials';
import { cn, nameColor, toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';

export function useAdminNavigation() {
    const { abilities } = usePage().props.auth;
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const sections = visibleAdminSections(abilities);
    const active = sections.find((section) =>
        section.pages.some((page) => isCurrentOrParentUrl(page.href)),
    );

    return { abilities, sections, active, isCurrentOrParentUrl };
}

export function AdminHeader() {
    const { auth } = usePage().props;
    const { abilities, sections, active, isCurrentOrParentUrl } =
        useAdminNavigation();
    const getInitials = useInitials();
    const [searchOpen, setSearchOpen] = useState(false);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
                event.preventDefault();
                setSearchOpen(true);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    const subPages = active && active.pages.length > 1 ? active.pages : [];
    // Sibling pages can share a prefix (/admin/exams and /admin/exams/jadwal), so only the most specific match is current.
    const currentPage = subPages
        .filter((page) => isCurrentOrParentUrl(page.href))
        .sort((a, b) => toUrl(b.href).length - toUrl(a.href).length)[0];

    return (
        <header className="sticky top-0 z-40 border-b border-line bg-background/75 backdrop-blur-sm">
            <div className="mx-auto flex h-14 max-w-7xl items-center justify-between gap-2 px-4 sm:px-6 md:h-20 lg:px-8">
                <Link
                    href={dashboard()}
                    prefetch
                    className="shrink-0 rounded-lg outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring lg:w-64"
                >
                    <span className="md:hidden">
                        <AppLogo showName={false} />
                    </span>
                    <span className="hidden md:inline">
                        <AppLogo />
                    </span>
                </Link>

                <nav
                    aria-label="Menu utama"
                    className="hidden items-end gap-1 self-stretch md:flex"
                >
                    {sections.map((section) => {
                        const selected = section === active;
                        const target = section.pages[0];

                        return (
                            <Link
                                key={section.title}
                                href={target.href}
                                prefetch
                                aria-current={selected ? 'page' : undefined}
                                className={cn(
                                    'group flex w-20 flex-col items-center justify-center gap-1.5 border-b-2 pt-1 text-[13px] font-semibold transition-colors outline-none focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring lg:w-24',
                                    selected
                                        ? 'border-foreground text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground',
                                )}
                            >
                                <section.icon
                                    className="size-[26px] transition-transform group-hover:scale-110"
                                    strokeWidth={selected ? 2.25 : 1.6}
                                />
                                <span className="mb-2.5">{section.title}</span>
                            </Link>
                        );
                    })}
                </nav>

                <div className="flex shrink-0 items-center justify-end gap-1 lg:w-64">
                    <button
                        type="button"
                        onClick={() => setSearchOpen(true)}
                        className="hidden h-11 items-center gap-3 rounded-full border border-neutral-300 pr-2 pl-5 text-sm font-semibold whitespace-nowrap shadow-sm transition-shadow outline-none hover:shadow-card focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring xl:flex dark:border-neutral-600"
                    >
                        Cari
                        <span className="font-normal text-muted-foreground">
                            nama atau NIK…
                        </span>
                        <span className="grid size-8 place-items-center rounded-full bg-primary text-primary-foreground">
                            <Search className="size-[15px]" />
                        </span>
                    </button>
                    <button
                        type="button"
                        onClick={() => setSearchOpen(true)}
                        aria-label="Cari"
                        className="grid size-10 place-items-center rounded-full transition-colors outline-none hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring xl:hidden"
                    >
                        <Search className="size-5" />
                    </button>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                type="button"
                                aria-label="Menu akun"
                                data-test="sidebar-menu-button"
                                className="grid size-10 place-items-center rounded-full outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                            >
                                <Avatar className="size-9">
                                    <AvatarImage
                                        src={auth.user?.avatar}
                                        alt={auth.user?.name}
                                    />
                                    <AvatarFallback
                                        className="text-sm font-bold text-white"
                                        style={{
                                            background: nameColor(
                                                auth.user?.name ?? '',
                                            ),
                                        }}
                                    >
                                        {getInitials(auth.user?.name ?? '')}
                                    </AvatarFallback>
                                </Avatar>
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent className="w-64" align="end">
                            {auth.user && <UserMenuContent user={auth.user} />}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            {subPages.length > 0 && (
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <LinkTabs
                        bare
                        label={`Halaman ${active?.title}`}
                        items={subPages.map((page) => ({
                            href: page.href,
                            label: page.title,
                            selected: page === currentPage,
                        }))}
                    />
                </div>
            )}

            <AdminSearch
                open={searchOpen}
                onOpenChange={setSearchOpen}
                abilities={abilities}
            />
        </header>
    );
}
