import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const ANY = '__any__';

/**
 * Select that can also mean "no choice" (for example "Semua lembaga"), which
 * is stored as an empty string.
 */
export function OptionSelect({
    value,
    onValueChange,
    options,
    anyLabel,
    placeholder = 'Pilih',
    ...props
}: {
    value: string;
    onValueChange: (value: string) => void;
    options: { value: string | number; label: string }[];
    /** When set, adds an option with this label that clears the value. */
    anyLabel?: string;
    placeholder?: string;
} & Omit<React.ComponentProps<typeof SelectTrigger>, 'value' | 'onChange'>) {
    return (
        <Select
            value={value === '' && anyLabel ? ANY : value || undefined}
            onValueChange={(next) => onValueChange(next === ANY ? '' : next)}
        >
            <SelectTrigger className="w-full" {...props}>
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {anyLabel && <SelectItem value={ANY}>{anyLabel}</SelectItem>}
                {options.map((option) => (
                    <SelectItem key={option.value} value={String(option.value)}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
