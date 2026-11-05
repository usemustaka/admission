import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export function EmptyState({
    title,
    description,
    icon: Icon,
    action,
}: {
    title: string;
    description?: string;
    icon?: LucideIcon;
    action?: ReactNode;
}) {
    return (
        <div className="grid place-items-center rounded-2xl border border-dashed border-neutral-300 px-6 py-14 text-center dark:border-border">
            {Icon && (
                <span className="mb-4 grid size-16 place-items-center rounded-full bg-lime-100 text-lime-700 dark:bg-lime-400/15 dark:text-lime-300">
                    <Icon className="size-7" />
                </span>
            )}

            <h3 className="text-lg font-semibold tracking-tight">{title}</h3>
            {description && (
                <p className="mt-1 max-w-sm text-[15px] text-pretty text-muted-foreground">
                    {description}
                </p>
            )}

            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}
