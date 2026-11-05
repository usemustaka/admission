import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { OptionSelect } from '@/components/option-select';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { dashboard } from '@/routes';
import { index, show, store, update } from '@/routes/admin/rulesets';
import type { RulesetOptions, RulesetSummary } from '@/types/admission';

export default function RulesetForm({
    ruleset,
    options,
}: {
    ruleset: RulesetSummary | null;
    options: RulesetOptions;
}) {
    const form = useForm({
        name: ruleset?.name ?? '',
        wave_id: ruleset?.wave_id ? String(ruleset.wave_id) : '',
        organization_id: ruleset?.organization_id
            ? String(ruleset.organization_id)
            : '',
        gender: ruleset?.gender_value ?? '',
        is_active: ruleset?.is_active ?? true,
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        if (ruleset) {
            form.put(update(ruleset.id).url);
        } else {
            form.post(store().url);
        }
    }

    const title = ruleset ? `Ubah ${ruleset.name}` : 'Aturan baru';

    return (
        <>
            <Head title={title} />
            <form
                onSubmit={submit}
                className="mx-auto grid w-full max-w-2xl gap-6 p-4 md:p-6"
            >
                <PageHeader
                    title={title}
                    description="Syarat pendaftar yang memakai aturan ini. Kosongkan yang tidak penting supaya berlaku untuk semua."
                />

                <SectionCard title="Nama dan syarat">
                    <div className="grid gap-6">
                        <FormField
                            label="Nama aturan"
                            required
                            error={form.errors.name}
                        >
                            {(control) => (
                                <Input
                                    placeholder="Contoh: SMP putra"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    {...control}
                                />
                            )}
                        </FormField>

                        <div className="grid gap-6 sm:grid-cols-3">
                            <FormField
                                label="Gelombang"
                                error={form.errors.wave_id}
                            >
                                {(control) => (
                                    <OptionSelect
                                        value={form.data.wave_id}
                                        onValueChange={(value) =>
                                            form.setData('wave_id', value)
                                        }
                                        options={options.waves}
                                        anyLabel="Semua gelombang"
                                        {...control}
                                    />
                                )}
                            </FormField>
                            <FormField
                                label="Lembaga"
                                error={form.errors.organization_id}
                            >
                                {(control) => (
                                    <OptionSelect
                                        value={form.data.organization_id}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'organization_id',
                                                value,
                                            )
                                        }
                                        options={options.organizations}
                                        anyLabel="Semua lembaga"
                                        {...control}
                                    />
                                )}
                            </FormField>
                            <FormField
                                label="Jenis kelamin"
                                error={form.errors.gender}
                            >
                                {(control) => (
                                    <OptionSelect
                                        value={form.data.gender}
                                        onValueChange={(value) =>
                                            form.setData('gender', value)
                                        }
                                        options={options.genders}
                                        anyLabel="Semua"
                                        {...control}
                                    />
                                )}
                            </FormField>
                        </div>

                        <label className="flex items-center gap-3 text-sm font-medium">
                            <Switch
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked)
                                }
                            />
                            Aturan aktif
                        </label>
                    </div>
                </SectionCard>

                <div className="flex justify-end gap-3">
                    <Button variant="outline" asChild>
                        <Link href={ruleset ? show(ruleset.id) : index()}>
                            Batal
                        </Link>
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        Simpan
                    </Button>
                </div>
            </form>
        </>
    );
}

RulesetForm.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Aturan pendaftaran', href: index() },
    ],
};
