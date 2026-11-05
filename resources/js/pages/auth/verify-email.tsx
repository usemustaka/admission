import { Form, Head } from '@inertiajs/react';
import AuthStatus from '@/components/auth-status';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Verifikasi email" />

            <div className="grid gap-6">
                <AuthStatus status={status} />

                <Form {...send.form()} className="grid gap-4 text-center">
                    {({ processing }) => (
                        <>
                            <Button
                                size="lg"
                                className="w-full"
                                disabled={processing}
                                variant="secondary"
                            >
                                {processing && <Spinner />}
                                Kirim ulang email verifikasi
                            </Button>

                            <TextLink
                                href={logout()}
                                className="mx-auto block py-2 text-[15px]"
                            >
                                Keluar
                            </TextLink>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

VerifyEmail.layout = {
    title: 'Verifikasi emailmu',
    description:
        'Klik tautan yang baru kami kirim ke emailmu untuk memverifikasi alamat ini.',
};
