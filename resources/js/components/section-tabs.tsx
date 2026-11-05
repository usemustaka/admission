import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

const tabClass = (selected: boolean) =>
    cn(
        '-mb-px inline-flex items-center gap-2 border-b-2 pb-3 text-[15px] font-semibold whitespace-nowrap transition-colors outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
        selected
            ? 'border-foreground text-foreground'
            : 'border-transparent text-muted-foreground hover:text-foreground',
    );

export type ValueTabItem<T extends string> = {
    value: T;
    label: string;
    count?: number;
};

/**
 * Underlined tabs that switch a panel on the same page.
 */
export function ValueTabs<T extends string>({
    value,
    onChange,
    items,
    label,
}: {
    value: T;
    onChange: (value: T) => void;
    items: ValueTabItem<T>[];
    label: string;
}) {
    return (
        <div
            role="tablist"
            aria-label={label}
            className="no-scrollbar flex gap-6 overflow-x-auto border-b border-line"
        >
            {items.map((item) => (
                <button
                    key={item.value}
                    type="button"
                    role="tab"
                    aria-selected={item.value === value}
                    onClick={() => onChange(item.value)}
                    className={tabClass(item.value === value)}
                >
                    {item.label}
                    {item.count !== undefined && (
                        <span className="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-semibold text-muted-foreground tabular-nums dark:bg-neutral-700/50">
                            {item.count}
                        </span>
                    )}
                </button>
            ))}
        </div>
    );
}

export type LinkTabItem = {
    href: NonNullable<InertiaLinkProps['href']>;
    label: string;
    selected: boolean;
    badge?: ReactNode;
};

/**
 * Underlined tabs that are real links, for sibling pages of one section.
 */
export function LinkTabs({
    items,
    label,
    bare = false,
}: {
    items: LinkTabItem[];
    label: string;
    /** Drop the baseline, for placing the tabs on an edge that has its own. */
    bare?: boolean;
}) {
    return (
        <nav
            aria-label={label}
            className={cn(
                'no-scrollbar flex gap-6 overflow-x-auto',
                !bare && 'border-b border-line',
            )}
        >
            {items.map((item) => (
                <Link
                    key={item.label}
                    href={item.href}
                    prefetch
                    aria-current={item.selected ? 'page' : undefined}
                    className={cn(tabClass(item.selected), 'py-4')}
                >
                    {item.label}
                    {item.badge}
                </Link>
            ))}
        </nav>
    );
}
