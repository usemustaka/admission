import { usePage } from '@inertiajs/react';
import type { SVGAttributes } from 'react';

/**
 * Accent-coloured tile with the first letter of the app name, used when no
 * logo has been uploaded. Size it with `className` (for example `size-9`); the
 * tile follows the accent colour chosen in the settings.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    const { name } = usePage().props;
    const initial = (name.trim()[0] ?? '?').toUpperCase();

    return (
        <svg
            {...props}
            viewBox="0 0 32 32"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <rect
                width="32"
                height="32"
                rx="9"
                style={{ fill: 'var(--color-lime-400)' }}
            />
            <text
                x="16"
                y="16"
                textAnchor="middle"
                dominantBaseline="central"
                fontFamily="Figtree, system-ui, sans-serif"
                fontSize="19"
                fontWeight="800"
                style={{ fill: 'var(--primary-foreground)' }}
            >
                {initial}
            </text>
        </svg>
    );
}
