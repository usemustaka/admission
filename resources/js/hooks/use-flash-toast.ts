import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            toast[data.type](data.message);
        });
    }, []);
}

/**
 * Shows the first validation error of a failed visit as a toast, so an action never fails without a word.
 * Errors the caller already shows next to a field are left out through `shownInline`.
 */
export function toastFirstError(
    errors: Record<string, string>,
    shownInline: (key: string) => boolean = () => false,
): void {
    const message = Object.entries(errors).find(
        ([key]) => !shownInline(key),
    )?.[1];

    if (message) {
        toast.error(message);
    }
}
