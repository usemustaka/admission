import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export default function InputError({
    message,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    return message ? (
        <p
            {...props}
            className={cn(
                'text-[15px] font-medium text-destructive-foreground',
                className,
            )}
        >
            {message}
        </p>
    ) : null;
}
