import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

export function SectionCard({
    title,
    description,
    icon: Icon,
    actions,
    className,
    contentClassName,
    children,
}: {
    title?: string;
    description?: string;
    icon?: LucideIcon;
    actions?: ReactNode;
    className?: string;
    contentClassName?: string;
    children: ReactNode;
}) {
    return (
        <Card className={cn('gap-5', className)}>
            {(title || description || actions) && (
                <CardHeader className="flex flex-row items-start justify-between gap-4">
                    <div className="grid gap-1">
                        {title && (
                            <CardTitle className="flex items-center gap-2 text-lg">
                                {Icon && (
                                    <Icon className="size-5 text-primary" />
                                )}
                                {title}
                            </CardTitle>
                        )}
                        {description && (
                            <CardDescription>{description}</CardDescription>
                        )}
                    </div>
                    {actions && (
                        <div className="flex shrink-0 items-center gap-2">
                            {actions}
                        </div>
                    )}
                </CardHeader>
            )}
            <CardContent className={contentClassName}>{children}</CardContent>
        </Card>
    );
}
