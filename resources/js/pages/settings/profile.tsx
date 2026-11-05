import { Form, Head, Link, usePage } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import { FormField } from '@/components/form-field';
import { SectionCard } from '@/components/section-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/profile';
import type { Auth } from '@/types';
import { send } from '@/routes/verification';

type PageProps = {
    auth: Auth;
};

export default function Profile({
    mustVerifyEmail,
    status,
    canDeleteAccount,
}: {
    mustVerifyEmail: boolean;
    status?: string;
    canDeleteAccount: boolean;
}) {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Pengaturan profil" />

            <SectionCard
                title="Profil"
                description="Ubah nama dan alamat emailmu."
            >
                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <FormField label="Nama lengkap" error={errors.name}>
                                {(control) => (
                                    <Input
                                        defaultValue={auth.user.name}
                                        name="name"
                                        required
                                        autoComplete="name"
                                        {...control}
                                    />
                                )}
                            </FormField>

                            <FormField
                                label="Alamat email"
                                error={errors.email}
                            >
                                {(control) => (
                                    <Input
                                        type="email"
                                        defaultValue={auth.user.email}
                                        name="email"
                                        required
                                        autoComplete="username"
                                        {...control}
                                    />
                                )}
                            </FormField>

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <div className="grid gap-2 text-[15px]">
                                        <p className="text-muted-foreground">
                                            Alamat emailmu belum diverifikasi.{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="inline-flex min-h-11 items-center font-semibold text-foreground underline underline-offset-4"
                                            >
                                                Kirim ulang email verifikasi
                                            </Link>
                                        </p>

                                        {status ===
                                            'verification-link-sent' && (
                                            <p className="flex items-center gap-2 font-medium text-success">
                                                <CircleCheck className="size-5" />
                                                Tautan verifikasi baru sudah
                                                dikirim ke emailmu.
                                            </p>
                                        )}
                                    </div>
                                )}

                            <Button
                                size="lg"
                                className="w-full @2xl:w-fit"
                                disabled={processing}
                                data-test="update-profile-button"
                            >
                                {processing && <Spinner />}
                                Simpan
                            </Button>
                        </>
                    )}
                </Form>
            </SectionCard>

            {canDeleteAccount && <DeleteUser />}
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Pengaturan profil',
            href: edit(),
        },
    ],
};
