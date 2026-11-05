import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { PersonAvatar } from '@/components/person-avatar';
import { StatusBadge } from '@/components/status-badge';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';

/**
 * A child's registration at a glance, shown on the parent's portal. The
 * whole card links to that child's focused view.
 */
export function ChildCard({
    name,
    photo,
    organizationName,
    yearName,
    status,
    stepIndex,
    stepCount,
    stepLabel,
    href,
    className,
}: {
    name: string;
    photo?: string | null;
    organizationName: string;
    yearName?: string;
    status: string;
    stepIndex: number;
    stepCount: number;
    stepLabel: string;
    href: NonNullable<InertiaLinkProps['href']>;
    className?: string;
}) {
    return (
        <Link
            href={href}
            className={cn(
                'group grid gap-4 rounded-2xl border bg-card p-5 shadow-card transition-shadow hover:shadow-lift focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                className,
            )}
        >
            <div className="flex items-center gap-4">
                <PersonAvatar name={name} src={photo} size="lg" />
                <div className="grid min-w-0 flex-1 gap-0.5">
                    <p className="truncate text-lg font-semibold tracking-tight">
                        {name}
                    </p>
                    <p className="truncate text-sm text-muted-foreground">
                        {[organizationName, yearName]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                </div>
                <ChevronRight className="size-5 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
            </div>

            <div className="grid gap-2">
                <div className="flex items-center justify-between gap-2">
                    <StatusBadge status={status} />
                    <span className="text-xs font-medium text-muted-foreground tabular-nums">
                        Tahap {Math.min(stepIndex + 1, stepCount)} dari{' '}
                        {stepCount}
                    </span>
                </div>
                <Progress
                    value={stepIndex}
                    max={stepCount}
                    aria-label={stepLabel}
                />
                <p className="text-sm text-muted-foreground">
                    Selanjutnya:{' '}
                    <span className="font-medium text-foreground">
                        {stepLabel}
                    </span>
                </p>
            </div>
        </Link>
    );
}
