import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export function PageHeader({
    title,
    description,
    icon: Icon,
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
        <header className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div className="flex items-start gap-4">
                {Icon && (
                    <span className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-sage-100 text-sage-700 dark:bg-secondary dark:text-primary">
                        <Icon className="size-6" />
                    </span>
                )}

                <div className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight text-balance">
                        {title}
                    </h1>
                    {description && (
                        <p className="max-w-2xl text-sm text-pretty text-muted-foreground">
                            {description}
                        </p>
                    )}
                    {meta}
                </div>
            </div>

            {actions && (
                <div className="flex shrink-0 items-center gap-2">
                    {actions}
                </div>
            )}
        </header>
    );
}
