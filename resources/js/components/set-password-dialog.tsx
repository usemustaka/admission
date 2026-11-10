import { router } from '@inertiajs/react';
import { Check, Copy, Dices } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { FormField } from '@/components/form-field';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useClipboard } from '@/hooks/use-clipboard';

const UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
const LOWER = 'abcdefghijkmnopqrstuvwxyz';
const DIGITS = '23456789';
const SYMBOLS = '!@#$%&*?';

function pick(set: string): string {
    const values = new Uint32Array(1);
    crypto.getRandomValues(values);

    return set[values[0] % set.length];
}

/**
 * A random password that passes the strictest rule the app has (12+ characters with upper and lower case, a number
 * and a symbol), without look-alike characters such as 0/O and 1/l so it can be read out over the phone.
 */
export function generatePassword(length = 14): string {
    const all = UPPER + LOWER + DIGITS + SYMBOLS;
    const chars = [
        pick(UPPER),
        pick(LOWER),
        pick(DIGITS),
        pick(SYMBOLS),
        ...Array.from({ length: length - 4 }, () => pick(all)),
    ];

    for (let i = chars.length - 1; i > 0; i--) {
        const values = new Uint32Array(1);
        crypto.getRandomValues(values);
        const j = values[0] % (i + 1);
        [chars[i], chars[j]] = [chars[j], chars[i]];
    }

    return chars.join('');
}

/**
 * Lets an admin choose or generate a password for someone else's account and sends it to `url`. The person is asked to
 * change it at their next sign-in, so the admin only hands it over once.
 */
export function SetPasswordDialog({
    open,
    onOpenChange,
    url,
    title = 'Atur password',
    description,
    account,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    url: string;
    title?: string;
    description?: string;
    /** Who the password is for, shown so the admin knows what to hand over. */
    account: string;
}) {
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string | undefined>();
    const [processing, setProcessing] = useState(false);
    const [copiedText, copy] = useClipboard();

    function close(next: boolean) {
        if (!next) {
            setPassword('');
            setError(undefined);
        }

        onOpenChange(next);
    }

    async function copyPassword() {
        if (await copy(password)) {
            toast.success('Password sudah disalin');
        } else {
            toast.error('Gagal menyalin, salin manual ya');
        }
    }

    function submit() {
        router.post(
            url,
            { password },
            {
                preserveScroll: true,
                onStart: () => {
                    setProcessing(true);
                    setError(undefined);
                },
                onSuccess: () => close(false),
                onError: (errors) => setError(errors.password),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <Dialog open={open} onOpenChange={close}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>
                        {description ??
                            `Password baru untuk ${account}. Salin lalu sampaikan ke orangnya; ia diminta menggantinya saat masuk berikutnya. Semua perangkat yang sedang masuk akan keluar.`}
                    </DialogDescription>
                </DialogHeader>

                <FormField
                    label="Password baru"
                    error={error}
                    hint="Ketik sendiri, atau buat otomatis lalu salin sebelum menyimpan."
                >
                    {(control) => (
                        <PasswordInput
                            value={password}
                            onChange={(event) =>
                                setPassword(event.target.value)
                            }
                            autoComplete="new-password"
                            {...control}
                        />
                    )}
                </FormField>

                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setPassword(generatePassword())}
                    >
                        <Dices />
                        Buat otomatis
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={password === ''}
                        onClick={copyPassword}
                    >
                        {copiedText === password && password !== '' ? (
                            <Check />
                        ) : (
                            <Copy />
                        )}
                        Salin
                    </Button>
                </div>

                <DialogFooter className="gap-2">
                    <Button
                        variant="outline"
                        disabled={processing}
                        onClick={() => close(false)}
                    >
                        Batal
                    </Button>
                    <Button
                        disabled={processing || password === ''}
                        onClick={submit}
                    >
                        {processing && <Spinner />}
                        Simpan password
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
