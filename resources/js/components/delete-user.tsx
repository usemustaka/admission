import { Form } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { FormField } from '@/components/form-field';
import PasswordInput from '@/components/password-input';
import { SectionCard } from '@/components/section-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

export default function DeleteUser() {
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <SectionCard
            title="Hapus akun"
            description="Hapus akunmu beserta semua datanya."
        >
            <div className="grid gap-4">
                <Alert variant="destructive">
                    <CircleAlert />
                    <AlertTitle>Hati-hati</AlertTitle>
                    <AlertDescription>
                        Akun yang sudah dihapus tidak bisa dikembalikan.
                    </AlertDescription>
                </Alert>

                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            variant="destructive"
                            size="lg"
                            className="w-full @2xl:w-fit"
                            data-test="delete-user-button"
                        >
                            Hapus akun
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>Yakin mau menghapus akunmu?</DialogTitle>
                        <DialogDescription>
                            Setelah dihapus, akun dan semua datanya hilang
                            selamanya. Masukkan kata sandimu untuk memastikan
                            ini memang kamu.
                        </DialogDescription>

                        <Form
                            {...ProfileController.destroy.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            onError={() => passwordInput.current?.focus()}
                            resetOnSuccess
                            className="grid gap-6"
                        >
                            {({ resetAndClearErrors, processing, errors }) => (
                                <>
                                    <FormField
                                        label="Kata sandi"
                                        error={errors.password}
                                    >
                                        {(control) => (
                                            <PasswordInput
                                                name="password"
                                                ref={passwordInput}
                                                autoComplete="current-password"
                                                {...control}
                                            />
                                        )}
                                    </FormField>

                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                variant="secondary"
                                                size="lg"
                                                onClick={() =>
                                                    resetAndClearErrors()
                                                }
                                            >
                                                Batal
                                            </Button>
                                        </DialogClose>

                                        <Button
                                            variant="destructive"
                                            size="lg"
                                            disabled={processing}
                                            asChild
                                        >
                                            <button
                                                type="submit"
                                                data-test="confirm-delete-user-button"
                                            >
                                                {processing && <Spinner />}
                                                Hapus akun
                                            </button>
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </SectionCard>
    );
}
