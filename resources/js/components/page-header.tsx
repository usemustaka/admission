import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * Title block of a page. `icon` is accepted so older call sites keep
 * compiling; the design carries its weight in the title alone.
 */
export function PageHeader({
    title,
    description,
    meta,
    actions,
}: {
    title: string;
    description?: string;
    icon?: LucideIcon;
    meta?: ReactNode;
    actions?: ReactNode;
}) {
    return (
        <header className="flex flex-wrap items-end justify-between gap-x-4 gap-y-2.5 pt-1 @3xl:pt-2">
            <div className="min-w-0 space-y-1">
                <h1 className="text-2xl leading-tight font-semibold tracking-tight text-balance @3xl:text-[32px]">
                    {title}
                </h1>
                {description && (
                    <p className="max-w-3xl text-sm text-pretty text-muted-foreground @3xl:text-base">
                        {description}
                    </p>
                )}
                {meta}
            </div>

            {actions && (
                <div className="flex flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </header>
    );
}
