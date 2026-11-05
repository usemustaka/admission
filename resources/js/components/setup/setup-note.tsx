import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** A quiet, non-announced hint block. Use `Alert` for anything urgent. */
export function SetupNote({
    icon,
    children,
    className,
}: {
    icon?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex items-start gap-3 rounded-2xl bg-neutral-50 p-4 text-[15px] text-muted-foreground dark:bg-muted',
                className,
            )}
        >
            {icon && (
                <span aria-hidden="true" className="mt-0.5 shrink-0">
                    {icon}
                </span>
            )}
            <div className="grid gap-1">{children}</div>
        </div>
    );
}
