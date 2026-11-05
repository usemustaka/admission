import { cn } from '@/lib/utils';

export type FilterPillItem<T extends string> = {
    value: T;
    label: string;
    count?: number;
};

/**
 * A row of pills that narrows a list by one value, for example a status. The
 * selected pill turns solid so the active filter is obvious at a glance.
 */
export function FilterPills<T extends string>({
    value,
    onChange,
    items,
    label,
    className,
}: {
    value: T;
    onChange: (value: T) => void;
    items: FilterPillItem<T>[];
    label: string;
    className?: string;
}) {
    return (
        <div
            role="group"
            aria-label={label}
            className={cn('no-scrollbar flex gap-2 overflow-x-auto', className)}
        >
            {items.map((item) => {
                const selected = item.value === value;

                return (
                    <button
                        key={item.value}
                        type="button"
                        aria-pressed={selected}
                        onClick={() => onChange(item.value)}
                        className={cn(
                            'rounded-full border px-4 py-2 text-sm font-semibold whitespace-nowrap transition-colors outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                            selected
                                ? 'border-neutral-900 bg-neutral-900 text-white dark:border-neutral-100 dark:bg-neutral-100 dark:text-neutral-900'
                                : 'border-neutral-300 hover:border-foreground dark:border-neutral-600',
                        )}
                    >
                        {item.label}
                        {item.count !== undefined && (
                            <span
                                className={cn(
                                    'ml-1.5 font-medium tabular-nums',
                                    selected
                                        ? 'text-neutral-400 dark:text-neutral-500'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {item.count}
                            </span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}
