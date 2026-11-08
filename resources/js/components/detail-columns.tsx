import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * The frame of a dense detail page: up to three columns of cards, each column a group of cards that belong together
 * (what to do, who it is, the paperwork). One column on a phone, two on a medium screen with the last column
 * running across the bottom, three on a wide one. Columns start at the top and never stretch to match each other.
 */
export function DetailColumns({ children }: { children: ReactNode }) {
    return (
        <div className="grid items-start gap-4 @4xl:grid-cols-2 @6xl:grid-cols-3">
            {children}
        </div>
    );
}

export function DetailColumn({
    children,
    wide = false,
}: {
    children: ReactNode;
    /** The column that spans both tracks on a medium screen and lays its cards out in two columns there. */
    wide?: boolean;
}) {
    return (
        <div
            className={cn(
                'grid content-start gap-4',
                wide &&
                    '@4xl:col-span-2 @4xl:grid-cols-2 @4xl:items-start @6xl:col-span-1 @6xl:grid-cols-1',
            )}
        >
            {children}
        </div>
    );
}
