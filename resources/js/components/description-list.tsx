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
    compact = false,
}: {
    items: DescriptionItem[];
    className?: string;
    /** Two tight columns at any width, for dense cards that sit side by side. */
    compact?: boolean;
}) {
    return (
        <dl
            className={cn(
                'grid',
                compact
                    ? 'grid-cols-2 gap-x-4 gap-y-3'
                    : 'gap-x-8 gap-y-5 @2xl:grid-cols-2',
                className,
            )}
        >
            {items.map(({ label, value, wide }) => (
                <div
                    key={label}
                    className={cn(
                        'grid content-start gap-0.5',
                        wide && (compact ? 'col-span-2' : '@2xl:col-span-2'),
                    )}
                >
                    <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        {label}
                    </dt>
                    <dd
                        className={cn(
                            'font-medium break-words',
                            compact ? 'text-sm' : 'text-[15px]',
                        )}
                    >
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
