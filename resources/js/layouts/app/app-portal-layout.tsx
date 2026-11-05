import { Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { PortalHeader } from '@/components/portal/portal-header';
import { PortalTabBar } from '@/components/portal/portal-tab-bar';
import type { AppLayoutProps } from '@/types';
import { PageTransition } from '@/components/page-transition';

/**
 * Parent portal shell. Parents mostly use phones, so the portal is a
 * phone-wide column with a bottom bar at every window size; on a large
 * screen the column sits centred on a quiet backdrop.
 */
export default function AppPortalLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const parent =
        breadcrumbs.length > 2 ? breadcrumbs[breadcrumbs.length - 2] : null;

    return (
        <div className="min-h-svh bg-neutral-100 dark:bg-background">
            <div className="@container relative mx-auto flex min-h-svh w-full max-w-[480px] flex-col bg-background min-[481px]:border-x min-[481px]:border-line">
                <PortalHeader />

                <main className="flex-1 px-3.5 pt-3 pb-24">
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

                <PortalTabBar />
            </div>
        </div>
    );
}
