import { Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
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
import { Textarea } from '@/components/ui/textarea';
import { toastFirstError } from '@/hooks/use-flash-toast';
import { digitsOnly, NIK_LENGTH } from '@/lib/format';
import { index as yearsIndex } from '@/routes/admin/years';
import {
    bypass,
    place,
    repointIdentity,
    revise,
} from '@/routes/admin/registrants';
import type { Option } from '@/types/people';

function DialogShell({
    open,
    onOpenChange,
    title,
    description,
    submitLabel,
    processing,
    onSubmit,
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    submitLabel: string;
    processing: boolean;
    onSubmit: (event: FormEvent) => void;
    children: ReactNode;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={onSubmit} className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    {children}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

type DialogProps = {
    registrantId: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Sends the data back to the parent. The reason is what the parent reads on the portal.
 */
export function ReviseDialog({
    registrantId,
    open,
    onOpenChange,
}: DialogProps) {
    const form = useForm({ note: '' });

    return (
        <DialogShell
            open={open}
            onOpenChange={onOpenChange}
            title="Minta revisi"
            description="Orang tua akan membaca alasan ini di portal dan bisa memperbaiki datanya."
            submitLabel="Kirim ke orang tua"
            processing={form.processing}
            onSubmit={(event) => {
                event.preventDefault();
                form.post(revise(registrantId).url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        onOpenChange(false);
                    },
                    onError: (errors) =>
                        toastFirstError(errors, (key) => key === 'note'),
                });
            }}
        >
            <FormField
                label="Apa yang perlu diperbaiki?"
                required
                error={form.errors.note}
            >
                {(control) => (
                    <Textarea
                        className="min-h-28"
                        placeholder="Misalnya: foto KK kurang jelas, tolong unggah ulang."
                        value={form.data.note}
                        onChange={(event) =>
                            form.setData('note', event.target.value)
                        }
                        {...control}
                    />
                )}
            </FormField>
        </DialogShell>
    );
}

/**
 * Moves the registration to any status, skipping what the flow would ask. The note stays in the history.
 */
export function BypassDialog({
    registrantId,
    open,
    onOpenChange,
    statuses,
}: DialogProps & { statuses: Option[] }) {
    const form = useForm({ status: '', note: '' });
    const errors = form.errors as Record<string, string>;

    return (
        <DialogShell
            open={open}
            onOpenChange={onOpenChange}
            title="Lompati status"
            description="Pindahkan ke status mana pun tanpa menunggu syaratnya. Catatan wajib diisi dan tersimpan di riwayat, tapi tidak dilihat orang tua."
            submitLabel="Pindahkan status"
            processing={form.processing}
            onSubmit={(event) => {
                event.preventDefault();
                form.post(bypass(registrantId).url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        onOpenChange(false);
                    },
                    onError: (errors) =>
                        toastFirstError(
                            errors,
                            (key) => key === 'status' || key === 'note',
                        ),
                });
            }}
        >
            <FormField label="Pindah ke status" required error={errors.status}>
                {(control) => (
                    <OptionSelect
                        value={form.data.status}
                        onValueChange={(status) =>
                            form.setData('status', status)
                        }
                        options={statuses}
                        placeholder="Pilih status"
                        {...control}
                    />
                )}
            </FormField>
            <FormField label="Catatan" required error={errors.note}>
                {(control) => (
                    <Textarea
                        className="min-h-24"
                        placeholder="Kenapa status dilompati?"
                        value={form.data.note}
                        onChange={(event) =>
                            form.setData('note', event.target.value)
                        }
                        {...control}
                    />
                )}
            </FormField>
        </DialogShell>
    );
}

/**
 * Places the registration into an academic year as a student. The student number is typed by the committee.
 */
export function PlaceDialog({
    registrantId,
    open,
    onOpenChange,
    years,
}: DialogProps & {
    years: { value: number; label: string; is_active: boolean }[];
}) {
    const form = useForm({
        year_id: String(years.find((year) => year.is_active)?.value ?? ''),
        nis: '',
    });
    const errors = form.errors as Record<string, string>;
    const { auth } = usePage().props;
    const canManageYears = auth.abilities.includes('core::year.manage');

    return (
        <DialogShell
            open={open}
            onOpenChange={onOpenChange}
            title="Tempatkan siswa"
            description="Anak ini akan tercatat sebagai siswa di tahun ajaran yang dipilih, lengkap dengan foto profilnya."
            submitLabel="Tempatkan siswa"
            processing={form.processing}
            onSubmit={(event) => {
                event.preventDefault();
                form.post(place(registrantId).url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        onOpenChange(false);
                    },
                    onError: (errors) =>
                        toastFirstError(errors, (key) =>
                            ['year_id', 'nis', 'status'].includes(key),
                        ),
                });
            }}
        >
            <FormField label="Tahun ajaran" required error={errors.year_id}>
                {(control) => (
                    <OptionSelect
                        value={form.data.year_id}
                        onValueChange={(yearId) =>
                            form.setData('year_id', yearId)
                        }
                        options={years}
                        placeholder="Pilih tahun ajaran"
                        {...control}
                    />
                )}
            </FormField>
            {years.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    Belum ada tahun ajaran, jadi siswa belum bisa ditempatkan.{' '}
                    {canManageYears ? (
                        <Link
                            href={yearsIndex()}
                            className="font-semibold text-foreground underline"
                        >
                            Buat tahun ajaran dulu
                        </Link>
                    ) : (
                        'Minta admin membuat tahun ajaran dulu.'
                    )}
                </p>
            )}
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
            {errors.status && (
                <p className="text-sm font-medium text-destructive-foreground">
                    {errors.status}
                </p>
            )}
        </DialogShell>
    );
}

/**
 * The parent typed a NIK that belongs to someone else. The committee enters the right one and the registration
 * moves to the child who owns it (or to a new person with the name the parent typed).
 */
export function RepointIdentityDialog({
    registrantId,
    open,
    onOpenChange,
    typedName,
}: DialogProps & { typedName: string }) {
    const form = useForm({ nik: '', reason: '' });
    const errors = form.errors as Record<string, string>;

    return (
        <DialogShell
            open={open}
            onOpenChange={onOpenChange}
            title="Ganti NIK pendaftaran"
            description={`Pendaftaran ini akan dipindah ke anak yang punya NIK yang benar. Kalau belum ada orangnya, dibuat orang baru dengan nama "${typedName}".`}
            submitLabel="Ganti NIK"
            processing={form.processing}
            onSubmit={(event) => {
                event.preventDefault();
                form.post(repointIdentity(registrantId).url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        onOpenChange(false);
                    },
                    onError: (errors) =>
                        toastFirstError(
                            errors,
                            (key) => key === 'nik' || key === 'reason',
                        ),
                });
            }}
        >
            <FormField
                label="NIK yang benar"
                required
                error={errors.nik}
                hint={`${NIK_LENGTH} digit angka.`}
            >
                {(control) => (
                    <Input
                        inputMode="numeric"
                        maxLength={NIK_LENGTH}
                        value={form.data.nik}
                        onChange={(event) =>
                            form.setData('nik', digitsOnly(event.target.value))
                        }
                        {...control}
                    />
                )}
            </FormField>
            <FormField label="Alasan" required error={errors.reason}>
                {(control) => (
                    <Textarea
                        className="min-h-24"
                        placeholder="Misalnya: orang tua salah ketik NIK, sudah dikonfirmasi lewat telepon."
                        value={form.data.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                        {...control}
                    />
                )}
            </FormField>
        </DialogShell>
    );
}
