import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

export type StepperStep = {
    key: string;
    label: string;
    description?: string;
};

type StepState = 'done' | 'current' | 'upcoming';

function stateOf(index: number, currentIndex: number): StepState {
    if (index < currentIndex) {
        return 'done';
    }

    return index === currentIndex ? 'current' : 'upcoming';
}

/**
 * Progress tracker. `currentIndex` is the step being worked on; everything
 * before it is done. Pass `steps.length` to mark everything as done.
 */
export function Stepper({
    steps,
    currentIndex,
    orientation = 'horizontal',
    className,
}: {
    steps: StepperStep[];
    currentIndex: number;
    orientation?: 'horizontal' | 'vertical';
    className?: string;
}) {
    const isVertical = orientation === 'vertical';

    return (
        <ol
            className={cn(
                isVertical ? 'grid gap-0' : 'flex items-start',
                className,
            )}
            aria-label="Tahapan pendaftaran"
        >
            {steps.map((step, index) => {
                const state = stateOf(index, currentIndex);
                const isLast = index === steps.length - 1;

                return (
                    <li
                        key={step.key}
                        aria-current={state === 'current' ? 'step' : undefined}
                        className={cn(
                            isVertical
                                ? 'relative flex gap-4 pb-6 last:pb-0'
                                : 'relative flex flex-1 flex-col items-center gap-2 text-center',
                        )}
                    >
                        {!isLast && (
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'absolute',
                                    isVertical
                                        ? 'top-9 left-[17px] h-[calc(100%-2.25rem)] w-0.5'
                                        : 'top-[17px] left-[calc(50%+22px)] h-0.5 w-[calc(100%-44px)]',
                                    state === 'done'
                                        ? 'bg-primary'
                                        : 'bg-border',
                                )}
                            />
                        )}

                        <span
                            className={cn(
                                'relative z-10 flex size-9 shrink-0 items-center justify-center rounded-full border-2 text-sm font-bold transition-colors',
                                state === 'done' &&
                                    'border-primary bg-primary text-primary-foreground',
                                state === 'current' &&
                                    'border-primary bg-card text-primary ring-4 ring-primary/15',
                                state === 'upcoming' &&
                                    'border-border bg-card text-muted-foreground',
                            )}
                        >
                            {state === 'done' ? (
                                <Check className="size-4" strokeWidth={3} />
                            ) : (
                                index + 1
                            )}
                        </span>

                        <span
                            className={cn(
                                'grid gap-0.5',
                                isVertical ? 'pt-1.5' : 'px-1',
                            )}
                        >
                            <span
                                className={cn(
                                    'text-sm font-semibold',
                                    state === 'upcoming' &&
                                        'text-muted-foreground',
                                )}
                            >
                                {step.label}
                            </span>
                            {step.description && (
                                <span
                                    className={cn(
                                        'text-xs text-muted-foreground',
                                        !isVertical && 'hidden lg:block',
                                    )}
                                >
                                    {step.description}
                                </span>
                            )}
                        </span>
                    </li>
                );
            })}
        </ol>
    );
}
