import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { requiredFields as saveRequiredFields } from '@/routes/admin/rulesets';

export type RequiredFieldGroup = {
    group: string;
    title: string;
    fields: { key: string; label: string }[];
};

/**
 * Pick which details of a registration the parent must fill in before sending, per ruleset. Each lembaga has its own
 * ruleset, so each decides what it insists on.
 */
export function RequiredFieldsEditor({
    rulesetId,
    groups,
    initial,
    canEdit,
}: {
    rulesetId: number;
    groups: RequiredFieldGroup[];
    initial: string[];
    canEdit: boolean;
}) {
    const form = useForm<{ fields: string[] }>({ fields: initial });

    function toggle(key: string, checked: boolean) {
        form.setData(
            'fields',
            checked
                ? [...form.data.fields, key]
                : form.data.fields.filter((field) => field !== key),
        );
    }

    function toggleGroup(group: RequiredFieldGroup, checked: boolean) {
        const keys = group.fields.map((field) => field.key);
        const others = form.data.fields.filter(
            (field) => !keys.includes(field),
        );

        form.setData('fields', checked ? [...others, ...keys] : others);
    }

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(saveRequiredFields(rulesetId).url, {
                    preserveScroll: true,
                });
            }}
            className="grid gap-6"
        >
            <p className="text-sm text-muted-foreground">
                Data yang dicentang wajib lengkap sebelum data bisa dikirim ke
                panitia. Data wali tidak pernah wajib. Perubahan hanya berlaku
                untuk anak yang mendaftar setelah ini.
            </p>

            <fieldset disabled={!canEdit} className="grid gap-6">
                {groups.map((group) => {
                    const checkedCount = group.fields.filter((field) =>
                        form.data.fields.includes(field.key),
                    ).length;

                    return (
                        <div key={group.group} className="grid gap-3">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <p className="text-[15px] font-semibold">
                                    {group.title}
                                </p>
                                <Label className="flex items-center gap-2 text-sm font-normal text-muted-foreground">
                                    <Checkbox
                                        checked={
                                            checkedCount === group.fields.length
                                        }
                                        onCheckedChange={(checked) =>
                                            toggleGroup(group, checked === true)
                                        }
                                    />
                                    Wajibkan semua
                                </Label>
                            </div>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {group.fields.map((field) => (
                                    <Label
                                        key={field.key}
                                        className="flex min-h-10 items-center gap-3 rounded-xl border border-line px-3 text-sm font-normal"
                                    >
                                        <Checkbox
                                            checked={form.data.fields.includes(
                                                field.key,
                                            )}
                                            onCheckedChange={(checked) =>
                                                toggle(
                                                    field.key,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        {field.label}
                                    </Label>
                                ))}
                            </div>
                        </div>
                    );
                })}
            </fieldset>

            {canEdit && (
                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        Simpan data wajib
                    </Button>
                </div>
            )}
        </form>
    );
}
