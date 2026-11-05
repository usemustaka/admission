import { Form, Head, Link, usePage } from '@inertiajs/react';
import { CircleAlert, CircleCheck } from 'lucide-react';
import { useState } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeactivateAccount from '@/components/deactivate-account';
import { FormField } from '@/components/form-field';
import PasswordInput from '@/components/password-input';
import { SectionCard } from '@/components/section-card';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
    canDeactivateAccount,
}: {
    mustVerifyEmail: boolean;
    status?: string;
    canDeactivateAccount: boolean;
}) {
    const { auth } = usePage<PageProps>().props;
    const [email, setEmail] = useState(auth.user.email);
    const emailChanged = email !== auth.user.email;

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
                                        onChange={(event) =>
                                            setEmail(event.target.value)
                                        }
                                        {...control}
                                    />
                                )}
                            </FormField>

                            {emailChanged && (
                                <>
                                    <Alert variant="warning">
                                        <CircleAlert />
                                        <AlertDescription>
                                            Setelah email diganti, kamu perlu
                                            memverifikasinya lagi lewat tautan
                                            yang kami kirim ke alamat baru.
                                        </AlertDescription>
                                    </Alert>

                                    <FormField
                                        label="Kata sandi saat ini"
                                        hint="Diperlukan untuk mengganti alamat email."
                                        error={errors.current_password}
                                    >
                                        {(control) => (
                                            <PasswordInput
                                                name="current_password"
                                                autoComplete="current-password"
                                                {...control}
                                            />
                                        )}
                                    </FormField>
                                </>
                            )}

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

            {canDeactivateAccount && <DeactivateAccount />}
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
