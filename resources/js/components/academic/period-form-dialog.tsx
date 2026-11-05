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
import { formatDate } from '@/lib/format';
import { store, update } from '@/routes/admin/years/periods';
import type { PeriodRow, YearRow } from '@/types/academic';

/**
 * Create or edit a period (semester) of a year. Mount it only while it is open.
 */
export function PeriodFormDialog({
    year,
    period,
    onClose,
}: {
    year: YearRow;
    period?: PeriodRow;
    onClose: () => void;
}) {
    const nextSequence =
        year.periods.reduce((max, item) => Math.max(max, item.sequence), 0) + 1;
    const form = useForm({
        name: period?.name ?? '',
        sequence: String(period?.sequence ?? nextSequence),
        starts_on: period?.starts_on ?? year.starts_on,
        ends_on: period?.ends_on ?? year.ends_on,
        is_active: period?.is_active ?? false,
    });
    const errors = form.errors as Record<string, string>;

    function submit(event: FormEvent) {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => onClose(),
        };

        if (period) {
            form.put(update({ year: year.id, period: period.id }).url, options);
        } else {
            form.post(store(year.id).url, options);
        }
    }

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>
                            {period ? 'Ubah periode' : 'Periode baru'}
                        </DialogTitle>
                        <DialogDescription>
                            Periode di tahun ajaran {year.name}, antara{' '}
                            {formatDate(year.starts_on)} dan{' '}
                            {formatDate(year.ends_on)}. Periode tidak boleh
                            tumpang tindih.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-5 sm:grid-cols-[minmax(0,1fr)_7rem]">
                        <FormField
                            label="Nama periode"
                            required
                            error={errors.name}
                        >
                            {(control) => (
                                <Input
                                    placeholder="Contoh: Ganjil"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    {...control}
                                />
                            )}
                        </FormField>
                        <FormField
                            label="Urutan"
                            required
                            error={errors.sequence}
                        >
                            {(control) => (
                                <Input
                                    type="number"
                                    min={1}
                                    max={20}
                                    inputMode="numeric"
                                    value={form.data.sequence}
                                    onChange={(e) =>
                                        form.setData('sequence', e.target.value)
                                    }
                                    {...control}
                                />
                            )}
                        </FormField>
                    </div>

                    <div className="grid gap-5 sm:grid-cols-2">
                        <FormField
                            label="Mulai"
                            required
                            error={errors.starts_on}
                        >
                            {(control) => (
                                <Input
                                    type="date"
                                    value={form.data.starts_on}
                                    min={year.starts_on}
                                    max={year.ends_on}
                                    onChange={(e) =>
                                        form.setData(
                                            'starts_on',
                                            e.target.value,
                                        )
                                    }
                                    {...control}
                                />
                            )}
                        </FormField>
                        <FormField
                            label="Selesai"
                            required
                            error={errors.ends_on}
                        >
                            {(control) => (
                                <Input
                                    type="date"
                                    value={form.data.ends_on}
                                    min={form.data.starts_on || year.starts_on}
                                    max={year.ends_on}
                                    onChange={(e) =>
                                        form.setData('ends_on', e.target.value)
                                    }
                                    {...control}
                                />
                            )}
                        </FormField>
                    </div>

                    <div className="grid gap-1">
                        <label className="flex min-h-10 items-center gap-3 text-sm font-medium">
                            <Switch
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked)
                                }
                            />
                            Periode yang sedang berjalan
                        </label>
                        <p className="text-sm text-muted-foreground">
                            Hanya satu periode yang bisa berjalan. Yang lain
                            otomatis nonaktif.
                        </p>
                    </div>

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
                            {period ? 'Simpan perubahan' : 'Tambah periode'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
