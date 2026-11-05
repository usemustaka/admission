import { CircleCheck } from 'lucide-react';
import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';
import { digitsOnly, NIK_LENGTH } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * Number-only input for NIK (16 digits). The same NIK always points to the
 * same person across organizations, so we keep the value digits-only.
 */
export function NikInput({
    value,
    onValueChange,
    className,
    ...props
}: Omit<ComponentProps<'input'>, 'value' | 'onChange' | 'type'> & {
    value: string;
    onValueChange: (value: string) => void;
}) {
    const isComplete = value.length === NIK_LENGTH;

    return (
        <div className="relative">
            <Input
                type="text"
                inputMode="numeric"
                autoComplete="off"
                maxLength={NIK_LENGTH}
                placeholder="16 digit angka"
                value={value}
                onChange={(event) =>
                    onValueChange(
                        digitsOnly(event.target.value).slice(0, NIK_LENGTH),
                    )
                }
                className={cn('pr-20 tabular-nums', className)}
                {...props}
            />
            <span
                className={cn(
                    'pointer-events-none absolute top-1/2 right-3.5 flex -translate-y-1/2 items-center gap-1 text-xs font-medium tabular-nums',
                    isComplete ? 'text-success' : 'text-muted-foreground',
                )}
            >
                {isComplete ? (
                    <CircleCheck className="size-4" />
                ) : (
                    `${value.length}/${NIK_LENGTH}`
                )}
            </span>
        </div>
    );
}
