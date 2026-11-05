import { Plus, Trash2 } from 'lucide-react';
import { FormField } from '@/components/form-field';
import InputError from '@/components/input-error';
import type {
    SetSection,
    SetupErrors,
    SetupExisting,
    SetupFormData,
} from '@/components/setup/setup-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { OptionSelect } from '@/components/option-select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CategoryOption } from '@/types/academic';

function ExistingChips({ label, items }: { label: string; items: string[] }) {
    return (
        <div className="flex flex-wrap items-center gap-2 text-[15px]">
            <span className="text-muted-foreground">{label}</span>
            {items.map((item) => (
                <Badge key={item} variant="muted" className="text-[13px]">
                    {item}
                </Badge>
            ))}
        </div>
    );
}

export function StepOrganizations({
    data,
    errors,
    existing,
    categories,
    setSection,
    onOrganizationsChange,
    yearOpen,
    onYearOpenChange,
    waveOpen,
    onWaveOpenChange,
}: {
    data: SetupFormData;
    errors: SetupErrors;
    existing: SetupExisting;
    categories: CategoryOption[];
    setSection: SetSection;
    onOrganizationsChange: (
        rows: SetupFormData['organizations'],
        changedIndex?: number,
    ) => void;
    yearOpen: boolean;
    onYearOpenChange: (open: boolean) => void;
    waveOpen: boolean;
    onWaveOpenChange: (open: boolean) => void;
}) {
    const rows = data.organizations;
    const hasExistingOrganizations = existing.organizations.length > 0;
    const hasExistingYears = existing.years.length > 0;
    const canRemoveRows = hasExistingOrganizations || rows.length > 1;

    function updateRow(
        index: number,
        values: Partial<SetupFormData['organizations'][number]>,
    ) {
        onOrganizationsChange(
            rows.map((row, position) =>
                position === index ? { ...row, ...values } : row,
            ),
            index,
        );
    }

    function addRow() {
        onOrganizationsChange([...rows, { name: '', category: '' }]);
        requestAnimationFrame(() =>
            document
                .getElementById(`organization-name-${rows.length}`)
                ?.focus(),
        );
    }

    return (
        <div className="grid gap-8">
            <fieldset className="grid gap-4">
                <legend className="mb-1 text-base font-semibold">
                    Lembaga
                </legend>
                <p className="text-[15px] text-muted-foreground">
                    Sekolah atau unit di bawah yayasan, misalnya SD dan SMP.
                    Kategorinya dipakai untuk menyaring daftar.
                </p>

                {hasExistingOrganizations && (
                    <ExistingChips
                        label="Sudah ada:"
                        items={existing.organizations}
                    />
                )}

                <InputError message={errors.organizations} role="alert" />

                {rows.length === 0 && (
                    <p className="text-[15px] text-muted-foreground">
                        Belum ada lembaga baru. Tambahkan kalau perlu.
                    </p>
                )}

                {rows.map((row, index) => (
                    <div
                        key={index}
                        className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_10rem_auto] sm:items-start"
                    >
                        <FormField
                            htmlFor={`organization-name-${index}`}
                            label={
                                rows.length > 1
                                    ? `Nama lembaga ${index + 1}`
                                    : 'Nama lembaga'
                            }
                            required={!hasExistingOrganizations}
                            error={errors[`organizations.${index}.name`]}
                        >
                            {(control) => (
                                <Input
                                    {...control}
                                    name={`organizations[${index}][name]`}
                                    autoComplete="off"
                                    placeholder="SMP Harapan Bangsa"
                                    value={row.name}
                                    onChange={(event) =>
                                        updateRow(index, {
                                            name: event.target.value,
                                        })
                                    }
                                />
                            )}
                        </FormField>
                        <FormField
                            htmlFor={`organization-category-${index}`}
                            label="Kategori"
                            error={errors[`organizations.${index}.category`]}
                        >
                            {(control) => (
                                <OptionSelect
                                    {...control}
                                    placeholder="Pilih kategori"
                                    anyLabel="Belum dipilih"
                                    options={categories}
                                    value={row.category}
                                    onValueChange={(category) =>
                                        updateRow(index, { category })
                                    }
                                />
                            )}
                        </FormField>
                        {canRemoveRows && (
                            <Button
                                type="button"
                                variant="ghost"
                                className="h-11 justify-self-start sm:mt-[26px] sm:size-11 sm:px-0"
                                aria-label={`Hapus lembaga ${index + 1}`}
                                onClick={() =>
                                    onOrganizationsChange(
                                        rows.filter(
                                            (_, position) => position !== index,
                                        ),
                                    )
                                }
                            >
                                <Trash2 />
                                <span className="sm:sr-only">Hapus</span>
                            </Button>
                        )}
                    </div>
                ))}

                <Button
                    type="button"
                    variant="outline"
                    className="h-11 w-fit"
                    onClick={addRow}
                >
                    <Plus />
                    Tambah lembaga
                </Button>
            </fieldset>

            <fieldset className="grid gap-4">
                <legend className="mb-1 text-base font-semibold">
                    Tahun ajaran
                </legend>

                {hasExistingYears && (
                    <ExistingChips label="Sudah ada:" items={existing.years} />
                )}

                {hasExistingYears && !yearOpen && (
                    <Button
                        type="button"
                        variant="outline"
                        className="h-11 w-fit"
                        aria-expanded={false}
                        onClick={() => onYearOpenChange(true)}
                    >
                        <Plus />
                        Tambah tahun ajaran baru
                    </Button>
                )}

                {yearOpen && (
                    <div className="grid gap-5">
                        <FormField
                            label="Nama tahun ajaran"
                            required
                            error={errors['year.name'] ?? errors.year}
                            hint="Contoh: 2026/2027."
                        >
                            {(control) => (
                                <Input
                                    {...control}
                                    name="year[name]"
                                    autoComplete="off"
                                    value={data.year.name}
                                    onChange={(event) =>
                                        setSection('year', {
                                            name: event.target.value,
                                        })
                                    }
                                />
                            )}
                        </FormField>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <FormField
                                label="Mulai"
                                required
                                error={errors['year.starts_on']}
                            >
                                {(control) => (
                                    <Input
                                        {...control}
                                        type="date"
                                        name="year[starts_on]"
                                        value={data.year.starts_on}
                                        onChange={(event) =>
                                            setSection('year', {
                                                starts_on: event.target.value,
                                            })
                                        }
                                    />
                                )}
                            </FormField>
                            <FormField
                                label="Selesai"
                                required
                                error={errors['year.ends_on']}
                            >
                                {(control) => (
                                    <Input
                                        {...control}
                                        type="date"
                                        name="year[ends_on]"
                                        value={data.year.ends_on}
                                        onChange={(event) =>
                                            setSection('year', {
                                                ends_on: event.target.value,
                                            })
                                        }
                                    />
                                )}
                            </FormField>
                        </div>
                        <div className="flex min-h-11 items-start gap-3">
                            <Checkbox
                                id="year-with-periods"
                                className="mt-0.5"
                                checked={data.year.with_periods}
                                onCheckedChange={(checked) =>
                                    setSection('year', {
                                        with_periods: checked === true,
                                    })
                                }
                            />
                            <Label
                                htmlFor="year-with-periods"
                                className="text-[15px] leading-snug font-medium"
                            >
                                Buat semester Ganjil dan Genap otomatis
                            </Label>
                        </div>
                        {hasExistingYears && (
                            <Button
                                type="button"
                                variant="ghost"
                                className="h-11 w-fit"
                                onClick={() => onYearOpenChange(false)}
                            >
                                Tidak jadi menambah tahun ajaran
                            </Button>
                        )}
                    </div>
                )}
            </fieldset>

            <fieldset className="grid gap-4">
                <legend className="mb-1 text-base font-semibold">
                    Gelombang pendaftaran (opsional)
                </legend>

                {!waveOpen ? (
                    <Button
                        type="button"
                        variant="outline"
                        className="h-11 w-fit"
                        aria-expanded={false}
                        onClick={() => onWaveOpenChange(true)}
                    >
                        <Plus />
                        Tambah gelombang pendaftaran pertama
                    </Button>
                ) : (
                    <div className="grid gap-5">
                        <FormField
                            label="Nama gelombang"
                            error={errors['wave.name']}
                            hint="Contoh: Gelombang 1. Kosongkan kalau tidak jadi."
                        >
                            {(control) => (
                                <Input
                                    {...control}
                                    name="wave[name]"
                                    autoComplete="off"
                                    value={data.wave.name}
                                    onChange={(event) =>
                                        setSection('wave', {
                                            name: event.target.value,
                                        })
                                    }
                                />
                            )}
                        </FormField>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <FormField
                                label="Mulai"
                                required={data.wave.name.trim() !== ''}
                                error={errors['wave.starts_on']}
                            >
                                {(control) => (
                                    <Input
                                        {...control}
                                        type="date"
                                        name="wave[starts_on]"
                                        value={data.wave.starts_on}
                                        onChange={(event) =>
                                            setSection('wave', {
                                                starts_on: event.target.value,
                                            })
                                        }
                                    />
                                )}
                            </FormField>
                            <FormField
                                label="Selesai"
                                required={data.wave.name.trim() !== ''}
                                error={errors['wave.ends_on']}
                            >
                                {(control) => (
                                    <Input
                                        {...control}
                                        type="date"
                                        name="wave[ends_on]"
                                        value={data.wave.ends_on}
                                        onChange={(event) =>
                                            setSection('wave', {
                                                ends_on: event.target.value,
                                            })
                                        }
                                    />
                                )}
                            </FormField>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            className="h-11 w-fit"
                            onClick={() => onWaveOpenChange(false)}
                        >
                            Tidak jadi menambah gelombang
                        </Button>
                    </div>
                )}
            </fieldset>
        </div>
    );
}
