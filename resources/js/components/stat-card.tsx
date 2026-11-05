import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

type Tone = 'default' | 'warning' | 'danger' | 'success';

const toneText: Record<Tone, string> = {
    default: 'text-muted-foreground',
    warning: 'font-semibold text-amber-700 dark:text-amber-300',
    danger: 'font-semibold text-red-700 dark:text-red-300',
    success: 'font-semibold text-emerald-700 dark:text-emerald-300',
};

/**
 * One headline figure. With `href` the whole card is a link, so a number can
 * lead straight to the list it counts.
 */
export function StatCard({
    label,
    value,
    hint,
    tone = 'default',
    icon: Icon,
    href,
}: {
    label: string;
    value: string | number;
    hint?: ReactNode;
    tone?: Tone;
    icon?: LucideIcon;
    href?: NonNullable<InertiaLinkProps['href']>;
}) {
    const body = (
        <Card
            className={cn(
                'h-full gap-0 p-4 transition-shadow @2xl:p-5',
                href && 'group-hover:shadow-card',
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <span className="text-sm font-medium text-muted-foreground">
                    {label}
                </span>
                {Icon && (
                    <span className="grid size-9 shrink-0 place-items-center rounded-full bg-lime-100 text-lime-700 dark:bg-lime-400/15 dark:text-lime-300">
                        <Icon className="size-[18px]" />
                    </span>
                )}
            </div>
            <p className="mt-1.5 text-2xl leading-none font-semibold tracking-tight tabular-nums @3xl:text-[32px]">
                {value}
            </p>
            {hint && (
                <p className={cn('mt-2 text-sm', toneText[tone])}>{hint}</p>
            )}
        </Card>
    );

    if (!href) {
        return body;
    }

    return (
        <Link
            href={href}
            prefetch
            className="group block rounded-2xl outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >
            {body}
        </Link>
    );
}
