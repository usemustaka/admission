import { Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { AdminHeader } from '@/components/admin/admin-header';
import { AdminTabBar } from '@/components/admin/admin-tab-bar';
import type { AppLayoutProps } from '@/types';
import { PageTransition } from '@/components/page-transition';

/**
 * Admin panel shell. Content spans the full window because staff work on
 * desktops; below md the header hands navigation to the bottom bar.
 */
export default function AppAdminLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const parent =
        breadcrumbs.length > 2 ? breadcrumbs[breadcrumbs.length - 2] : null;

    return (
        <div className="flex min-h-svh flex-col">
            <AdminHeader />

            <main className="@container mx-auto w-full max-w-7xl flex-1 px-4 pt-3 pb-24 sm:px-6 md:pt-6 md:pb-12 lg:px-8">
                {parent && (
                    <Link
                        href={parent.href}
                        prefetch
                        className="mb-2 -ml-1.5 inline-flex items-center gap-1 rounded-lg px-1.5 py-1 text-sm font-semibold text-muted-foreground outline-none hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >
                        <ChevronLeft className="size-4" />
                        {parent.title}
                    </Link>
                )}
                <PageTransition>{children}</PageTransition>
            </main>

            <AdminTabBar />
        </div>
    );
}
