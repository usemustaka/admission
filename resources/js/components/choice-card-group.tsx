import type { LucideIcon } from 'lucide-react';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { cn } from '@/lib/utils';

export type ChoiceOption = {
    value: string;
    label: string;
    description?: string;
    icon?: LucideIcon;
    disabled?: boolean;
};

/**
 * Big tappable cards for picking one option, e.g. "Saya mendaftarkan sebagai
 * Ayah / Ibu / Wali". Columns stay fixed (also on a phone) because the
 * options are short; it also works inside dialogs, which sit outside any
 * container.
 */
export function ChoiceCardGroup({
    options,
    value,
    onValueChange,
    columns = 3,
    className,
}: {
    options: ChoiceOption[];
    value?: string;
    onValueChange: (value: string) => void;
    columns?: 1 | 2 | 3;
    className?: string;
}) {
    return (
        <RadioGroup
            value={value}
            onValueChange={onValueChange}
            className={cn(
                'gap-3',
                columns === 1 && 'grid-cols-1',
                columns === 2 && 'grid-cols-2',
                columns === 3 && 'grid-cols-3',
                className,
            )}
        >
            {options.map(
                ({
                    value: optionValue,
                    label,
                    description,
                    icon: Icon,
                    disabled,
                }) => (
                    <label
                        key={optionValue}
                        className={cn(
                            'flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border border-input bg-card p-3.5 transition-[border-color,background-color]',
                            'hover:border-neutral-500 has-[:checked]:border-neutral-900 has-[:checked]:bg-lime-50 has-[:checked]:ring-1 has-[:checked]:ring-neutral-900 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-ring dark:has-[:checked]:border-lime-400 dark:has-[:checked]:bg-lime-400/10 dark:has-[:checked]:ring-lime-400',
                            disabled &&
                                'cursor-not-allowed opacity-50 hover:border-input',
                        )}
                    >
                        <RadioGroupItem
                            value={optionValue}
                            disabled={disabled}
                        />
                        {Icon && (
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-lime-100 text-lime-700 dark:bg-lime-400/15 dark:text-lime-300">
                                <Icon className="size-5" />
                            </span>
                        )}
                        <span className="grid min-w-0 flex-1 gap-0.5">
                            <span className="text-[15px] font-semibold">
                                {label}
                            </span>
                            {description && (
                                <span className="text-sm text-muted-foreground">
                                    {description}
                                </span>
                            )}
                        </span>
                    </label>
                ),
            )}
        </RadioGroup>
    );
}
