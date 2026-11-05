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
    options: { educations: Option[]; incomes: Option[]; kinds: Option[] };
    /** The NIK of a parent who is already known cannot be changed here. */
    lockedNik: boolean;
}) {
    const error = (field: string) => errors[`parents.${relationKey}.${field}`];

    return (
        <div className="grid gap-5 sm:grid-cols-2">
            <FormField label="Nama lengkap" error={error('name')}>
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
            <FormField label="Nomor HP" error={error('phone')}>
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
            <FormField label="Pekerjaan" error={error('job')}>
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
                error={error('address')}
                className="sm:col-span-2"
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
                    className="sm:col-span-2"
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
            <label className="flex items-center gap-3 text-sm font-medium sm:col-span-2">
                <Switch
                    checked={value.is_alive}
                    onCheckedChange={(is_alive) => onChange({ is_alive })}
                />
                Masih hidup
            </label>
        </div>
    );
}
