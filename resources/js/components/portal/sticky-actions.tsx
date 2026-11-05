import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Action area pinned above the bottom tab bar of the portal. It bleeds to the
 * edges of the phone column (the page already has a 16px gutter) and sits
 * above the tab bar's 64px plus the device's safe area.
 */
export function StickyActions({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'sticky bottom-[calc(3.5rem+env(safe-area-inset-bottom))] z-20 -mx-4 grid gap-2 border-t border-line bg-background/95 px-4 py-3 backdrop-blur',
                className,
            )}
        >
            {children}
        </div>
    );
}
