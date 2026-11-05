import { Form, Head } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import { useRef } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import { FormField } from '@/components/form-field';
import PasswordInput from '@/components/password-input';
import { SectionCard } from '@/components/section-card';
import ActiveSessions from '@/components/settings/active-sessions';
import type { ActiveSession } from '@/components/settings/active-sessions';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/security';
import type { Props as ManageTwoFactorProps } from '@/components/manage-two-factor';
import ManageTwoFactor from '@/components/manage-two-factor';

// oxfmt-ignore
type Props = {
    passwordRules: string;
    passwordChangeRequired: boolean;
    twoFactorRequired: boolean;
    sessions: ActiveSession[] | null;
} &
    ManageTwoFactorProps;

export default function Security(props: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    return (
        <>
            <Head title="Pengaturan keamanan" />

            {props.passwordChangeRequired && (
                <Alert variant="warning">
                    <CircleAlert />
                    <AlertDescription>
                        Admin meminta kamu mengganti kata sandi. Ganti dulu
                        sebelum melanjutkan.
                    </AlertDescription>
                </Alert>
            )}

            {props.twoFactorRequired && (
                <Alert variant="warning">
                    <CircleAlert />
                    <AlertDescription>
                        Akun staf wajib mengaktifkan autentikasi dua langkah.
                        Aktifkan dulu sebelum membuka halaman admin.
                    </AlertDescription>
                </Alert>
            )}

            <SectionCard
                title="Ganti kata sandi"
                description="Pakai kata sandi yang panjang dan sulit ditebak supaya akunmu aman."
            >
                <Form
                    {...SecurityController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    resetOnError={[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]}
                    resetOnSuccess
                    onError={(errors) => {
                        if (errors.password) {
                            passwordInput.current?.focus();
                        }

                        if (errors.current_password) {
                            currentPasswordInput.current?.focus();
                        }
                    }}
                    className="grid gap-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <FormField
                                label="Kata sandi saat ini"
                                error={errors.current_password}
                            >
                                {(control) => (
                                    <PasswordInput
                                        ref={currentPasswordInput}
                                        name="current_password"
                                        autoComplete="current-password"
                                        {...control}
                                    />
                                )}
                            </FormField>

                            <FormField
                                label="Kata sandi baru"
                                error={errors.password}
                            >
                                {(control) => (
                                    <PasswordInput
                                        ref={passwordInput}
                                        name="password"
                                        autoComplete="new-password"
                                        passwordrules={props.passwordRules}
                                        {...control}
                                    />
                                )}
                            </FormField>

                            <FormField
                                label="Ulangi kata sandi baru"
                                error={errors.password_confirmation}
                            >
                                {(control) => (
                                    <PasswordInput
                                        name="password_confirmation"
                                        autoComplete="new-password"
                                        passwordrules={props.passwordRules}
                                        {...control}
                                    />
                                )}
                            </FormField>

                            <Button
                                size="lg"
                                className="w-full @2xl:w-fit"
                                disabled={processing}
                                data-test="update-password-button"
                            >
                                {processing && <Spinner />}
                                Simpan kata sandi
                            </Button>
                        </>
                    )}
                </Form>
            </SectionCard>

            <ManageTwoFactor
                canManageTwoFactor={props.canManageTwoFactor}
                requiresConfirmation={props.requiresConfirmation}
                twoFactorEnabled={props.twoFactorEnabled}
            />

            <ActiveSessions sessions={props.sessions} />
        </>
    );
}

Security.layout = {
    breadcrumbs: [
        {
            title: 'Pengaturan keamanan',
            href: edit(),
        },
    ],
};
