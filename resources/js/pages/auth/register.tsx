import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    return (
        <>
            <Head title="Daftar" />
            <div className="grid gap-6">
                <Form
                    {...store.form()}
                    resetOnSuccess={['password', 'password_confirmation']}
                    disableWhileProcessing
                    className="grid gap-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name" className="text-[15px]">
                                    Nama lengkap
                                </Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    autoComplete="name"
                                    name="name"
                                    placeholder="Nama sesuai KTP"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email" className="text-[15px]">
                                    Alamat email
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    autoComplete="email"
                                    name="email"
                                    placeholder="email@contoh.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label
                                    htmlFor="password"
                                    className="text-[15px]"
                                >
                                    Kata sandi
                                </Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    autoComplete="new-password"
                                    name="password"
                                    passwordrules={passwordRules}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label
                                    htmlFor="password_confirmation"
                                    className="text-[15px]"
                                >
                                    Ulangi kata sandi
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    passwordrules={passwordRules}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                type="submit"
                                size="lg"
                                className="w-full"
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Daftar
                            </Button>
                        </>
                    )}
                </Form>

                <p className="text-center text-[15px] text-muted-foreground">
                    Sudah punya akun? <TextLink href={login()}>Masuk</TextLink>
                </p>
            </div>
        </>
    );
}

Register.layout = {
    title: 'Buat akun',
    description: 'Isi data di bawah untuk membuat akunmu.',
};
