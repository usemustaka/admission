import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { ArrowRight, ChevronRight } from 'lucide-react';
import { PersonAvatar } from '@/components/person-avatar';
import { TurnBadge } from '@/components/portal/turn-badge';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import type { NextAction } from '@/lib/portal-next-step';
import { cn } from '@/lib/utils';

/**
 * A child's registration at a glance, on the parent's home. The name opens
 * that child's page; when `next` is given the card also says what to do next
 * and offers the one button for it.
 */
export function ChildCard({
    name,
    photo,
    organizationName,
    waveName,
    status,
    stepIndex,
    stepCount,
    stepLabel,
    href,
    next,
    className,
}: {
    name: string;
    photo?: string | null;
    organizationName: string;
    waveName?: string;
    status: string;
    stepIndex: number;
    stepCount: number;
    stepLabel: string;
    href: NonNullable<InertiaLinkProps['href']>;
    next?: NextAction;
    className?: string;
}) {
    return (
        <article
            className={cn(
                'grid gap-3 rounded-2xl border border-line bg-card p-4',
                className,
            )}
        >
            <Link
                href={href}
                className="group -m-1 flex items-center gap-3 rounded-xl p-1 outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                <PersonAvatar name={name} src={photo} size="lg" />
                <div className="grid min-w-0 flex-1 gap-0.5">
                    <p className="truncate text-lg font-semibold tracking-tight">
                        {name}
                    </p>
                    <p className="truncate text-[15px] text-muted-foreground">
                        {[organizationName, waveName]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                </div>
                <ChevronRight className="size-5 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
            </Link>

            <div className="grid gap-2">
                <div className="flex items-center justify-between gap-2">
                    <StatusBadge status={status} />
                    <span className="text-sm font-medium text-muted-foreground tabular-nums">
                        Tahap {Math.min(stepIndex + 1, stepCount)} dari{' '}
                        {stepCount}
                    </span>
                </div>
                <Progress
                    value={Math.min(stepIndex + 1, stepCount)}
                    max={stepCount}
                    aria-label={`Tahap ${stepLabel}`}
                />
            </div>

            {next ? (
                <div className="grid gap-3 border-t border-line pt-4">
                    <div className="grid justify-items-start gap-2">
                        <TurnBadge turn={next.turn} label={next.turnLabel} />
                        <p className="text-base font-semibold">{next.title}</p>
                        <p className="text-[15px] text-muted-foreground">
                            {next.description}
                        </p>
                    </div>
                    <Button
                        asChild
                        size="lg"
                        variant={next.cta ? 'default' : 'outline'}
                        className="w-full"
                    >
                        <Link href={next.cta?.href ?? href}>
                            {next.cta?.label ?? 'Lihat detail'}
                            <ArrowRight />
                        </Link>
                    </Button>
                </div>
            ) : (
                <p className="border-t border-line pt-4 text-[15px] text-muted-foreground">
                    Selanjutnya:{' '}
                    <span className="font-semibold text-foreground">
                        {stepLabel}
                    </span>
                </p>
            )}
        </article>
    );
}
