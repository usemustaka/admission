import type { LucideIcon } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';

export function StatCard({
    label,
    value,
    hint,
    icon: Icon,
}: {
    label: string;
    value: string | number;
    hint?: string;
    icon?: LucideIcon;
}) {
    return (
        <Card className="gap-0 py-5 transition-shadow duration-200 hover:shadow-lift">
            <CardContent className="flex items-start justify-between gap-4">
                <div className="min-w-0 space-y-1">
                    <p className="truncate text-sm font-medium text-muted-foreground">
                        {label}
                    </p>
                    <p className="text-3xl font-bold tracking-tight tabular-nums">
                        {value}
                    </p>
                    {hint && (
                        <p className="truncate text-xs text-muted-foreground">
                            {hint}
                        </p>
                    )}
                </div>

                {Icon && (
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-sage-100 text-sage-700 dark:bg-secondary dark:text-primary">
                        <Icon className="size-5" />
                    </span>
                )}
            </CardContent>
        </Card>
    );
}
