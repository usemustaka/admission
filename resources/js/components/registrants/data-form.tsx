import { Link, useForm } from '@inertiajs/react';
import { Camera, Plus, UserRound, Users } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { OptionSelect } from '@/components/option-select';
import { ParentFields } from '@/components/portal/parent-fields';
import { PhotoUpload } from '@/components/photo-upload';
import { SectionCard } from '@/components/section-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { DescriptionList } from '@/components/description-list';
import type { Option } from '@/types/people';
import type { ParentFieldsData, ParentRelationKey } from '@/types/portal';

export type ChildData = {
    name: string;
    nik: string | null;
    gender: string | null;
    birth_place: string | null;
    birth_date: string | null;
    nisn: string | null;
    family_card_number: string | null;
    religion: string | null;
    blood_type: string | null;
    child_order: number | null;
    siblings_count: number | null;
    address: string | null;
    photo_url: string | null;
};

export type ParentFromServer = Partial<
    Omit<ParentFieldsData, 'is_alive'> & { is_alive: boolean }
> | null;

export type DataFormOptions = {
    genders: Option[];
    religions: Option[];
    bloodTypes: Option[];
    educations: Option[];
    incomes: Option[];
    kinds: Option[];
};

const relations: { key: ParentRelationKey; title: string }[] = [
    { key: 'father', title: 'Data ayah' },
    { key: 'mother', title: 'Data ibu' },
    { key: 'guardian', title: 'Data wali' },
];

function parentData(server: ParentFromServer): ParentFieldsData {
    return {
        name: server?.name ?? '',
        nik: server?.nik ?? '',
        phone: server?.phone ?? '',
        job: server?.job ?? '',
        last_education: server?.last_education ?? '',
        monthly_income: server?.monthly_income ?? '',
        address: server?.address ?? '',
        is_alive: server?.is_alive ?? true,
        kind: server?.kind ?? 'biological',
    };
}

export function RegistrantDataForm({
    action,
    backHref,
    identityNote,
    identityHref,
    child,
    formalPhotoUrl,
    parents,
    options,
    canEdit,
}: {
    /** Where the form is posted. */
    action: string;
    backHref: string;
    /** What to tell about name and NIK, which are not changed here. */
    identityNote: string;
    /** Where name and NIK can be changed, for the committee. */
    identityHref?: string;
    child: ChildData;
    formalPhotoUrl: string | null;
    parents: Record<ParentRelationKey, ParentFromServer>;
    options: DataFormOptions;
    canEdit: boolean;
}) {
    const form = useForm({
        child: {
            gender: child.gender ?? '',
            birth_place: child.birth_place ?? '',
            birth_date: child.birth_date ?? '',
            nisn: child.nisn ?? '',
            family_card_number: child.family_card_number ?? '',
            religion: child.religion ?? '',
            blood_type: child.blood_type ?? '',
            child_order: child.child_order?.toString() ?? '',
            siblings_count: child.siblings_count?.toString() ?? '',
            address: child.address ?? '',
        },
        parents: {
            father: parentData(parents.father),
            mother: parentData(parents.mother),
            guardian: parentData(parents.guardian),
        },
        photo: null as File | null,
        remove_photo: false,
        formal_photo: null as File | null,
        remove_formal_photo: false,
    });
    const [showGuardian, setShowGuardian] = useState(parents.guardian !== null);
    const errors = form.errors as Record<string, string>;

    function setChild(field: keyof typeof form.data.child, value: string) {
        form.setData('child', { ...form.data.child, [field]: value });
    }

    function setParent(
        key: ParentRelationKey,
        changes: Partial<ParentFieldsData>,
    ) {
        form.setData('parents', {
            ...form.data.parents,
            [key]: { ...form.data.parents[key], ...changes },
        });
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(action, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () =>
                form.setData((data) => ({
                    ...data,
                    photo: null,
                    remove_photo: false,
                    formal_photo: null,
                    remove_formal_photo: false,
                })),
        });
    }

    return (
        <form onSubmit={submit} className="grid gap-6">
            {!canEdit && (
                <Alert variant="info">
                    <UserRound />
                    <AlertTitle>Data sedang diperiksa</AlertTitle>
                    <AlertDescription>
                        Data tidak bisa diubah dulu. Kalau ada yang perlu
                        diperbaiki, panitia akan mengabari kamu.
                    </AlertDescription>
                </Alert>
            )}

            <fieldset disabled={!canEdit} className="grid gap-6">
                <SectionCard title="Foto" icon={Camera}>
                    <div className="grid gap-8">
                        <PhotoUpload
                            label="Foto profil"
                            hint="Foto biasa anak. Format JPG atau PNG, maksimal 2 MB."
                            value={
                                form.data.remove_photo ? null : child.photo_url
                            }
                            onChange={(file) =>
                                form.setData((data) => ({
                                    ...data,
                                    photo: file,
                                    remove_photo: file === null,
                                }))
                            }
                        />
                        <PhotoUpload
                            label="Pas foto"
                            hint="Foto formal berlatar polos untuk kartu siswa. Format JPG atau PNG, maksimal 2 MB."
                            value={
                                form.data.remove_formal_photo
                                    ? null
                                    : formalPhotoUrl
                            }
                            onChange={(file) =>
                                form.setData((data) => ({
                                    ...data,
                                    formal_photo: file,
                                    remove_formal_photo: file === null,
                                }))
                            }
                        />
                        {(errors.photo || errors.formal_photo) && (
                            <p className="text-sm font-medium text-destructive-foreground">
                                {errors.photo ?? errors.formal_photo}
                            </p>
                        )}
                    </div>
                </SectionCard>

                <SectionCard title="Data anak" icon={UserRound}>
                    <div className="grid gap-6">
                        <DescriptionList
                            items={[
                                { label: 'Nama', value: child.name },
                                { label: 'NIK', value: child.nik },
                            ]}
                        />
                        <p className="text-sm text-muted-foreground">
                            {identityNote}{' '}
                            {identityHref && (
                                <Link
                                    href={identityHref}
                                    className="font-semibold text-primary hover:underline"
                                >
                                    Buka data orang
                                </Link>
                            )}
                        </p>
                        <div className="grid gap-6 sm:grid-cols-2">
                            <FormField
                                label="Jenis kelamin"
                                error={errors['child.gender']}
                            >
                                {(control) => (
                                    <OptionSelect
                                        value={form.data.child.gender}
                                        onValueChange={(v) =>
                                            setChild('gender', v)
                                        }
                                        options={options.genders}
                                        anyLabel="Belum dipilih"
                                        {...control}
                                    />
                                )}
                            </FormField>
                            <FormField
                                label="Agama"
                                error={errors['child.religion']}
                            >
                                {(control) => (
                                    <OptionSelect
                                        value={form.data.child.religion}
                                        onValueChange={(v) =>
                                            setChild('religion', v)
                                        }
                                        options={options.religions}
                                        anyLabel="Belum dipilih"
                                        {...control}
                                    />
                                )}
                            </FormField>
                            <FormField
                                label="Tempat lahir"
                                error={errors['child.birth_place']}
                            >
                                {(control) => (
                                    <Input
                                        value={form.data.child.birth_place}
                                        onChange={(e) =>
                                            setChild(
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
                                error={errors['child.birth_date']}
                            >
                                {(control) => (
                                    <Input
                                        type="date"
                                        value={form.data.child.birth_date}
                                        onChange={(e) =>
                                            setChild(
                                                'birth_date',
                                                e.target.value,
                                            )
                                        }
                                        {...control}
                                    />
                                )}
                            </FormField>
                            <FormField
                                label="Nomor KK"
                                hint="16 digit angka"
                                error={errors['child.family_card_number']}
                            >
                                {(control) => (
                                    <Input
                                        inputMode="numeric"
                                        maxLength={16}
                                        value={
                                            form.data.child.family_card_number
                                        }
                                        onChange={(e) =>
                                            setChild(
                                                'family_card_number',
                                                e.target.value.replace(
                                                    /\D/g,
                                                    '',
                                                ),
                                            )
                                        }
                                        {...control}
                                    />
                                )}
                            </FormField>
                            <FormField
                                label="NISN"
                                hint="10 digit angka, kalau ada"
                                error={errors['child.nisn']}
                            >
                                {(control) => (
                                    <Input
                                        inputMode="numeric"
                                        maxLength={10}
                                        value={form.data.child.nisn}
                                        onChange={(e) =>
                                            setChild(
                                                'nisn',
                                                e.target.value.replace(
                                                    /\D/g,
                                                    '',
                                                ),
                                            )
                                        }
                                        {...control}
                                    />
                                )}
                            </FormField>
                            <FormField
                                label="Anak ke-"
                                error={errors['child.child_order']}
                            >
                                {(control) => (
                                    <Input
                                        type="number"
                                        min={1}
                                        value={form.data.child.child_order}
                                        onChange={(e) =>
                                            setChild(
                                                'child_order',
                                                e.target.value,
                                            )
                                        }
                                        {...control}
                                    />
                                )}
                            </FormField>
                            <FormField
                                label="Jumlah saudara"
                                error={errors['child.siblings_count']}
                            >
                                {(control) => (
                                    <Input
                                        type="number"
                                        min={0}
                                        value={form.data.child.siblings_count}
                                        onChange={(e) =>
                                            setChild(
                                                'siblings_count',
                                                e.target.value,
                                            )
                                        }
                                        {...control}
                                    />
                                )}
                            </FormField>
                            <FormField
                                label="Golongan darah"
                                error={errors['child.blood_type']}
                            >
                                {(control) => (
                                    <OptionSelect
                                        value={form.data.child.blood_type}
                                        onValueChange={(v) =>
                                            setChild('blood_type', v)
                                        }
                                        options={options.bloodTypes}
                                        anyLabel="Belum dipilih"
                                        {...control}
                                    />
                                )}
                            </FormField>
                            <FormField
                                label="Alamat"
                                error={errors['child.address']}
                                className="sm:col-span-2"
                            >
                                {(control) => (
                                    <Textarea
                                        className="min-h-20"
                                        value={form.data.child.address}
                                        onChange={(e) =>
                                            setChild('address', e.target.value)
                                        }
                                        {...control}
                                    />
                                )}
                            </FormField>
                        </div>
                    </div>
                </SectionCard>

                {relations.map(({ key, title }) =>
                    key === 'guardian' && !showGuardian ? (
                        <div key={key}>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setShowGuardian(true)}
                            >
                                <Plus />
                                Tambah data wali
                            </Button>
                        </div>
                    ) : (
                        <SectionCard key={key} title={title} icon={Users}>
                            <ParentFields
                                relationKey={key}
                                value={form.data.parents[key]}
                                onChange={(changes) => setParent(key, changes)}
                                errors={errors}
                                options={options}
                                lockedNik={Boolean(parents[key]?.nik)}
                            />
                        </SectionCard>
                    ),
                )}
            </fieldset>

            <div className="flex justify-end gap-3">
                <Button variant="outline" asChild>
                    <Link href={backHref}>Kembali</Link>
                </Button>
                {canEdit && (
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        Simpan draf
                    </Button>
                )}
            </div>
        </form>
    );
}
