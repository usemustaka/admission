import { Form, usePage } from '@inertiajs/react';
import { MailWarning } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { send } from '@/routes/verification';

/**
 * Stays at the top of the portal home while the parent's email is unverified, so the reminder is hard to miss
 * even when the school lets people in without verifying.
 */
export function EmailVerificationReminder() {
    const { auth } = usePage().props;

    if (!auth.email_unverified) {
        return null;
    }

    return (
        <div
            role="status"
            className="sticky top-2 z-20 grid gap-2 rounded-xl border border-warning/40 bg-warning/15 p-3 text-[15px] shadow-sm backdrop-blur"
        >
            <div className="flex items-start gap-2.5">
                <MailWarning className="mt-0.5 size-5 shrink-0" />
                <p>
                    <span className="font-semibold">Cek email kamu.</span> Kami
                    mengirim tautan verifikasi ke{' '}
                    <span className="font-medium break-all">
                        {auth.user.email}
                    </span>
                    . Buka tautannya supaya akunmu terverifikasi.
                </p>
            </div>
            <Form {...send.form()} className="flex items-center gap-3">
                {({ processing, wasSuccessful }) => (
                    <>
                        <Button
                            type="submit"
                            size="sm"
                            variant="secondary"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Kirim ulang email
                        </Button>
                        {wasSuccessful && (
                            <span className="text-sm font-medium">
                                Email terkirim. Cek kotak masuk atau spam.
                            </span>
                        )}
                    </>
                )}
            </Form>
        </div>
    );
}
