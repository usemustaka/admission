import { Check } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type StepperStep = {
    key: string;
    label: string;
    description?: string;
    /** Extra content shown under the step while it is the current one. */
    content?: ReactNode;
};

type StepState = 'done' | 'current' | 'upcoming';

const stateWord: Record<StepState, string> = {
    done: 'Selesai',
    current: 'Sedang berjalan',
    upcoming: 'Belum dimulai',
};

function stateOf(index: number, currentIndex: number): StepState {
    if (index < currentIndex) {
        return 'done';
    }

    return index === currentIndex ? 'current' : 'upcoming';
}

/**
 * Progress tracker. `currentIndex` is the step being worked on; everything
 * before it is done. Pass `steps.length` to mark everything as done. The
 * state is spelled out in words, so it never relies on colour alone. The
 * horizontal layout only spreads out in a wide container.
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
                                        ? 'top-10 left-[19px] h-[calc(100%-2.5rem)] w-0.5'
                                        : 'top-[19px] left-[calc(50%+24px)] h-0.5 w-[calc(100%-48px)]',
                                    state === 'done'
                                        ? 'bg-lime-400'
                                        : 'bg-neutral-200 dark:bg-neutral-700',
                                )}
                            />
                        )}

                        <span
                            className={cn(
                                'relative z-10 flex size-10 shrink-0 items-center justify-center rounded-full border-2 text-[15px] font-bold transition-colors',
                                state === 'done' &&
                                    'border-lime-400 bg-lime-400 text-primary-foreground',
                                state === 'current' &&
                                    'border-neutral-900 bg-neutral-900 text-white ring-4 ring-lime-300/60 dark:border-lime-400 dark:bg-lime-400 dark:text-neutral-900',
                                state === 'upcoming' &&
                                    'border-neutral-300 bg-card text-muted-foreground dark:border-neutral-600',
                            )}
                        >
                            {state === 'done' ? (
                                <Check className="size-5" strokeWidth={3} />
                            ) : (
                                index + 1
                            )}
                        </span>

                        <span
                            className={cn(
                                'grid min-w-0 flex-1 content-start gap-1',
                                isVertical
                                    ? 'pt-1.5'
                                    : 'justify-items-center px-1',
                            )}
                        >
                            <span
                                className={cn(
                                    'text-base font-semibold',
                                    state === 'upcoming' &&
                                        'text-muted-foreground',
                                )}
                            >
                                {step.label}
                                <span className="sr-only">
                                    , {stateWord[state].toLowerCase()}
                                </span>
                            </span>
                            {state === 'current' && (
                                <span
                                    aria-hidden="true"
                                    className="w-fit rounded-full bg-lime-100 px-2.5 py-0.5 text-xs font-semibold text-lime-800 dark:bg-lime-400/15 dark:text-lime-300"
                                >
                                    {stateWord.current}
                                </span>
                            )}
                            {step.description && (
                                <span
                                    className={cn(
                                        'text-sm text-muted-foreground',
                                        !isVertical && 'hidden @5xl:block',
                                    )}
                                >
                                    {step.description}
                                </span>
                            )}
                            {state === 'current' && step.content}
                        </span>
                    </li>
                );
            })}
        </ol>
    );
}
