import { cn } from '@/lib/utils';

/**
 * An on/off pill for a filter that stands on its own (several can be on together), styled like `FilterPills`
 * so the two read as one family.
 */
export function FilterToggle({
    pressed,
    onPressedChange,
    children,
}: {
    pressed: boolean;
    onPressedChange: (pressed: boolean) => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            aria-pressed={pressed}
            onClick={() => onPressedChange(!pressed)}
            className={cn(
                'h-10 rounded-full border px-4 text-sm font-semibold whitespace-nowrap transition-colors outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                pressed
                    ? 'border-neutral-900 bg-neutral-900 text-white dark:border-neutral-100 dark:bg-neutral-100 dark:text-neutral-900'
                    : 'border-neutral-300 hover:border-foreground dark:border-neutral-600',
            )}
        >
            {children}
        </button>
    );
}
