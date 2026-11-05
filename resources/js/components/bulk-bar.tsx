import { X } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * Floating bar that appears while rows are selected. Put the bulk actions
 * in `children`; it sits above the mobile tab bar and stays out of the way
 * of the table.
 */
export function BulkBar({
    count,
    noun,
    onClear,
    children,
}: {
    count: number;
    /** What is selected, e.g. "pendaftar". */
    noun: string;
    onClear: () => void;
    children: ReactNode;
}) {
    if (count === 0) {
        return null;
    }

    return (
        <div
            role="region"
            aria-label="Aksi untuk pilihan"
            className="anim-up fixed inset-x-3 bottom-[88px] z-30 mx-auto flex max-w-fit flex-wrap items-center justify-center gap-x-4 gap-y-2 rounded-2xl bg-neutral-900 px-4 py-3 text-white shadow-float md:bottom-6 dark:bg-neutral-100 dark:text-neutral-900"
        >
            <p className="flex items-center gap-2 text-sm font-semibold tabular-nums">
                <button
                    type="button"
                    onClick={onClear}
                    aria-label="Batalkan pilihan"
                    className="grid size-7 place-items-center rounded-full hover:bg-white/15 dark:hover:bg-black/10"
                >
                    <X className="size-4" />
                </button>
                {count} {noun} dipilih
            </p>
            <div className="flex flex-wrap items-center gap-2">{children}</div>
        </div>
    );
}
