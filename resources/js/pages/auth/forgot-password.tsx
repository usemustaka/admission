import { Form, Head } from '@inertiajs/react';
import AuthStatus from '@/components/auth-status';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Lupa kata sandi" />

            <div className="grid gap-6">
                <AuthStatus status={status} />

                <Form {...email.form()} className="grid gap-5">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email" className="text-[15px]">
                                    Alamat email
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    autoComplete="off"
                                    autoFocus
                                    placeholder="email@contoh.com"
                                />

                                <InputError message={errors.email} />
                            </div>

                            <Button
                                size="lg"
                                className="w-full"
                                disabled={processing}
                                data-test="email-password-reset-link-button"
                            >
                                {processing && <Spinner />}
                                Kirim tautan atur ulang
                            </Button>
                        </>
                    )}
                </Form>

                <p className="text-center text-[15px] text-muted-foreground">
                    Sudah ingat?{' '}
                    <TextLink href={login()}>Kembali masuk</TextLink>
                </p>
            </div>
        </>
    );
}

ForgotPassword.layout = {
    title: 'Lupa kata sandi?',
    description:
        'Masukkan emailmu. Kami kirim tautan untuk mengatur ulang kata sandi.',
};
