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
 * Ayah / Ibu / Wali".
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
                columns === 2 && 'grid-cols-1 sm:grid-cols-2',
                columns === 3 && 'grid-cols-1 sm:grid-cols-3',
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
                            'flex cursor-pointer items-start gap-3 rounded-2xl border bg-card p-4 shadow-xs transition-[border-color,background-color,box-shadow]',
                            'hover:border-sage-400 has-[:checked]:border-primary has-[:checked]:bg-sage-50 has-[:checked]:ring-1 has-[:checked]:ring-primary has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50 dark:has-[:checked]:bg-secondary/40',
                            disabled &&
                                'cursor-not-allowed opacity-50 hover:border-border',
                        )}
                    >
                        {Icon && (
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-sage-100 text-sage-700 dark:bg-secondary dark:text-primary">
                                <Icon className="size-5" />
                            </span>
                        )}
                        <span className="grid flex-1 gap-0.5">
                            <span className="text-sm font-semibold">
                                {label}
                            </span>
                            {description && (
                                <span className="text-sm text-muted-foreground">
                                    {description}
                                </span>
                            )}
                        </span>
                        <RadioGroupItem
                            value={optionValue}
                            disabled={disabled}
                        />
                    </label>
                ),
            )}
        </RadioGroup>
    );
}
