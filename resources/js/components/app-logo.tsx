import { usePage } from '@inertiajs/react';
import { BrandMark } from '@/components/brand-mark';
import { cn } from '@/lib/utils';

/**
 * Logo with the app name beside it. The name is hidden when `showName` is
 * false, or when the settings say the uploaded logo already carries it.
 */
export default function AppLogo({
    showName = true,
    size = 32,
    className,
}: {
    showName?: boolean;
    size?: number;
    className?: string;
}) {
    const { name, brand } = usePage().props;
    const withName = showName && (brand.show_name || !brand.logo_url);

    return (
        <span className={cn('inline-flex items-center gap-2.5', className)}>
            <BrandMark size={size} />
            {withName ? (
                <span className="truncate text-lg leading-tight font-extrabold tracking-tight">
                    {name}
                </span>
            ) : (
                <span className="sr-only">{name}</span>
            )}
        </span>
    );
}
