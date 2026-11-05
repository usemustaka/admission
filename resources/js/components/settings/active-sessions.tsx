import { Form } from '@inertiajs/react';
import { Laptop, Smartphone } from 'lucide-react';
import { useRef, useState } from 'react';
import SessionController from '@/actions/App/Http/Controllers/Settings/SessionController';
import { FormField } from '@/components/form-field';
import PasswordInput from '@/components/password-input';
import { SectionCard } from '@/components/section-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { formatRelativeTime } from '@/lib/format';

export type ActiveSession = {
    handle: string;
    browser: string;
    platform: string;
    ip_address: string | null;
    last_active_at: string;
    is_current: boolean;
};

const mobilePlatforms = ['iPhone', 'iPad', 'Android'];

export default function ActiveSessions({
    sessions,
}: {
    sessions: ActiveSession[] | null;
}) {
    const [confirmOpen, setConfirmOpen] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);

    if (sessions === null) {
        return null;
    }

    const hasOthers = sessions.some((session) => !session.is_current);

    return (
        <SectionCard
            title="Perangkat yang masuk"
            description="Perangkat yang sedang masuk ke akunmu. Keluarkan yang tidak kamu kenali."
        >
            <div className="grid gap-5">
                <ul className="grid divide-y divide-line">
                    {sessions.map((session) => {
                        const Icon = mobilePlatforms.includes(session.platform)
                            ? Smartphone
                            : Laptop;

                        return (
                            <li
                                key={session.handle}
                                className="flex items-center gap-4 py-3 first:pt-0 last:pb-0"
                            >
                                <Icon className="size-6 shrink-0 text-muted-foreground" />
                                <div className="grid min-w-0 flex-1 gap-0.5 text-[15px]">
                                    <p className="flex flex-wrap items-center gap-2 font-semibold">
                                        {session.browser} di {session.platform}
                                        {session.is_current && (
                                            <Badge variant="success">
                                                Perangkat ini
                                            </Badge>
                                        )}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {session.ip_address ??
                                            'IP tidak diketahui'}
                                        {' · '}
                                        {session.is_current
                                            ? 'sedang aktif'
                                            : `aktif ${formatRelativeTime(session.last_active_at)}`}
                                    </p>
                                </div>
                                {!session.is_current && (
                                    <Form
                                        {...SessionController.destroy.form({
                                            session: session.handle,
                                        })}
                                        options={{ preserveScroll: true }}
                                    >
                                        {({ processing }) => (
                                            <Button
                                                variant="outline"
                                                disabled={processing}
                                                data-test="logout-session-button"
                                            >
                                                {processing && <Spinner />}
                                                Keluar
                                            </Button>
                                        )}
                                    </Form>
                                )}
                            </li>
                        );
                    })}
                </ul>

                {hasOthers && (
                    <>
                        <Button
                            variant="outline"
                            size="lg"
                            className="w-full @2xl:w-fit"
                            onClick={() => setConfirmOpen(true)}
                            data-test="logout-other-sessions-button"
                        >
                            Keluar dari perangkat lain
                        </Button>

                        <Dialog
                            open={confirmOpen}
                            onOpenChange={setConfirmOpen}
                        >
                            <DialogContent>
                                <DialogTitle>
                                    Keluar dari semua perangkat lain?
                                </DialogTitle>
                                <DialogDescription>
                                    Semua perangkat selain ini akan keluar dan
                                    harus masuk lagi. Masukkan kata sandimu
                                    untuk memastikan ini memang kamu.
                                </DialogDescription>

                                <Form
                                    {...SessionController.destroyOthers.form()}
                                    options={{ preserveScroll: true }}
                                    onError={() =>
                                        passwordInput.current?.focus()
                                    }
                                    onSuccess={() => setConfirmOpen(false)}
                                    resetOnSuccess
                                    className="grid gap-6"
                                >
                                    {({
                                        resetAndClearErrors,
                                        processing,
                                        errors,
                                    }) => (
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
                                                    size="lg"
                                                    disabled={processing}
                                                    data-test="confirm-logout-other-sessions-button"
                                                >
                                                    {processing && <Spinner />}
                                                    Keluarkan
                                                </Button>
                                            </DialogFooter>
                                        </>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    </>
                )}
            </div>
        </SectionCard>
    );
}
