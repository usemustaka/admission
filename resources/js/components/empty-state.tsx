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
        <div className="flex flex-col items-center justify-center gap-4 rounded-2xl border border-dashed border-sage-300 bg-sage-50/60 px-6 py-14 text-center dark:border-border dark:bg-card/40">
            {Icon && (
                <span className="flex size-14 items-center justify-center rounded-full bg-sage-100 text-sage-700 dark:bg-secondary dark:text-primary">
                    <Icon className="size-6" />
                </span>
            )}

            <div className="space-y-1.5">
                <h3 className="text-lg font-semibold tracking-tight">
                    {title}
                </h3>
                {description && (
                    <p className="mx-auto max-w-sm text-sm text-pretty text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>

            {action && <div className="mt-1">{action}</div>}
        </div>
    );
}
