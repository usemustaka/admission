import { ChoiceCardGroup } from '@/components/choice-card-group';
import { FormField } from '@/components/form-field';
import { NikInput } from '@/components/nik-input';
import { OptionSelect } from '@/components/option-select';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { Option } from '@/types/people';
import type { ParentFieldsData } from '@/types/portal';

export function ParentFields({
    relationKey,
    value,
    onChange,
    errors,
    options,
    lockedNik,
}: {
    relationKey: 'father' | 'mother' | 'guardian';
    value: ParentFieldsData;
    onChange: (changes: Partial<ParentFieldsData>) => void;
    errors: Record<string, string>;
    options: {
        educations: Option[];
        incomes: Option[];
        kinds: Option[];
        requiredFields: string[];
    };
    /** The NIK of a parent who is already known cannot be changed here. */
    lockedNik: boolean;
}) {
    const error = (field: string) => errors[`parents.${relationKey}.${field}`];
    const required = (field: string) =>
        options.requiredFields.includes(`${relationKey}.${field}`);

    return (
        <div className="grid gap-6 @2xl:grid-cols-2">
            <FormField
                label="Nama lengkap"
                required={required('name')}
                error={error('name')}
            >
                {(control) => (
                    <Input
                        value={value.name}
                        onChange={(e) => onChange({ name: e.target.value })}
                        {...control}
                    />
                )}
            </FormField>
            <FormField
                label="NIK"
                required={required('nik')}
                error={error('nik')}
                hint={
                    lockedNik
                        ? 'NIK yang sudah tercatat tidak bisa diubah di sini.'
                        : undefined
                }
            >
                {(control) => (
                    <NikInput
                        value={value.nik}
                        disabled={lockedNik}
                        onValueChange={(nik) => onChange({ nik })}
                        {...control}
                    />
                )}
            </FormField>
            <FormField
                label="Nomor HP / WA"
                required={required('phone')}
                error={error('phone')}
            >
                {(control) => (
                    <Input
                        type="tel"
                        inputMode="tel"
                        placeholder="081234567890"
                        value={value.phone}
                        onChange={(e) => onChange({ phone: e.target.value })}
                        {...control}
                    />
                )}
            </FormField>
            <FormField
                label="Pekerjaan"
                required={required('job')}
                error={error('job')}
            >
                {(control) => (
                    <Input
                        value={value.job}
                        onChange={(e) => onChange({ job: e.target.value })}
                        {...control}
                    />
                )}
            </FormField>
            <FormField
                label="Pendidikan terakhir"
                required={required('last_education')}
                error={error('last_education')}
            >
                {(control) => (
                    <OptionSelect
                        value={value.last_education}
                        onValueChange={(last_education) =>
                            onChange({ last_education })
                        }
                        options={options.educations}
                        anyLabel="Belum dipilih"
                        {...control}
                    />
                )}
            </FormField>
            <FormField
                label="Penghasilan per bulan"
                required={required('monthly_income')}
                error={error('monthly_income')}
            >
                {(control) => (
                    <OptionSelect
                        value={value.monthly_income}
                        onValueChange={(monthly_income) =>
                            onChange({ monthly_income })
                        }
                        options={options.incomes}
                        anyLabel="Belum dipilih"
                        {...control}
                    />
                )}
            </FormField>
            <FormField
                label="Alamat"
                required={required('address')}
                error={error('address')}
                className="@2xl:col-span-2"
            >
                {(control) => (
                    <Textarea
                        className="min-h-20"
                        value={value.address}
                        onChange={(e) => onChange({ address: e.target.value })}
                        {...control}
                    />
                )}
            </FormField>
            {relationKey !== 'guardian' && (
                <FormField
                    label="Kandung atau tiri?"
                    error={error('kind')}
                    className="@2xl:col-span-2"
                >
                    {() => (
                        <ChoiceCardGroup
                            columns={2}
                            value={value.kind}
                            onValueChange={(kind) => onChange({ kind })}
                            options={options.kinds}
                        />
                    )}
                </FormField>
            )}
            <label className="flex min-h-11 items-center gap-3 text-[15px] font-medium @2xl:col-span-2">
                <Switch
                    checked={value.is_alive}
                    onCheckedChange={(is_alive) => onChange({ is_alive })}
                />
                Masih hidup
            </label>
        </div>
    );
}
