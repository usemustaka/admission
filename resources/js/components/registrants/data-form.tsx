import { Link, useForm } from '@inertiajs/react';
import {
    Camera,
    GraduationCap,
    Plus,
    Trash2,
    UserRound,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ChoiceCardGroup } from '@/components/choice-card-group';
import { FormField } from '@/components/form-field';
import { OptionSelect } from '@/components/option-select';
import { ParentFields } from '@/components/portal/parent-fields';
import { PhotoUpload } from '@/components/photo-upload';
import { StickyActions } from '@/components/portal/sticky-actions';
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

export type EducationRow = {
    level: string;
    school_name: string;
    npsn: string | null;
    school_address: string | null;
};

export type DataFormOptions = {
    genders: Option[];
    admissionTypes: Option[];
    educationLevels: Option[];
    /** Child fields the parent has to fill in before sending. The rest can be skipped. */
    requiredChildFields: string[];
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
    parents,
    admissionType,
    education,
    options,
    canEdit,
    stickyActions = false,
}: {
    /** Where the form is posted. */
    action: string;
    backHref: string;
    /** What to tell about name and NIK, which are not changed here. */
    identityNote: string;
    /** Where name and NIK can be changed, for the committee. */
    identityHref?: string;
    child: ChildData;
    parents: Record<ParentRelationKey, ParentFromServer>;
    admissionType: string;
    education: EducationRow[];
    options: DataFormOptions;
    canEdit: boolean;
    /** Pin the buttons above the portal's bottom tab bar (parents on a phone). */
    stickyActions?: boolean;
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
        admission_type: admissionType,
        education: education.map((row) => ({
            level: row.level,
            school_name: row.school_name,
            npsn: row.npsn ?? '',
            school_address: row.school_address ?? '',
        })),
        photo: null as File | null,
        remove_photo: false,
    });
    const [showGuardian, setShowGuardian] = useState(parents.guardian !== null);
    const errors = form.errors as Record<string, string>;

    function setChild(field: keyof typeof form.data.child, value: string) {
        form.setData('child', { ...form.data.child, [field]: value });
    }

    function setEducation(
        index: number,
        changes: Partial<(typeof form.data.education)[number]>,
    ) {
        form.setData(
            'education',
            form.data.education.map((row, i) =>
                i === index ? { ...row, ...changes } : row,
            ),
        );
    }

    const isRequired = (field: string) =>
        options.requiredChildFields.includes(field);

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
                })),
        });
    }

    const buttonSize = stickyActions ? 'lg' : 'default';
    const actions = (
        <>
            <Button variant="outline" size={buttonSize} asChild>
                <Link href={backHref}>Kembali</Link>
            </Button>
            {canEdit && (
                <Button
                    type="submit"
                    size={buttonSize}
                    className={stickyActions ? 'flex-1' : undefined}
                    disabled={form.processing}
                >
                    {form.processing && <Spinner />}
                    Simpan draf
                </Button>
            )}
        </>
    );

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

            <p className="text-[15px] text-muted-foreground">
                Isian bertanda <span className="text-destructive">*</span> wajib
                diisi sebelum data dikirim ke panitia. Yang lain boleh dilewati.
            </p>

            <fieldset disabled={!canEdit} className="grid gap-6">
                <SectionCard title="Foto" icon={Camera}>
                    <div className="grid gap-4">
                        <PhotoUpload
                            label="Foto profil"
                            hint="Foto biasa anak. Format JPG atau PNG, maksimal 1 MB."
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
                        {errors.photo && (
                            <p className="text-sm font-medium text-destructive-foreground">
                                {errors.photo}
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
                        <p className="text-[15px] text-muted-foreground">
                            {identityNote}{' '}
                            {identityHref && (
                                <Link
                                    href={identityHref}
                                    className="font-semibold text-foreground underline underline-offset-4"
                                >
                                    Buka data orang
                                </Link>
                            )}
                        </p>
                        <FormField label="Status pendaftar" required>
                            {() => (
                                <ChoiceCardGroup
                                    columns={2}
                                    value={form.data.admission_type}
                                    onValueChange={(value) =>
                                        form.setData('admission_type', value)
                                    }
                                    options={options.admissionTypes}
                                />
                            )}
                        </FormField>
                        <div className="grid gap-6 @2xl:grid-cols-2">
                            <FormField
                                label="Jenis kelamin"
                                required={isRequired('gender')}
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
                                required={isRequired('religion')}
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
                                required={isRequired('birth_place')}
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
                                required={isRequired('birth_date')}
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
                                required={isRequired('family_card_number')}
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
                                required={isRequired('address')}
                                error={errors['child.address']}
                                className="@2xl:col-span-2"
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

                <SectionCard title="Riwayat pendidikan" icon={GraduationCap}>
                    <div className="grid gap-6">
                        {form.data.education.length === 0 && (
                            <p className="text-[15px] text-muted-foreground">
                                Belum ada sekolah sebelumnya. Boleh dilewati
                                kalau anak belum pernah bersekolah.
                            </p>
                        )}
                        {form.data.education.map((row, index) => (
                            <div
                                key={index}
                                className="grid gap-4 rounded-xl border border-line p-4 @2xl:grid-cols-2"
                            >
                                <FormField
                                    label="Jenjang"
                                    required
                                    error={errors[`education.${index}.level`]}
                                >
                                    {(control) => (
                                        <OptionSelect
                                            value={row.level}
                                            onValueChange={(value) =>
                                                setEducation(index, {
                                                    level: value,
                                                })
                                            }
                                            options={options.educationLevels}
                                            {...control}
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    label="Nama sekolah"
                                    required
                                    error={
                                        errors[`education.${index}.school_name`]
                                    }
                                >
                                    {(control) => (
                                        <Input
                                            value={row.school_name}
                                            onChange={(e) =>
                                                setEducation(index, {
                                                    school_name: e.target.value,
                                                })
                                            }
                                            {...control}
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    label="NPSN"
                                    hint="8 digit angka, kalau tahu"
                                    error={errors[`education.${index}.npsn`]}
                                >
                                    {(control) => (
                                        <Input
                                            inputMode="numeric"
                                            maxLength={8}
                                            value={row.npsn}
                                            onChange={(e) =>
                                                setEducation(index, {
                                                    npsn: e.target.value.replace(
                                                        /\D/g,
                                                        '',
                                                    ),
                                                })
                                            }
                                            {...control}
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    label="Alamat sekolah"
                                    error={
                                        errors[
                                            `education.${index}.school_address`
                                        ]
                                    }
                                >
                                    {(control) => (
                                        <Input
                                            value={row.school_address}
                                            onChange={(e) =>
                                                setEducation(index, {
                                                    school_address:
                                                        e.target.value,
                                                })
                                            }
                                            {...control}
                                        />
                                    )}
                                </FormField>
                                <div className="@2xl:col-span-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            form.setData(
                                                'education',
                                                form.data.education.filter(
                                                    (_, i) => i !== index,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 />
                                        Hapus sekolah ini
                                    </Button>
                                </div>
                            </div>
                        ))}
                        {form.data.education.length < 6 && (
                            <div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size={buttonSize}
                                    className={
                                        stickyActions ? 'w-full' : undefined
                                    }
                                    onClick={() =>
                                        form.setData('education', [
                                            ...form.data.education,
                                            {
                                                level: 'elementary',
                                                school_name: '',
                                                npsn: '',
                                                school_address: '',
                                            },
                                        ])
                                    }
                                >
                                    <Plus />
                                    Tambah sekolah
                                </Button>
                            </div>
                        )}
                    </div>
                </SectionCard>

                {relations.map(({ key, title }) =>
                    key === 'guardian' && !showGuardian ? (
                        <div key={key}>
                            <Button
                                type="button"
                                variant="outline"
                                size={buttonSize}
                                className={stickyActions ? 'w-full' : undefined}
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

            {stickyActions ? (
                <StickyActions className="flex gap-3">{actions}</StickyActions>
            ) : (
                <div className="flex justify-end gap-3">{actions}</div>
            )}
        </form>
    );
}
