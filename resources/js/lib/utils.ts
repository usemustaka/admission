import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

function hashString(value: string): number {
    let hash = 0;

    for (let index = 0; index < value.length; index++) {
        hash = (hash * 31 + value.charCodeAt(index)) >>> 0;
    }

    return hash;
}

/**
 * Flat colour picked from a name, so the same person always gets the same
 * avatar colour. Dark enough for white initials.
 */
export function nameColor(name: string): string {
    return `hsl(${hashString(name) % 360} 45% 42%)`;
}
