import { Search, X } from 'lucide-react';
import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export function SearchInput({
    value,
    onValueChange,
    placeholder = 'Cari…',
    className,
    ...props
}: Omit<ComponentProps<'input'>, 'value' | 'onChange' | 'type'> & {
    value: string;
    onValueChange: (value: string) => void;
}) {
    return (
        <div className={cn('relative', className)}>
            <Search
                className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <Input
                type="search"
                value={value}
                placeholder={placeholder}
                onChange={(event) => onValueChange(event.target.value)}
                className="rounded-full pr-10 pl-10 [&::-webkit-search-cancel-button]:hidden"
                {...props}
            />
            {value !== '' && (
                <button
                    type="button"
                    onClick={() => onValueChange('')}
                    className="absolute top-1/2 right-2.5 flex size-6 -translate-y-1/2 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    aria-label="Hapus pencarian"
                >
                    <X className="size-4" />
                </button>
            )}
        </div>
    );
}
