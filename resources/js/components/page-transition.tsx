import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

/**
 * Fades and slides a page in whenever the path changes. Filters and sorting
 * only change the query string, so they do not replay it. Motion is switched
 * off globally for people who prefer reduced motion.
 */
export function PageTransition({ children }: { children: ReactNode }) {
    const path = usePage().url.split('?')[0];

    return (
        <div key={path} className="anim-page">
            {children}
        </div>
    );
}
