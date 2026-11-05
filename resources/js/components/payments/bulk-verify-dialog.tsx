import { Checkbox } from '@/components/ui/checkbox';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatRupiah } from '@/lib/format';

/**
 * Asks for the final go-ahead before verifying the selected payments, with how many they are and how much money
 * that is. Pending payments without a proof file are left out unless the committee ticks the box.
 */
export function BulkVerifyDialog({
    open,
    count,
    total,
    withoutProof,
    includeWithoutProof,
    processing,
    onIncludeWithoutProofChange,
    onOpenChange,
    onConfirm,
}: {
    open: boolean;
    count: number;
    total: number;
    /** How many of the selected payments are waiting and have no proof file. */
    withoutProof: number;
    includeWithoutProof: boolean;
    processing: boolean;
    onIncludeWithoutProofChange: (include: boolean) => void;
    onOpenChange: (open: boolean) => void;
    onConfirm: () => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        Verifikasi {count} pembayaran senilai{' '}
                        {formatRupiah(total)}?
                    </DialogTitle>
                    <DialogDescription>
                        Tiap pembayaran dicatat sesuai rincian yang dikirim.
                        Yang sudah diproses atau yang rinciannya sudah tidak
                        muat akan dilewati, dan alasannya kami tampilkan.
                    </DialogDescription>
                </DialogHeader>

                {withoutProof > 0 && (
                    <div className="flex items-start gap-3 rounded-xl border border-line bg-muted/50 p-3 text-sm">
                        <Checkbox
                            id="include-without-proof"
                            checked={includeWithoutProof}
                            onCheckedChange={(checked) =>
                                onIncludeWithoutProofChange(checked === true)
                            }
                            className="mt-0.5"
                        />
                        <Label
                            htmlFor="include-without-proof"
                            className="grid cursor-pointer gap-0.5 font-normal"
                        >
                            <span className="font-semibold">
                                Ikutkan {withoutProof} pembayaran tanpa bukti
                            </span>
                            <span className="text-muted-foreground">
                                Bawaannya dilewati. Centang hanya kalau uangnya
                                sudah kamu cek di rekening.
                            </span>
                        </Label>
                    </div>
                )}

                <DialogFooter className="gap-2">
                    <Button
                        variant="outline"
                        disabled={processing}
                        onClick={() => onOpenChange(false)}
                    >
                        Batal
                    </Button>
                    <Button disabled={processing} onClick={onConfirm}>
                        {processing && <Spinner />}
                        Verifikasi
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
