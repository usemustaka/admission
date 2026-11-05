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
        <Card className={cn('gap-4 @2xl:gap-5', className)}>
            {(title || description || actions) && (
                <CardHeader className="flex flex-row items-start justify-between gap-4">
                    <div className="grid gap-1">
                        {title && (
                            <CardTitle className="flex items-center gap-2 text-base font-semibold @2xl:text-lg">
                                {Icon && (
                                    <Icon className="size-5 text-muted-foreground" />
                                )}
                                {title}
                            </CardTitle>
                        )}
                        {description && (
                            <CardDescription className="text-sm @2xl:text-[15px]">
                                {description}
                            </CardDescription>
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
