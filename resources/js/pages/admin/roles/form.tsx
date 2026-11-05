import { Head, Link, useForm } from '@inertiajs/react';
import { Lock, TriangleAlert } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { index, store, update } from '@/routes/admin/roles';
import type { AbilityGroup, RoleDetail } from '@/types/access';

const ADMIN_ACCESS = 'core::admin.access';

export default function RoleForm({
    role,
    groups,
}: {
    role: RoleDetail | null;
    groups: AbilityGroup[];
}) {
    const isEditing = role !== null;
    const isLocked = role?.is_locked ?? false;
    const form = useForm<{ name: string; abilities: string[] }>({
        name: role?.name ?? '',
        abilities: role?.abilities ?? [],
    });

    const readOnly = isLocked;
    const dropsAdminAccess =
        isEditing &&
        role.users_count > 0 &&
        role.abilities.includes(ADMIN_ACCESS) &&
        !form.data.abilities.includes(ADMIN_ACCESS);

    function submit(event: FormEvent) {
        event.preventDefault();

        if (isEditing) {
            form.put(update(String(role.id)).url);

            return;
        }

        form.post(store().url);
    }

    function toggle(value: string, checked: boolean) {
        form.setData(
            'abilities',
            checked
                ? [...form.data.abilities, value]
                : form.data.abilities.filter((ability) => ability !== value),
        );
    }

    function toggleGroup(group: AbilityGroup, checked: boolean) {
        const editable = group.abilities
            .filter((ability) => ability.held)
            .map((ability) => ability.value);
        const rest = form.data.abilities.filter(
            (ability) => !editable.includes(ability),
        );

        form.setData('abilities', checked ? [...rest, ...editable] : rest);
    }

    const title = isEditing
        ? `${readOnly ? 'Peran' : 'Ubah peran'} ${role.name}`
        : 'Buat peran';

    return (
        <>
            <Head title={title} />
            <form
                onSubmit={submit}
                className="mx-auto grid w-full max-w-4xl gap-6"
            >
                <PageHeader
                    title={title}
                    description={
                        isEditing
                            ? `Dipakai ${role.users_count} akun. Perubahan langsung berlaku untuk semuanya.`
                            : 'Beri nama peran, lalu centang izin yang diperlukan.'
                    }
                />

                {readOnly && (
                    <Alert variant="info">
                        <Lock />
                        <AlertTitle>Peran terkunci</AlertTitle>
                        <AlertDescription>
                            Peran ini dipakai sistem, jadi tidak bisa diubah
                            atau dihapus.
                        </AlertDescription>
                    </Alert>
                )}

                {dropsAdminAccess && (
                    <Alert variant="warning">
                        <TriangleAlert />
                        <AlertTitle>
                            Akun peran ini kehilangan akses panel
                        </AlertTitle>
                        <AlertDescription>
                            Izin masuk panel admin tidak bisa dicabut selama
                            peran ini masih dipakai {role.users_count} akun.
                            Pindahkan akunnya ke peran lain dulu.
                        </AlertDescription>
                    </Alert>
                )}

                <SectionCard title="Nama peran">
                    <FormField
                        label="Nama"
                        required
                        hint={
                            role?.is_built_in
                                ? 'Nama peran bawaan tidak bisa diubah.'
                                : 'Huruf kecil, angka, spasi, dan tanda hubung. Contoh: bendahara'
                        }
                        error={form.errors.name}
                    >
                        {(control) => (
                            <Input
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                maxLength={50}
                                disabled={readOnly || role?.is_built_in}
                                {...control}
                            />
                        )}
                    </FormField>
                </SectionCard>

                {form.errors.abilities && (
                    <Alert variant="destructive">
                        <TriangleAlert />
                        <AlertTitle>Izin belum bisa disimpan</AlertTitle>
                        <AlertDescription>
                            {form.errors.abilities}
                        </AlertDescription>
                    </Alert>
                )}

                {groups.map((group) => {
                    const editable = group.abilities.filter(
                        (ability) => ability.held,
                    );
                    const checkedCount = editable.filter((ability) =>
                        form.data.abilities.includes(ability.value),
                    ).length;
                    const allChecked =
                        editable.length > 0 && checkedCount === editable.length;
                    const groupId = `group-${group.module}`;

                    return (
                        <SectionCard
                            key={group.module}
                            title={group.title}
                            actions={
                                !readOnly &&
                                editable.length > 0 && (
                                    <span className="flex items-center gap-2">
                                        <Checkbox
                                            id={groupId}
                                            checked={
                                                allChecked
                                                    ? true
                                                    : checkedCount > 0
                                                      ? 'indeterminate'
                                                      : false
                                            }
                                            onCheckedChange={(checked) =>
                                                toggleGroup(
                                                    group,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <Label
                                            htmlFor={groupId}
                                            className="text-sm font-semibold"
                                        >
                                            Pilih semua
                                        </Label>
                                    </span>
                                )
                            }
                        >
                            <div className="grid gap-3.5">
                                {group.abilities.map((ability) => {
                                    const id = `ability-${ability.value}`;

                                    return (
                                        <div
                                            key={ability.value}
                                            className="flex items-start gap-3"
                                        >
                                            <Checkbox
                                                id={id}
                                                checked={form.data.abilities.includes(
                                                    ability.value,
                                                )}
                                                disabled={
                                                    readOnly || !ability.held
                                                }
                                                onCheckedChange={(checked) =>
                                                    toggle(
                                                        ability.value,
                                                        checked === true,
                                                    )
                                                }
                                                className="mt-0.5"
                                            />
                                            <Label
                                                htmlFor={id}
                                                className="grid gap-0.5 text-[15px] font-medium"
                                            >
                                                {ability.label}
                                                {!ability.held && !readOnly && (
                                                    <span className="text-sm font-normal text-muted-foreground">
                                                        Kamu tidak punya izin
                                                        ini, jadi tidak bisa
                                                        mengubahnya.
                                                    </span>
                                                )}
                                            </Label>
                                        </div>
                                    );
                                })}
                            </div>
                        </SectionCard>
                    );
                })}

                <div className="flex flex-wrap justify-end gap-2">
                    <Button variant="outline" asChild>
                        <Link href={index()}>
                            {readOnly ? 'Kembali' : 'Batal'}
                        </Link>
                    </Button>
                    {!readOnly && (
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {isEditing ? 'Simpan perubahan' : 'Buat peran'}
                        </Button>
                    )}
                </div>
            </form>
        </>
    );
}

RoleForm.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Peran', href: index() },
    ],
};
