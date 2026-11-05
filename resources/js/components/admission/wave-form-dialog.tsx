import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { store, update } from '@/routes/admin/waves';
import type { Wave } from '@/types/admission';

export function WaveFormDialog({
    wave,
    trigger,
}: {
    wave?: Wave;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: wave?.name ?? '',
        starts_on: wave?.starts_on ?? '',
        ends_on: wave?.ends_on ?? '',
        is_open: wave?.is_open ?? true,
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        if (
            form.data.starts_on &&
            form.data.ends_on &&
            form.data.ends_on < form.data.starts_on
        ) {
            form.setError(
                'ends_on',
                'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            );

            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);

                if (wave) {
                    form.setDefaults();
                } else {
                    form.reset();
                }
            },
        };

        if (wave) {
            form.put(update(wave.id).url, options);
        } else {
            form.post(store().url, options);
        }
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    form.reset();
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>
                            {wave ? 'Ubah gelombang' : 'Gelombang baru'}
                        </DialogTitle>
                        <DialogDescription>
                            Pendaftaran dibuka sepanjang tahun. Gelombang hanya
                            mengelompokkan pendaftar dan menentukan aturannya.
                        </DialogDescription>
                    </DialogHeader>

                    <FormField
                        label="Nama gelombang"
                        required
                        error={form.errors.name}
                    >
                        {(control) => (
                            <Input
                                placeholder="Contoh: Gelombang 1"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                {...control}
                            />
                        )}
                    </FormField>

                    <div className="grid gap-5 sm:grid-cols-2">
                        <FormField
                            label="Mulai (boleh kosong)"
                            error={form.errors.starts_on}
                        >
                            {(control) => (
                                <Input
                                    type="date"
                                    value={form.data.starts_on}
                                    onChange={(e) => {
                                        form.setData(
                                            'starts_on',
                                            e.target.value,
                                        );
                                        form.clearErrors('ends_on');
                                    }}
                                    {...control}
                                />
                            )}
                        </FormField>
                        <FormField
                            label="Selesai (boleh kosong)"
                            error={form.errors.ends_on}
                        >
                            {(control) => (
                                <Input
                                    type="date"
                                    value={form.data.ends_on}
                                    min={form.data.starts_on || undefined}
                                    onChange={(e) => {
                                        form.setData('ends_on', e.target.value);
                                        form.clearErrors('ends_on');
                                    }}
                                    {...control}
                                />
                            )}
                        </FormField>
                    </div>

                    <label className="flex min-h-10 items-center gap-3 text-sm font-medium">
                        <Switch
                            checked={form.data.is_open}
                            onCheckedChange={(checked) =>
                                form.setData('is_open', checked)
                            }
                        />
                        Buka untuk pendaftar baru
                    </label>

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {wave ? 'Simpan perubahan' : 'Buat gelombang'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
