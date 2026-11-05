import { Link } from '@inertiajs/react';
import { BrandMark } from '@/components/brand-mark';
import { FocusSwitcher } from '@/components/portal/focus-switcher';
import { portal } from '@/routes';

export function PortalHeader() {
    return (
        <header className="sticky top-0 z-30 flex h-12 items-center justify-between gap-3 border-b border-line bg-background/95 px-4 backdrop-blur">
            <Link
                href={portal()}
                prefetch
                aria-label="Beranda"
                className="shrink-0 rounded-lg outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                <BrandMark size={32} />
            </Link>
            <FocusSwitcher />
        </header>
    );
}
