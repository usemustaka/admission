import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import { FormField } from '@/components/form-field';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
import { cancelPlacement } from '@/routes/admin/students';

/**
 * Undoes a placement: the student record is deleted and the registration goes back to "Sudah diumumkan". The
 * reason is mandatory and stays in the history. Mount it only while it is open.
 */
export function CancelPlacementDialog({
    studentId,
    childName,
    onClose,
}: {
    studentId: number;
    childName: string;
    onClose: () => void;
}) {
    const form = useForm({ reason: '' });
    const errors = form.errors as Record<string, string>;

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(cancelPlacement(studentId).url, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: (problems) => {
                if (problems.student) {
                    toast.error(problems.student);
                }
            },
        });
    }

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>Batalkan penempatan?</DialogTitle>
                        <DialogDescription>
                            {childName} tidak lagi tercatat sebagai siswa, dan
                            pendaftarannya kembali ke &quot;Sudah
                            diumumkan&quot; supaya bisa ditempatkan ulang.
                        </DialogDescription>
                    </DialogHeader>

                    <Alert variant="warning">
                        <AlertDescription>
                            Nomor induk siswa ini dilepas. Data pendaftaran,
                            tagihan, dan pembayaran tidak berubah.
                        </AlertDescription>
                    </Alert>

                    <FormField label="Alasan" required error={errors.reason}>
                        {(control) => (
                            <Textarea
                                className="min-h-24"
                                placeholder="Misalnya: salah memilih lembaga."
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                                {...control}
                            />
                        )}
                    </FormField>
                    {errors.student && (
                        <p className="text-sm font-medium text-destructive-foreground">
                            {errors.student}
                        </p>
                    )}

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={form.processing}
                        >
                            {form.processing && <Spinner />}
                            Batalkan penempatan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
