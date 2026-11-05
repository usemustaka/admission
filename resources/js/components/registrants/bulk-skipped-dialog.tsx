import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { show } from '@/routes/admin/registrants';
import type { BulkSkipped } from '@/types/registrant';

/**
 * After a bulk action: who was skipped and why, with a link to each so the committee can fix it.
 */
export function BulkSkippedDialog({
    skipped,
    skippedTotal,
    done,
    onClose,
}: {
    skipped: BulkSkipped[] | null;
    /** How many were skipped in all; the list may show only the first of them. */
    skippedTotal: number;
    done: number;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={skipped !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {done} berhasil, {skippedTotal} dilewati
                    </DialogTitle>
                    <DialogDescription>
                        Yang dilewati tidak diubah sama sekali. Buka satu per
                        satu untuk melihat apa yang perlu dibereskan.
                    </DialogDescription>
                </DialogHeader>
                <ul className="grid max-h-80 gap-3 overflow-y-auto text-sm">
                    {skipped?.map((item) => (
                        <li
                            key={item.id}
                            className="flex items-start justify-between gap-3"
                        >
                            <span className="grid">
                                <span className="font-semibold">
                                    {item.name}
                                </span>
                                <span className="text-muted-foreground">
                                    {item.reason}
                                </span>
                            </span>
                            <Link
                                href={show(item.id)}
                                className="shrink-0 rounded-lg px-1 font-semibold underline underline-offset-4 outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                            >
                                Buka
                            </Link>
                        </li>
                    ))}
                </ul>
                {skipped && skippedTotal > skipped.length && (
                    <p className="text-sm text-muted-foreground">
                        Menampilkan {skipped.length} pertama dari {skippedTotal}{' '}
                        yang dilewati.
                    </p>
                )}
                <DialogFooter>
                    <Button onClick={onClose}>Mengerti</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
