import { Link } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import { useState } from 'react';
import { useAdminNavigation } from '@/components/admin/admin-header';
import { AccountSheet } from '@/components/account-sheet';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';

const itemClass = (selected: boolean) =>
    cn(
        'flex flex-col items-center gap-0.5 pt-2 pb-1 text-[11px] font-semibold outline-none focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring',
        selected ? 'text-foreground' : 'text-muted-foreground',
    );

const pillClass = (selected: boolean) =>
    cn(
        'grid h-7 w-12 place-items-center rounded-full transition-colors',
        selected && 'bg-lime-300 text-neutral-900',
    );

/**
 * Bottom navigation of the admin panel on narrow screens. The desktop header
 * carries the same sections; this one only exists below the md breakpoint.
 */
export function AdminTabBar() {
    const { sections: allSections, active } = useAdminNavigation();
    const sections = allSections.filter(
        (section) => section.title !== 'Pengaturan',
    );
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const [menuOpen, setMenuOpen] = useState(false);
    const onSettings =
        isCurrentOrParentUrl('/settings') || active?.title === 'Pengaturan';

    return (
        <>
            <nav
                aria-label="Menu utama"
                className="fixed inset-x-0 bottom-0 z-40 grid border-t border-line bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden"
                style={{
                    gridTemplateColumns: `repeat(${sections.length + 1}, minmax(0, 1fr))`,
                }}
            >
                {sections.map((section) => {
                    const selected = section === active;

                    return (
                        <Link
                            key={section.title}
                            href={section.pages[0].href}
                            aria-current={selected ? 'page' : undefined}
                            className={itemClass(selected)}
                        >
                            <span className={pillClass(selected)}>
                                <section.icon
                                    className="size-[22px]"
                                    strokeWidth={selected ? 2.25 : 1.75}
                                />
                            </span>
                            {section.title}
                        </Link>
                    );
                })}
                <button
                    type="button"
                    onClick={() => setMenuOpen(true)}
                    className={itemClass(onSettings)}
                >
                    <span className={pillClass(onSettings)}>
                        <Menu className="size-[22px]" strokeWidth={1.75} />
                    </span>
                    Akun
                </button>
            </nav>

            <AccountSheet open={menuOpen} onOpenChange={setMenuOpen} />
        </>
    );
}
