import { Form, Head, Link } from '@inertiajs/react';
import AuthStatus from '@/components/auth-status';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Masuk" />

            <div className="grid gap-6">
                <AuthStatus status={status} />

                <Form
                    {...store.form()}
                    resetOnSuccess={['password']}
                    className="grid gap-5"
                >
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
                                    required
                                    autoFocus
                                    autoComplete="email"
                                    placeholder="email@contoh.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center justify-between gap-3">
                                    <Label
                                        htmlFor="password"
                                        className="text-[15px]"
                                    >
                                        Kata sandi
                                    </Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="py-1 text-[15px]"
                                        >
                                            Lupa kata sandi?
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoComplete="current-password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <label
                                htmlFor="remember"
                                className="flex min-h-11 cursor-pointer items-center gap-3 text-[15px] font-medium"
                            >
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    className="size-6"
                                />
                                Ingat saya di perangkat ini
                            </label>

                            <Button
                                type="submit"
                                size="lg"
                                className="w-full"
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Masuk
                            </Button>
                        </>
                    )}
                </Form>

                <div
                    role="separator"
                    className="flex items-center gap-3 text-sm text-muted-foreground"
                >
                    <span className="h-px flex-1 bg-line" />
                    atau
                    <span className="h-px flex-1 bg-line" />
                </div>

                <div className="grid gap-2 text-center">
                    <p className="text-[15px] text-muted-foreground">
                        Belum punya akun?
                    </p>
                    <Button asChild size="lg" variant="outline">
                        <Link href={register()}>Daftar</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

Login.layout = {
    title: 'Masuk ke akunmu',
    description: 'Masukkan email dan kata sandimu untuk masuk.',
};
