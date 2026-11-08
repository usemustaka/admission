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
    compact = false,
    children,
}: {
    title?: string;
    description?: string;
    icon?: LucideIcon;
    actions?: ReactNode;
    className?: string;
    contentClassName?: string;
    /** Tighter spacing and a fixed title size, for cards that sit side by side in a dense detail page. */
    compact?: boolean;
    children: ReactNode;
}) {
    return (
        <Card className={cn(compact ? 'gap-3' : 'gap-4 @2xl:gap-5', className)}>
            {(title || description || actions) && (
                <CardHeader className="flex flex-row items-start justify-between gap-4">
                    <div className="grid gap-1">
                        {title && (
                            <CardTitle
                                className={cn(
                                    'flex items-center gap-2 text-base font-semibold',
                                    !compact && '@2xl:text-lg',
                                )}
                            >
                                {Icon && (
                                    <Icon className="size-5 text-muted-foreground" />
                                )}
                                {title}
                            </CardTitle>
                        )}
                        {description && (
                            <CardDescription
                                className={cn(
                                    'text-sm',
                                    !compact && '@2xl:text-[15px]',
                                )}
                            >
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
