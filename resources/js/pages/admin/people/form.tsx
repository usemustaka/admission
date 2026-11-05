import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { NikInput } from '@/components/nik-input';
import { PageHeader } from '@/components/page-header';
import { PhotoUpload } from '@/components/photo-upload';
import { SectionCard } from '@/components/section-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { dashboard } from '@/routes';
import { index, show, store, update } from '@/routes/admin/people';
import type { Option, PersonDetail } from '@/types/people';

export default function PersonForm({
    person,
    genders,
}: {
    person: PersonDetail | null;
    genders: Option[];
}) {
    const isEditing = person !== null;

    const form = useForm<{
        nik: string;
        name: string;
        gender: string;
        birth_place: string;
        birth_date: string;
        phone: string;
        email: string;
        is_alive: boolean;
        photo: File | null;
        remove_photo: boolean;
        _method?: string;
    }>({
        nik: person?.nik ?? '',
        name: person?.name ?? '',
        gender: person?.gender_value ?? '',
        birth_place: person?.birth_place ?? '',
        birth_date: person?.birth_date ?? '',
        phone: person?.phone ?? '',
        email: person?.email ?? '',
        is_alive: person?.is_alive ?? true,
        photo: null,
        remove_photo: false,
        // PHP cannot read file uploads sent with PUT, so edits are posted with a spoofed method.
        ...(person ? { _method: 'put' } : {}),
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        if (isEditing) {
            form.post(update(person.id).url, { forceFormData: true });

            return;
        }

        form.post(store().url, { forceFormData: true });
    }

    const title = isEditing ? `Ubah ${person.name}` : 'Tambah orang';

    return (
        <>
            <Head title={title} />
            <form
                onSubmit={submit}
                className="mx-auto grid w-full max-w-3xl gap-6"
            >
                <PageHeader
                    title={title}
                    description={
                        isEditing
                            ? 'Perbarui data diri, lalu simpan.'
                            : 'Kalau NIK sudah pernah tercatat, kamu akan diarahkan ke datanya.'
                    }
                />

                <SectionCard title="Data diri">
                    <div className="grid gap-6 sm:grid-cols-2">
                        <FormField
                            label="Nama lengkap"
                            required
                            error={form.errors.name}
                            className="sm:col-span-2"
                        >
                            {(control) => (
                                <Input
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    {...control}
                                />
                            )}
                        </FormField>

                        <FormField
                            label="NIK"
                            hint="16 digit angka, sesuai KTP atau KK"
                            error={form.errors.nik}
                        >
                            {(control) => (
                                <NikInput
                                    value={form.data.nik}
                                    onValueChange={(value) =>
                                        form.setData('nik', value)
                                    }
                                    {...control}
                                />
                            )}
                        </FormField>

                        <FormField
                            label="Jenis kelamin"
                            error={form.errors.gender}
                        >
                            {(control) => (
                                <Select
                                    value={form.data.gender || undefined}
                                    onValueChange={(value) =>
                                        form.setData('gender', value)
                                    }
                                >
                                    <SelectTrigger
                                        className="w-full"
                                        {...control}
                                    >
                                        <SelectValue placeholder="Pilih" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {genders.map((gender) => (
                                            <SelectItem
                                                key={gender.value}
                                                value={gender.value}
                                            >
                                                {gender.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </FormField>

                        <FormField
                            label="Tempat lahir"
                            error={form.errors.birth_place}
                        >
                            {(control) => (
                                <Input
                                    value={form.data.birth_place}
                                    onChange={(e) =>
                                        form.setData(
                                            'birth_place',
                                            e.target.value,
                                        )
                                    }
                                    {...control}
                                />
                            )}
                        </FormField>

                        <FormField
                            label="Tanggal lahir"
                            error={form.errors.birth_date}
                        >
                            {(control) => (
                                <Input
                                    type="date"
                                    value={form.data.birth_date}
                                    onChange={(e) =>
                                        form.setData(
                                            'birth_date',
                                            e.target.value,
                                        )
                                    }
                                    {...control}
                                />
                            )}
                        </FormField>

                        <FormField
                            label="Nomor HP"
                            hint="Contoh: 081234567890"
                            error={form.errors.phone}
                        >
                            {(control) => (
                                <Input
                                    type="tel"
                                    inputMode="tel"
                                    value={form.data.phone}
                                    onChange={(e) =>
                                        form.setData('phone', e.target.value)
                                    }
                                    {...control}
                                />
                            )}
                        </FormField>

                        <FormField label="Email" error={form.errors.email}>
                            {(control) => (
                                <Input
                                    type="email"
                                    value={form.data.email}
                                    onChange={(e) =>
                                        form.setData('email', e.target.value)
                                    }
                                    {...control}
                                />
                            )}
                        </FormField>

                        <label className="flex min-h-10 items-center gap-3 text-sm font-medium sm:col-span-2">
                            <Switch
                                checked={form.data.is_alive}
                                onCheckedChange={(checked) =>
                                    form.setData('is_alive', checked)
                                }
                            />
                            Masih hidup
                        </label>
                    </div>
                </SectionCard>

                <SectionCard
                    title="Foto profil"
                    description="Opsional. Dipakai sebagai avatar di daftar dan halaman detail."
                >
                    <PhotoUpload
                        value={
                            form.data.remove_photo
                                ? null
                                : (person?.photo_url ?? null)
                        }
                        onChange={(file) => {
                            form.setData((data) => ({
                                ...data,
                                photo: file,
                                remove_photo: file === null,
                            }));
                        }}
                        label="Foto profil"
                    />
                    {form.errors.photo && (
                        <p className="mt-2 text-sm font-medium text-destructive-foreground">
                            {form.errors.photo}
                        </p>
                    )}
                </SectionCard>

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button variant="outline" asChild>
                        <Link href={isEditing ? show(person.id) : index()}>
                            Batal
                        </Link>
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        {isEditing ? 'Simpan perubahan' : 'Tambah orang'}
                    </Button>
                </div>
            </form>
        </>
    );
}

PersonForm.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Orang', href: index() },
    ],
};
