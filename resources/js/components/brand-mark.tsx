import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';

/**
 * The logo of this installation: the uploaded image when there is one,
 * otherwise a tile with the app name's initial. `size` is the height in px.
 */
export function BrandMark({ size = 32 }: { size?: number }) {
    const { brand } = usePage().props;

    if (brand.logo_url) {
        return (
            <img
                src={brand.logo_url}
                alt=""
                style={{
                    height: size,
                    width: 'auto',
                    maxWidth: size * 5,
                }}
                className="shrink-0 object-contain"
            />
        );
    }

    return <AppLogoIcon className="shrink-0" width={size} height={size} />;
}
