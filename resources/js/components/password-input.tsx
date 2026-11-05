import { Eye, EyeOff } from 'lucide-react';
import type { ComponentProps, Ref } from 'react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/**
 * Password field with a 44px eye button to show what was typed, which helps
 * on a phone keyboard.
 */
export default function PasswordInput({
    className,
    ref,
    ...props
}: Omit<ComponentProps<'input'>, 'type'> & { ref?: Ref<HTMLInputElement> }) {
    const [showPassword, setShowPassword] = useState(false);

    return (
        <div className="relative">
            <Input
                type={showPassword ? 'text' : 'password'}
                className={cn('pr-12', className)}
                ref={ref}
                {...props}
            />
            <button
                type="button"
                onClick={() => setShowPassword((prev) => !prev)}
                className="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-xl text-muted-foreground outline-none hover:text-foreground focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring"
                aria-label={
                    showPassword
                        ? 'Sembunyikan kata sandi'
                        : 'Tampilkan kata sandi'
                }
                aria-pressed={showPassword}
            >
                {showPassword ? (
                    <EyeOff className="size-5" />
                ) : (
                    <Eye className="size-5" />
                )}
            </button>
        </div>
    );
}
