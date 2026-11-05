import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

const tabs: { value: Appearance; icon: LucideIcon; label: string }[] = [
    { value: 'light', icon: Sun, label: 'Terang' },
    { value: 'dark', icon: Moon, label: 'Gelap' },
    { value: 'system', icon: Monitor, label: 'Otomatis' },
];

/**
 * Three big buttons to pick light, dark, or whatever the device uses. The
 * picked one is solid and ticked by `aria-pressed`, not only by colour.
 */
export default function AppearanceToggleTab({
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    const { appearance, updateAppearance } = useAppearance();

    return (
        <div
            role="group"
            aria-label="Pilih tampilan"
            className={cn(
                'grid grid-cols-3 gap-1 rounded-2xl bg-neutral-100 p-1 dark:bg-neutral-800',
                className,
            )}
            {...props}
        >
            {tabs.map(({ value, icon: Icon, label }) => (
                <button
                    key={value}
                    type="button"
                    aria-pressed={appearance === value}
                    onClick={() => updateAppearance(value)}
                    className={cn(
                        'flex min-h-12 items-center justify-center gap-2 rounded-xl px-3 text-[15px] font-semibold transition-colors outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                        appearance === value
                            ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900'
                            : 'text-muted-foreground hover:bg-neutral-200/70 hover:text-foreground dark:hover:bg-neutral-700/60',
                    )}
                >
                    <Icon className="size-5 shrink-0" />
                    {label}
                </button>
            ))}
        </div>
    );
}
