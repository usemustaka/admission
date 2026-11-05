import { X } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';

/**
 * Side panel for peeking at one record without leaving the list. On a
 * phone it rises from the bottom instead. Fill `footer` with the actions an
 * admin takes most often on that record.
 */
export function QuickView({
    open,
    onOpenChange,
    title,
    description,
    footer,
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: ReactNode;
    description?: string;
    footer?: ReactNode;
    children: ReactNode;
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="right"
                showCloseButton={false}
                className="w-full gap-0 p-0 sm:max-w-[28rem]"
            >
                <div className="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-line px-5">
                    <SheetTitle className="truncate text-base font-semibold">
                        {title}
                    </SheetTitle>
                    <button
                        type="button"
                        onClick={() => onOpenChange(false)}
                        aria-label="Tutup"
                        className="grid size-9 shrink-0 place-items-center rounded-full outline-none hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >
                        <X className="size-[18px]" />
                    </button>
                </div>
                <SheetDescription className="sr-only">
                    {description ?? 'Ringkasan data'}
                </SheetDescription>

                <div className="flex-1 overflow-y-auto p-5">{children}</div>

                {footer && (
                    <div className="flex shrink-0 flex-wrap gap-2 border-t border-line px-5 py-4">
                        {footer}
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}
