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
import { show } from '@/routes/admin/payments';

export type PaymentBulkSkipped = { id: number; label: string; reason: string };

/**
 * After verifying in bulk: every payment that was skipped and why, in a scrollable list with a link to each.
 */
export function PaymentBulkSkippedDialog({
    skipped,
    done,
    onClose,
}: {
    skipped: PaymentBulkSkipped[] | null;
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
                        {done} diverifikasi, {skipped?.length ?? 0} dilewati
                    </DialogTitle>
                    <DialogDescription>
                        Yang dilewati tidak diubah sama sekali. Buka satu per
                        satu untuk memeriksanya.
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
                                    {item.label}
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
                <DialogFooter>
                    <Button onClick={onClose}>Mengerti</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
