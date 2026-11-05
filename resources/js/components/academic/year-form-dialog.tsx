import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
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
import { store, update } from '@/routes/admin/years';
import type { YearRow, YearSuggestion } from '@/types/academic';

/**
 * Create or edit an academic year. A new year starts with the dates of the year after the latest one, and can
 * copy the period structure (Ganjil, Genap) of an earlier year. Mount it only while it is open, so the form
 * always starts from the current values.
 */
export function YearFormDialog({
    year,
    suggestion,
    hasActiveYear,
    onClose,
}: {
    year?: YearRow;
    suggestion: YearSuggestion;
    hasActiveYear: boolean;
    onClose: () => void;
}) {
    const form = useForm({
        name: year?.name ?? suggestion.name,
        starts_on: year?.starts_on ?? suggestion.starts_on,
        ends_on: year?.ends_on ?? suggestion.ends_on,
        is_active: year?.is_active ?? !hasActiveYear,
        copy_periods: year === undefined && suggestion.copy_from_id !== null,
    });
    const errors = form.errors as Record<string, string>;
    const keepsActive = year?.is_active === true;

    function submit(event: FormEvent) {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: (problems: Record<string, string>) => {
                if (problems.year) {
                    toast.error(problems.year);
                }
            },
        };

        if (year) {
            form.transform(({ name, starts_on, ends_on, is_active }) => ({
                name,
                starts_on,
                ends_on,
                is_active,
            }));
            form.put(update(year.id).url, options);
        } else {
            form.transform(({ copy_periods, ...data }) => ({
                ...data,
                copy_periods_from: copy_periods
                    ? suggestion.copy_from_id
                    : null,
            }));
            form.post(store().url, options);
        }
    }

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>
                            {year ? 'Ubah tahun ajaran' : 'Tahun ajaran baru'}
                        </DialogTitle>
                        <DialogDescription>
                            {year
                                ? 'Tanggalnya tidak boleh tumpang tindih dengan tahun ajaran lain.'
                                : 'Isiannya sudah disiapkan untuk tahun ajaran berikutnya. Ubah kalau perlu.'}
                        </DialogDescription>
                    </DialogHeader>

                    <FormField
                        label="Nama tahun ajaran"
                        required
                        error={errors.name}
                    >
                        {(control) => (
                            <Input
                                placeholder="Contoh: 2026/2027"
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
                            label="Mulai"
                            required
                            error={errors.starts_on}
                        >
                            {(control) => (
                                <Input
                                    type="date"
                                    value={form.data.starts_on}
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
                                    min={form.data.starts_on || undefined}
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
                                disabled={keepsActive}
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked)
                                }
                            />
                            Jadikan tahun ajaran aktif
                        </label>
                        {keepsActive ? (
                            <p className="text-sm text-muted-foreground">
                                Ini tahun ajaran aktif. Untuk menggantinya,
                                aktifkan tahun ajaran lain.
                            </p>
                        ) : (
                            form.data.is_active &&
                            hasActiveYear && (
                                <p className="text-sm text-muted-foreground">
                                    Tahun ajaran aktif yang sekarang akan
                                    nonaktif.
                                </p>
                            )
                        )}
                        {errors.is_active && (
                            <p className="text-sm font-medium text-destructive-foreground">
                                {errors.is_active}
                            </p>
                        )}
                    </div>

                    {!year && suggestion.copy_from_id !== null && (
                        <label className="flex min-h-10 items-start gap-3 text-sm font-medium">
                            <Switch
                                checked={form.data.copy_periods}
                                onCheckedChange={(checked) =>
                                    form.setData('copy_periods', checked)
                                }
                            />
                            <span className="grid gap-0.5">
                                Salin periode dari {suggestion.copy_from_name}
                                <span className="font-normal text-muted-foreground">
                                    Nama, urutan, dan tanggal periodenya ikut
                                    bergeser ke tahun ini.
                                </span>
                            </span>
                        </label>
                    )}

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
                            {year ? 'Simpan perubahan' : 'Buat tahun ajaran'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
