import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { OptionSelect } from '@/components/option-select';
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
import { update } from '@/routes/admin/students';
import type { StudentRow } from '@/types/academic';

/**
 * Corrects the student number and the academic year of a student. The change is written as a private note on the
 * child. Mount it only while it is open.
 */
export function StudentCorrectionDialog({
    student,
    years,
    onClose,
}: {
    student: StudentRow;
    years: { value: number; label: string }[];
    onClose: () => void;
}) {
    const form = useForm({
        nis: student.nis ?? '',
        year_id: student.year_id === null ? '' : String(student.year_id),
    });
    const errors = form.errors as Record<string, string>;

    function submit(event: FormEvent) {
        event.preventDefault();

        form.put(update(student.id).url, {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    }

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>Koreksi data siswa</DialogTitle>
                        <DialogDescription>
                            Ubah NIS atau tahun ajaran {student.name}. Apa yang
                            berubah tercatat di catatan anak ini.
                        </DialogDescription>
                    </DialogHeader>

                    <FormField
                        label="Nomor induk siswa (NIS)"
                        required
                        error={errors.nis}
                    >
                        {(control) => (
                            <Input
                                value={form.data.nis}
                                onChange={(event) =>
                                    form.setData('nis', event.target.value)
                                }
                                {...control}
                            />
                        )}
                    </FormField>
                    <FormField
                        label="Tahun ajaran"
                        required
                        error={errors.year_id}
                    >
                        {(control) => (
                            <OptionSelect
                                value={form.data.year_id}
                                onValueChange={(value) =>
                                    form.setData('year_id', value)
                                }
                                options={years}
                                placeholder="Pilih tahun ajaran"
                                {...control}
                            />
                        )}
                    </FormField>

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
                            Simpan koreksi
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
