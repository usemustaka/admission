import { useState } from 'react';
import { ChoiceCardGroup } from '@/components/choice-card-group';
import { FormField } from '@/components/form-field';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { TriangleAlert } from 'lucide-react';

export type CorrectionTarget = 'rejected' | 'pending';

const TARGETS = [
    {
        value: 'rejected',
        label: 'Tolak',
        description: 'Uangnya tidak pernah masuk. Orang tua melihat alasannya.',
    },
    {
        value: 'pending',
        label: 'Kembalikan ke antrean',
        description:
            'Diperiksa dan diverifikasi ulang, misalnya dengan pembagian lain.',
    },
] as const;

/**
 * Asks why a payment is taken back (verified) or reopened (rejected). The reason is mandatory and becomes part of
 * the audit trail. Taking back a payment never rolls back a registrant who already moved on, so the dialog says so.
 */
export function PaymentCorrectionDialog({
    mode,
    open,
    onOpenChange,
    processing,
    error,
    targetError,
    onSubmit,
}: {
    mode: 'void' | 'reopen';
    open: boolean;
    onOpenChange: (open: boolean) => void;
    processing: boolean;
    error?: string;
    targetError?: string;
    onSubmit: (values: { reason: string; to: CorrectionTarget }) => void;
}) {
    const [reason, setReason] = useState('');
    const [to, setTo] = useState<CorrectionTarget>('rejected');
    const isVoid = mode === 'void';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        onSubmit({ reason: reason.trim(), to });
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>
                            {isVoid
                                ? 'Batalkan pembayaran ini?'
                                : 'Buka ulang pembayaran ini?'}
                        </DialogTitle>
                        <DialogDescription>
                            {isVoid
                                ? 'Pembayaran tidak lagi dihitung ke tagihan. Siapa yang memverifikasi dan alasanmu dicatat di profil pembayar dan anak.'
                                : 'Pembayaran kembali menunggu verifikasi dengan pembagian semula. Alasan penolakan sebelumnya disimpan di catatan.'}
                        </DialogDescription>
                    </DialogHeader>

                    {isVoid && (
                        <>
                            <FormField
                                label="Setelah dibatalkan"
                                error={targetError}
                            >
                                {() => (
                                    <ChoiceCardGroup
                                        columns={1}
                                        value={to}
                                        onValueChange={(value) =>
                                            setTo(value as CorrectionTarget)
                                        }
                                        options={[...TARGETS]}
                                    />
                                )}
                            </FormField>
                            <Alert variant="warning">
                                <TriangleAlert />
                                <AlertTitle>
                                    Status pendaftar tidak ikut mundur
                                </AlertTitle>
                                <AlertDescription>
                                    Kalau pendaftar sudah maju (misalnya
                                    diterima), statusnya tetap. Cek dan ubah
                                    sendiri bila perlu.
                                </AlertDescription>
                            </Alert>
                        </>
                    )}

                    <FormField label="Alasan" required error={error}>
                        {(control) => (
                            <Textarea
                                autoFocus
                                maxLength={480}
                                className="min-h-20"
                                placeholder={
                                    isVoid
                                        ? 'Contoh: salah input nominal, dana tidak masuk'
                                        : 'Contoh: orang tua kirim bukti yang benar lewat WA'
                                }
                                value={reason}
                                onChange={(event) =>
                                    setReason(event.target.value)
                                }
                                {...control}
                            />
                        )}
                    </FormField>

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant={isVoid ? 'destructive' : 'default'}
                            disabled={processing || reason.trim() === ''}
                        >
                            {processing && <Spinner />}
                            {isVoid ? 'Batalkan pembayaran' : 'Buka ulang'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
