import { cva } from 'class-variance-authority';
import type { VariantProps } from 'class-variance-authority';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

const avatarSize = cva('', {
    variants: {
        size: {
            sm: 'size-8 text-xs',
            md: 'size-10 text-sm',
            lg: 'size-14 text-base',
            xl: 'size-24 text-2xl',
        },
    },
    defaultVariants: { size: 'md' },
});

export function PersonAvatar({
    name,
    src,
    size,
    className,
}: {
    name: string;
    src?: string | null;
    className?: string;
} & VariantProps<typeof avatarSize>) {
    const getInitials = useInitials();

    return (
        <Avatar className={cn(avatarSize({ size }), className)}>
            {src && <AvatarImage src={src} alt={name} />}
            <AvatarFallback>{getInitials(name)}</AvatarFallback>
        </Avatar>
    );
}
