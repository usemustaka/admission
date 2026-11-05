import { Head, Link, router, useForm } from '@inertiajs/react';
import { Mail, ShieldAlert, ShieldCheck, UserCheck, UserX } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DescriptionList } from '@/components/description-list';
import { PageHeader } from '@/components/page-header';
import { PersonAvatar } from '@/components/person-avatar';
import { SectionCard } from '@/components/section-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import { show as showPerson } from '@/routes/admin/people';
import { index as roleIndex } from '@/routes/admin/roles';
import {
    deactivate,
    index,
    passwordChange,
    reactivate,
    setupLink,
    update,
} from '@/routes/admin/users';
import type { RoleOption, StaffDetail } from '@/types/access';

type Action = 'deactivate' | 'reactivate' | 'password' | 'link';

const SUPER_ADMIN = 'super-admin';

export default function UserShow({
    staff,
    roles,
    assigned,
    can,
}: {
    staff: StaffDetail;
    roles: RoleOption[];
    assigned: string[];
    can: { manage: boolean; viewPerson: boolean };
}) {
    const form = useForm<{ roles: string[] }>({ roles: assigned });
    const [pending, setPending] = useState<Action | null>(null);
    const [busy, setBusy] = useState(false);

    function saveRoles(event: FormEvent) {
        event.preventDefault();
        form.put(update(staff.id).url, { preserveScroll: true });
    }

    function toggleRole(name: string, checked: boolean) {
        form.setData(
            'roles',
            checked
                ? [...form.data.roles, name]
                : form.data.roles.filter((role) => role !== name),
        );
    }

    function run(action: Action) {
        const url = {
            deactivate: deactivate(staff.id),
            reactivate: reactivate(staff.id),
            password: passwordChange(staff.id),
            link: setupLink(staff.id),
        }[action].url;

        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onSuccess: () => setPending(null),
                onError: (errors) => {
                    setPending(null);
                    toast.error(
                        Object.values(errors)[0] ??
                            'Aksi gagal. Coba lagi sebentar lagi.',
                    );
                },
                onFinish: () => setBusy(false),
            },
        );
    }

    const rolesError =
        form.errors.roles ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('roles.'),
        )?.[1];

    const lockedReason = (role: RoleOption): string | null => {
        if (!can.manage) {
            return null;
        }

        if (
            role.name === SUPER_ADMIN &&
            form.data.roles.includes(SUPER_ADMIN) &&
            assigned.includes(SUPER_ADMIN)
        ) {
            if (staff.is_last_super_admin) {
                return 'Super admin terakhir, tidak bisa diturunkan';
            }

            if (staff.is_self) {
                return 'Tidak bisa dicabut dari dirimu sendiri';
            }
        }

        if (!role.assignable) {
            return role.name === SUPER_ADMIN
                ? 'Hanya super admin yang bisa mengatur ini'
                : 'Berisi izin yang tidak kamu miliki';
        }

        return null;
    };

    const dialog = {
        deactivate: {
            title: `Nonaktifkan ${staff.name}?`,
            description:
                'Akun ini langsung keluar dari semua perangkat dan tidak bisa masuk lagi. Catatan dan riwayat yang ditulisnya tetap ada, dan akunnya bisa diaktifkan kembali.',
            confirmLabel: 'Nonaktifkan',
            tone: 'danger' as const,
        },
        reactivate: {
            title: `Aktifkan kembali ${staff.name}?`,
            description:
                'Akun ini bisa masuk lagi dengan perannya yang tersimpan. Passwordnya tetap yang lama.',
            confirmLabel: 'Aktifkan kembali',
            tone: 'default' as const,
        },
        password: {
            title: 'Wajibkan ganti password?',
            description: `${staff.name} harus memilih password baru saat masuk berikutnya, sebelum bisa memakai panel.`,
            confirmLabel: 'Wajibkan',
            tone: 'default' as const,
        },
        link: {
            title: 'Kirim ulang tautan atur password?',
            description: `Email berisi tautan untuk memilih password dikirim ke ${staff.email}.`,
            confirmLabel: 'Kirim tautan',
            tone: 'default' as const,
        },
    };

    return (
        <>
            <Head title={staff.name} />
            <div className="grid gap-6">
                <PageHeader
                    title={staff.name}
                    description={staff.email}
                    meta={
                        <div className="flex flex-wrap items-center gap-2 pt-1">
                            {staff.is_active ? (
                                <Badge variant="success">Aktif</Badge>
                            ) : (
                                <Badge variant="danger">Nonaktif</Badge>
                            )}
                            {staff.is_self && (
                                <Badge variant="muted">Akunmu</Badge>
                            )}
                        </div>
                    }
                    actions={
                        can.viewPerson && staff.person_id !== null ? (
                            <Button variant="outline" asChild>
                                <Link href={showPerson(staff.person_id)}>
                                    Lihat data orang
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />

                {!can.manage && (
                    <Alert variant="info">
                        <ShieldAlert />
                        <AlertTitle>Hanya bisa dilihat</AlertTitle>
                        <AlertDescription>
                            Akun super admin hanya bisa diubah oleh super admin.
                        </AlertDescription>
                    </Alert>
                )}

                {!staff.is_active && (
                    <Alert variant="warning">
                        <UserX />
                        <AlertTitle>Akun ini nonaktif</AlertTitle>
                        <AlertDescription>
                            Semua izinnya ditolak dan sesinya sudah dihapus.
                            Aktifkan kembali untuk mengizinkannya masuk.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 @5xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <form onSubmit={saveRoles} className="grid content-start">
                        <SectionCard
                            title="Peran"
                            description="Izin staf berasal dari gabungan perannya. Satu staf boleh punya beberapa peran."
                            icon={ShieldCheck}
                            actions={
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={roleIndex()}>Kelola peran</Link>
                                </Button>
                            }
                        >
                            <div className="grid gap-3">
                                {roles.map((role) => {
                                    const reason = lockedReason(role);
                                    const id = `role-${role.name}`;

                                    return (
                                        <div
                                            key={role.name}
                                            className="flex items-start gap-3"
                                        >
                                            <Checkbox
                                                id={id}
                                                checked={form.data.roles.includes(
                                                    role.name,
                                                )}
                                                disabled={
                                                    !can.manage ||
                                                    reason !== null
                                                }
                                                onCheckedChange={(checked) =>
                                                    toggleRole(
                                                        role.name,
                                                        checked === true,
                                                    )
                                                }
                                                className="mt-0.5"
                                            />
                                            <Label
                                                htmlFor={id}
                                                className="grid gap-0.5 text-[15px]"
                                            >
                                                <span className="font-semibold">
                                                    {role.name}
                                                </span>
                                                <span className="text-sm font-normal text-muted-foreground">
                                                    {role.abilities_count} izin
                                                    {reason && ` · ${reason}`}
                                                </span>
                                            </Label>
                                        </div>
                                    );
                                })}

                                {rolesError && (
                                    <p
                                        role="alert"
                                        className="text-[15px] font-medium text-destructive-foreground"
                                    >
                                        {rolesError}
                                    </p>
                                )}

                                {can.manage && (
                                    <div className="flex justify-end pt-2">
                                        <Button
                                            type="submit"
                                            disabled={
                                                form.processing || !form.isDirty
                                            }
                                        >
                                            {form.processing && <Spinner />}
                                            Simpan peran
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </SectionCard>
                    </form>

                    <div className="grid content-start gap-6">
                        <SectionCard title="Akun">
                            <div className="mb-5 flex items-center gap-3">
                                <PersonAvatar
                                    name={staff.name}
                                    src={staff.photo_url}
                                    size="lg"
                                />
                                <p className="min-w-0 text-sm text-muted-foreground">
                                    Ditambahkan{' '}
                                    {staff.created_at
                                        ? formatDate(staff.created_at)
                                        : '-'}
                                </p>
                            </div>
                            <DescriptionList
                                className="@2xl:grid-cols-1"
                                items={[
                                    {
                                        label: 'Email',
                                        value: staff.email_verified ? (
                                            <Badge variant="success">
                                                Terverifikasi
                                            </Badge>
                                        ) : (
                                            <Badge variant="warning">
                                                Belum verifikasi
                                            </Badge>
                                        ),
                                    },
                                    {
                                        label: 'Verifikasi dua langkah (2FA)',
                                        value: staff.has_two_factor ? (
                                            <Badge variant="success">
                                                Aktif
                                            </Badge>
                                        ) : (
                                            <Badge variant="muted">
                                                Belum diaktifkan
                                            </Badge>
                                        ),
                                    },
                                    {
                                        label: 'Ganti password',
                                        value: staff.password_change_required ? (
                                            <Badge variant="warning">
                                                Wajib saat masuk berikutnya
                                            </Badge>
                                        ) : (
                                            'Tidak diwajibkan'
                                        ),
                                    },
                                ]}
                            />
                        </SectionCard>

                        {can.manage && (
                            <SectionCard title="Tindakan">
                                <div className="grid gap-2">
                                    <Button
                                        variant="outline"
                                        className="justify-start"
                                        onClick={() => setPending('link')}
                                    >
                                        <Mail />
                                        Kirim ulang tautan atur password
                                    </Button>
                                    <Button
                                        variant="outline"
                                        className="justify-start"
                                        onClick={() => setPending('password')}
                                        disabled={
                                            staff.password_change_required
                                        }
                                    >
                                        <ShieldAlert />
                                        Wajibkan ganti password
                                    </Button>
                                    {staff.is_active ? (
                                        <>
                                            <Button
                                                variant="outline"
                                                className="justify-start text-destructive-foreground"
                                                onClick={() =>
                                                    setPending('deactivate')
                                                }
                                                disabled={staff.is_self}
                                            >
                                                <UserX />
                                                Nonaktifkan akun
                                            </Button>
                                            {staff.is_self && (
                                                <p className="text-sm text-muted-foreground">
                                                    Kamu tidak bisa
                                                    menonaktifkan akunmu
                                                    sendiri.
                                                </p>
                                            )}
                                        </>
                                    ) : (
                                        <Button
                                            variant="outline"
                                            className="justify-start"
                                            onClick={() =>
                                                setPending('reactivate')
                                            }
                                        >
                                            <UserCheck />
                                            Aktifkan kembali
                                        </Button>
                                    )}
                                </div>
                            </SectionCard>
                        )}
                    </div>
                </div>
            </div>

            <ConfirmDialog
                open={pending !== null}
                onOpenChange={(open) => !open && !busy && setPending(null)}
                processing={busy}
                onConfirm={() => pending && run(pending)}
                {...dialog[pending ?? 'link']}
            />
        </>
    );
}

UserShow.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Staf', href: index() },
    ],
};
