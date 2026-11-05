import { useId } from 'react';
import type { ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export type FormFieldControlProps = {
    id: string;
    'aria-invalid': boolean;
    'aria-describedby': string | undefined;
};

export function FormField({
    label,
    hint,
    error,
    required = false,
    htmlFor,
    className,
    children,
}: {
    label: string;
    hint?: string;
    error?: string;
    required?: boolean;
    htmlFor?: string;
    className?: string;
    children: (control: FormFieldControlProps) => ReactNode;
}) {
    const generatedId = useId();
    const id = htmlFor ?? generatedId;
    const messageId = `${id}-message`;
    const message = error ?? hint;

    return (
        <div className={cn('grid content-start gap-2', className)}>
            <Label htmlFor={id} className="text-[15px]">
                {label}
                {required && (
                    <span className="text-destructive" aria-hidden="true">
                        *
                    </span>
                )}
            </Label>

            {children({
                id,
                'aria-invalid': Boolean(error),
                'aria-describedby': message ? messageId : undefined,
            })}

            {message && (
                <p
                    id={messageId}
                    className={cn(
                        'text-[15px]',
                        error
                            ? 'font-medium text-destructive-foreground'
                            : 'text-muted-foreground',
                    )}
                >
                    {message}
                </p>
            )}
        </div>
    );
}
