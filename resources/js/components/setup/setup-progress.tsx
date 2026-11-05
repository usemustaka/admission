import { Check } from 'lucide-react';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';

type StepState = 'done' | 'current' | 'upcoming';

/**
 * Where the person is in the wizard: numbered steps with labels from the sm
 * breakpoint up, and "Langkah 2 dari 5" with a slim bar on a phone. Steps
 * already visited can be tapped to go back and check them.
 */
export function SetupProgress({
    labels,
    current,
    reached,
    errorSteps,
    onSelect,
}: {
    labels: readonly string[];
    current: number;
    reached: number;
    errorSteps: ReadonlySet<number>;
    onSelect: (index: number) => void;
}) {
    return (
        <nav aria-label="Langkah setup">
            <div className="grid gap-2.5 sm:hidden">
                <p className="flex items-baseline justify-between gap-3 text-[15px] font-semibold">
                    <span>
                        Langkah {current + 1} dari {labels.length}
                    </span>
                    <span className="truncate text-muted-foreground">
                        {labels[current]}
                    </span>
                </p>
                <Progress
                    value={current + 1}
                    max={labels.length}
                    aria-label={`Langkah ${current + 1} dari ${labels.length}`}
                />
            </div>

            <ol className="hidden items-start sm:flex">
                {labels.map((label, index) => {
                    const state: StepState =
                        index < current
                            ? 'done'
                            : index === current
                              ? 'current'
                              : 'upcoming';
                    const hasError = errorSteps.has(index);
                    const canOpen = index <= reached && index !== current;
                    const body = (
                        <>
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'relative z-10 flex size-9 items-center justify-center rounded-full border-2 text-sm font-bold transition-colors',
                                    state === 'done' &&
                                        'border-lime-400 bg-lime-400 text-primary-foreground',
                                    state === 'current' &&
                                        'border-neutral-900 bg-neutral-900 text-white ring-4 ring-lime-300/60 dark:border-lime-400 dark:bg-lime-400 dark:text-neutral-900',
                                    state === 'upcoming' &&
                                        'border-neutral-300 bg-card text-muted-foreground dark:border-neutral-600',
                                    hasError &&
                                        'border-destructive bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-300',
                                )}
                            >
                                {hasError ? (
                                    '!'
                                ) : state === 'done' ? (
                                    <Check className="size-4" strokeWidth={3} />
                                ) : (
                                    index + 1
                                )}
                            </span>
                            <span
                                className={cn(
                                    'text-[13px] leading-tight font-semibold',
                                    state === 'upcoming' &&
                                        'text-muted-foreground',
                                )}
                            >
                                {label}
                                <span className="sr-only">
                                    {state === 'done' && ', selesai'}
                                    {state === 'current' && ', sedang berjalan'}
                                    {state === 'upcoming' && ', belum dimulai'}
                                    {hasError &&
                                        ', ada isian yang perlu diperbaiki'}
                                </span>
                            </span>
                        </>
                    );

                    return (
                        <li
                            key={label}
                            aria-current={
                                state === 'current' ? 'step' : undefined
                            }
                            className="relative flex flex-1 justify-center"
                        >
                            {index < labels.length - 1 && (
                                <span
                                    aria-hidden="true"
                                    className={cn(
                                        'absolute top-[17px] left-[calc(50%+26px)] h-0.5 w-[calc(100%-52px)]',
                                        state === 'done'
                                            ? 'bg-lime-400'
                                            : 'bg-neutral-200 dark:bg-neutral-700',
                                    )}
                                />
                            )}
                            {canOpen ? (
                                <button
                                    type="button"
                                    onClick={() => onSelect(index)}
                                    className="flex min-h-11 w-full flex-col items-center gap-2 rounded-lg px-1 text-center outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                >
                                    {body}
                                </button>
                            ) : (
                                <div className="flex w-full flex-col items-center gap-2 px-1 text-center">
                                    {body}
                                </div>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
