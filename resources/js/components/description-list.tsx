import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type DescriptionItem = {
    label: string;
    value?: ReactNode;
    /** Spans the full row, for long values such as addresses. */
    wide?: boolean;
};

export function DescriptionList({
    items,
    className,
}: {
    items: DescriptionItem[];
    className?: string;
}) {
    return (
        <dl className={cn('grid gap-x-8 gap-y-5 sm:grid-cols-2', className)}>
            {items.map(({ label, value, wide }) => (
                <div
                    key={label}
                    className={cn('grid gap-1', wide && 'sm:col-span-2')}
                >
                    <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        {label}
                    </dt>
                    <dd className="text-sm font-medium break-words">
                        {value === undefined ||
                        value === null ||
                        value === '' ? (
                            <span className="text-muted-foreground">—</span>
                        ) : (
                            value
                        )}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
