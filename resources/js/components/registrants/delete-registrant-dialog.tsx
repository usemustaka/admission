import { useForm } from '@inertiajs/react';
import { FormField } from '@/components/form-field';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { toastFirstError } from '@/hooks/use-flash-toast';
import { bulkDelete, destroy } from '@/routes/admin/registrants';

/**
 * Deletes one registrant (`ids` omitted) or the ticked ones. A reason is required and kept as a private note on the
 * child; the parent's login can go too, but only when that parent has no other registration.
 */
export function DeleteRegistrantDialog({
    open,
    onOpenChange,
    registrantId,
    ids,
    name,
    onDone,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    registrantId?: number;
    ids?: number[];
    name?: string;
    onDone?: () => void;
}) {
    const form = useForm({ reason: '', delete_account: false, ids: ids ?? [] });
    const errors = form.errors as Record<string, string>;
    const bulk = registrantId === undefined;
    const label = bulk
        ? `${ids?.length ?? 0} pendaftar`
        : (name ?? 'pendaftar ini');

    function submit() {
        form.transform((data) =>
            bulk
                ? data
                : { reason: data.reason, delete_account: data.delete_account },
        );
        form.setData('ids', ids ?? []);

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
                onDone?.();
            },
            onError: (formErrors: Record<string, string>) =>
                toastFirstError(formErrors, (key) =>
                    ['reason', 'ids', 'registrant'].includes(key),
                ),
        };

        if (bulk) {
            form.post(bulkDelete().url, options);
        } else {
            form.delete(destroy(registrantId).url, options);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form
                    className="grid gap-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>Hapus {label}?</DialogTitle>
                        <DialogDescription>
                            Data pendaftaran, tagihan, dan berkas yang diunggah
                            ikut terhapus dan tidak bisa dikembalikan. Yang
                            sudah ditempatkan sebagai siswa atau sudah ada
                            pembayarannya tidak akan dihapus.
                        </DialogDescription>
                    </DialogHeader>
                    <FormField
                        label="Alasan"
                        required
                        error={errors.reason ?? errors.ids}
                    >
                        {(control) => (
                            <Textarea
                                className="min-h-20"
                                placeholder="Mis. data uji coba, ganda, atau tidak valid"
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                                {...control}
                            />
                        )}
                    </FormField>
                    <label className="flex items-start gap-3 text-sm">
                        <Switch
                            checked={form.data.delete_account}
                            onCheckedChange={(checked) =>
                                form.setData('delete_account', checked)
                            }
                        />
                        <span>
                            <span className="font-semibold">
                                Hapus juga akun login orang tua
                            </span>
                            <span className="block text-muted-foreground">
                                Hanya bila orang tua itu tidak punya pendaftar
                                lain.
                            </span>
                        </span>
                    </label>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={form.processing}
                        >
                            {form.processing && <Spinner />}
                            Ya, hapus
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
