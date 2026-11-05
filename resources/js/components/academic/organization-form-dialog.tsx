import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
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
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { store, update } from '@/routes/admin/organizations';
import type { OrganizationRow } from '@/types/academic';

/**
 * Create or edit a lembaga. The code is how the lembaga is told apart elsewhere, so it is only editable while
 * nothing uses it. Mount it only while it is open.
 */
export function OrganizationFormDialog({
    organization,
    onClose,
}: {
    organization?: OrganizationRow;
    onClose: () => void;
}) {
    const form = useForm({
        name: organization?.name ?? '',
        code: organization?.code ?? '',
        category: organization?.category ?? '',
        is_active: organization?.is_active ?? true,
    });
    const errors = form.errors as Record<string, string>;
    const codeLocked = organization?.in_use === true;

    function submit(event: FormEvent) {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => onClose() };

        if (organization) {
            form.put(update(organization.id).url, options);
        } else {
            form.post(store().url, options);
        }
    }

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>
                            {organization ? 'Ubah lembaga' : 'Lembaga baru'}
                        </DialogTitle>
                        <DialogDescription>
                            Lembaga yang dinonaktifkan tidak muncul di
                            pendaftaran baru dan aturan baru, tapi datanya yang
                            lama tetap utuh.
                        </DialogDescription>
                    </DialogHeader>

                    <FormField
                        label="Nama lembaga"
                        required
                        error={errors.name}
                    >
                        {(control) => (
                            <Input
                                placeholder="Contoh: SMP Mustaka"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                {...control}
                            />
                        )}
                    </FormField>

                    <FormField
                        label="Kode"
                        required
                        error={errors.code}
                        hint={
                            codeLocked
                                ? 'Kode terkunci karena lembaga ini sudah dipakai aturan, pendaftar, siswa, atau pegawai.'
                                : 'Huruf kecil, angka, dan tanda hubung, misalnya smp-1. Tidak bisa diubah setelah lembaga dipakai.'
                        }
                    >
                        {(control) => (
                            <Input
                                placeholder="Contoh: smp"
                                value={form.data.code}
                                disabled={codeLocked}
                                autoCapitalize="none"
                                onChange={(e) =>
                                    form.setData(
                                        'code',
                                        e.target.value.toLowerCase(),
                                    )
                                }
                                {...control}
                            />
                        )}
                    </FormField>

                    <FormField
                        label="Kategori (boleh kosong)"
                        error={errors.category}
                    >
                        {(control) => (
                            <Input
                                placeholder="Contoh: smp, sma, pesantren"
                                value={form.data.category}
                                onChange={(e) =>
                                    form.setData('category', e.target.value)
                                }
                                {...control}
                            />
                        )}
                    </FormField>

                    <label className="flex min-h-10 items-center gap-3 text-sm font-medium">
                        <Switch
                            checked={form.data.is_active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked)
                            }
                        />
                        Aktif untuk pendaftaran baru
                    </label>

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {organization ? 'Simpan perubahan' : 'Buat lembaga'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
