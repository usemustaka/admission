import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { ChoiceCardGroup } from '@/components/choice-card-group';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { store } from '@/routes/admin/notes';
import type { Option } from '@/types/people';

function toggle(list: number[], value: number, checked: boolean): number[] {
    return checked ? [...list, value] : list.filter((item) => item !== value);
}

export function NoteForm({
    personId,
    urgencies,
    visibilities,
    users,
    roles,
}: {
    personId: number;
    urgencies: Option[];
    visibilities: Option[];
    users: Option<number>[];
    roles: Option<number>[];
}) {
    const form = useForm({
        noteable_type: 'person',
        noteable_id: personId,
        body: '',
        urgency: 'normal',
        visibility: 'private',
        shared_with_family: false,
        user_ids: [] as number[],
        role_ids: [] as number[],
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(store().url, {
            preserveScroll: true,
            onSuccess: () => form.reset('body', 'shared_with_family'),
        });
    }

    return (
        <form onSubmit={submit} className="grid gap-5">
            <FormField label="Catatan" required error={form.errors.body}>
                {(control) => (
                    <Textarea
                        placeholder="Tulis catatan untuk panitia…"
                        value={form.data.body}
                        onChange={(e) => form.setData('body', e.target.value)}
                        {...control}
                    />
                )}
            </FormField>

            <div className="grid gap-5 sm:grid-cols-2">
                <FormField
                    label="Seberapa mendesak?"
                    error={form.errors.urgency}
                >
                    {(control) => (
                        <Select
                            value={form.data.urgency}
                            onValueChange={(value) =>
                                form.setData('urgency', value)
                            }
                        >
                            <SelectTrigger className="w-full" {...control}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {urgencies.map((urgency) => (
                                    <SelectItem
                                        key={urgency.value}
                                        value={urgency.value}
                                    >
                                        {urgency.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </FormField>

                <FormField
                    label="Siapa yang bisa melihat?"
                    error={form.errors.visibility}
                >
                    {() => (
                        <ChoiceCardGroup
                            columns={1}
                            value={form.data.visibility}
                            onValueChange={(value) =>
                                form.setData('visibility', value)
                            }
                            options={visibilities}
                        />
                    )}
                </FormField>
            </div>

            {(users.length > 0 || roles.length > 0) && (
                <fieldset className="grid gap-3">
                    <legend className="mb-1 text-sm font-medium">
                        Bagikan juga ke
                    </legend>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {roles.map((role) => (
                            <label
                                key={`role-${role.value}`}
                                className="flex items-center gap-2.5 text-sm"
                            >
                                <Checkbox
                                    checked={form.data.role_ids.includes(
                                        role.value,
                                    )}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'role_ids',
                                            toggle(
                                                form.data.role_ids,
                                                role.value,
                                                checked === true,
                                            ),
                                        )
                                    }
                                />
                                Semua {role.label}
                            </label>
                        ))}
                        {users.map((user) => (
                            <label
                                key={`user-${user.value}`}
                                className="flex items-center gap-2.5 text-sm"
                            >
                                <Checkbox
                                    checked={form.data.user_ids.includes(
                                        user.value,
                                    )}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'user_ids',
                                            toggle(
                                                form.data.user_ids,
                                                user.value,
                                                checked === true,
                                            ),
                                        )
                                    }
                                />
                                {user.label}
                            </label>
                        ))}
                    </div>
                </fieldset>
            )}

            <FormField
                label="Share ke keluarga anak"
                error={form.errors.shared_with_family}
                hint="Orang tua dan wali anak ini bisa membaca catatan ini di portal mereka."
            >
                {(control) => (
                    <Switch
                        {...control}
                        checked={form.data.shared_with_family}
                        onCheckedChange={(checked) =>
                            form.setData('shared_with_family', checked)
                        }
                    />
                )}
            </FormField>

            <div className="flex justify-end">
                <Button
                    type="submit"
                    disabled={form.processing || form.data.body.trim() === ''}
                >
                    {form.processing && <Spinner />}
                    Simpan catatan
                </Button>
            </div>
        </form>
    );
}
