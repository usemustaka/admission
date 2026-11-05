import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
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
import { dashboard } from '@/routes';
import { create, index, store } from '@/routes/admin/users';
import type { RoleOption } from '@/types/access';

export default function UserForm({ roles }: { roles: RoleOption[] }) {
    const form = useForm({ name: '', email: '', role: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(store().url);
    }

    return (
        <>
            <Head title="Tambah staf" />
            <form
                onSubmit={submit}
                className="mx-auto grid w-full max-w-3xl gap-6"
            >
                <PageHeader
                    title="Tambah staf"
                    description="Staf menerima email berisi tautan untuk memilih passwordnya sendiri. Kamu tidak perlu tahu passwordnya."
                />

                <SectionCard title="Akun staf">
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
                                    autoComplete="off"
                                    {...control}
                                />
                            )}
                        </FormField>

                        <FormField
                            label="Email"
                            required
                            hint="Kalau orangnya sudah tercatat dengan email ini, datanya dipakai ulang."
                            error={form.errors.email}
                        >
                            {(control) => (
                                <Input
                                    type="email"
                                    value={form.data.email}
                                    onChange={(e) =>
                                        form.setData('email', e.target.value)
                                    }
                                    autoComplete="off"
                                    {...control}
                                />
                            )}
                        </FormField>

                        <FormField
                            label="Peran"
                            required
                            hint="Peran bisa diubah nanti, dan ditambah lebih dari satu."
                            error={form.errors.role}
                        >
                            {(control) => (
                                <Select
                                    value={form.data.role || undefined}
                                    onValueChange={(value) =>
                                        form.setData('role', value)
                                    }
                                >
                                    <SelectTrigger
                                        className="w-full"
                                        {...control}
                                    >
                                        <SelectValue placeholder="Pilih peran" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {roles.map((role) => (
                                            <SelectItem
                                                key={role.name}
                                                value={role.name}
                                            >
                                                {role.name} ·{' '}
                                                {role.abilities_count} izin
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </FormField>
                    </div>
                </SectionCard>

                <div className="flex flex-wrap justify-end gap-2">
                    <Button variant="outline" asChild>
                        <Link href={index()}>Batal</Link>
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        Kirim undangan
                    </Button>
                </div>
            </form>
        </>
    );
}

UserForm.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Staf', href: index() },
        { title: 'Tambah staf', href: create() },
    ],
};
