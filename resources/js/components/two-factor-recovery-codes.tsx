import { Form } from '@inertiajs/react';
import { Eye, EyeOff, LockKeyhole, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import AlertError from '@/components/alert-error';
import { Button } from '@/components/ui/button';
import { regenerateRecoveryCodes } from '@/routes/two-factor';

type Props = {
    recoveryCodesList: string[];
    fetchRecoveryCodes: () => Promise<void>;
    errors: string[];
};

export default function TwoFactorRecoveryCodes({
    recoveryCodesList,
    fetchRecoveryCodes,
    errors,
}: Props) {
    const [codesAreVisible, setCodesAreVisible] = useState<boolean>(false);
    const codesSectionRef = useRef<HTMLDivElement | null>(null);
    const canRegenerateCodes = recoveryCodesList.length > 0 && codesAreVisible;

    const toggleCodesVisibility = useCallback(async () => {
        if (!codesAreVisible && !recoveryCodesList.length) {
            await fetchRecoveryCodes();
        }

        setCodesAreVisible(!codesAreVisible);

        if (!codesAreVisible) {
            setTimeout(() => {
                codesSectionRef.current?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                });
            });
        }
    }, [codesAreVisible, recoveryCodesList.length, fetchRecoveryCodes]);

    useEffect(() => {
        if (!recoveryCodesList.length) {
            void fetchRecoveryCodes();
        }
    }, [recoveryCodesList.length, fetchRecoveryCodes]);

    const RecoveryCodeIconComponent = codesAreVisible ? EyeOff : Eye;

    return (
        <div className="grid gap-4 border-t border-line pt-5">
            <div className="grid gap-1">
                <h3 className="flex items-center gap-2 text-base font-semibold">
                    <LockKeyhole className="size-4" aria-hidden="true" />
                    Kode pemulihan 2FA
                </h3>
                <p className="text-[15px] text-muted-foreground">
                    Kode pemulihan dipakai kalau HP-mu hilang. Simpan di tempat
                    yang aman, misalnya aplikasi pengelola kata sandi.
                </p>
            </div>

            <div className="flex flex-col gap-3 select-none @2xl:flex-row @2xl:items-center">
                <Button
                    onClick={toggleCodesVisibility}
                    size="lg"
                    className="w-full @2xl:w-fit"
                    aria-expanded={codesAreVisible}
                    aria-controls="recovery-codes-section"
                >
                    <RecoveryCodeIconComponent
                        className="size-4"
                        aria-hidden="true"
                    />
                    {codesAreVisible
                        ? 'Sembunyikan kode pemulihan'
                        : 'Lihat kode pemulihan'}
                </Button>

                {canRegenerateCodes && (
                    <Form
                        {...regenerateRecoveryCodes.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={fetchRecoveryCodes}
                    >
                        {({ processing }) => (
                            <Button
                                variant="secondary"
                                size="lg"
                                className="w-full @2xl:w-fit"
                                type="submit"
                                disabled={processing}
                                aria-describedby="regenerate-warning"
                            >
                                <RefreshCw /> Buat kode baru
                            </Button>
                        )}
                    </Form>
                )}
            </div>

            <div
                id="recovery-codes-section"
                className={`relative overflow-hidden transition-all duration-300 ${codesAreVisible ? 'h-auto opacity-100' : 'h-0 opacity-0'}`}
                aria-hidden={!codesAreVisible}
            >
                <div className="space-y-3">
                    {errors?.length ? (
                        <AlertError errors={errors} />
                    ) : (
                        <>
                            <div
                                ref={codesSectionRef}
                                className="grid gap-1.5 rounded-xl bg-muted p-4 text-[15px] tabular-nums"
                                role="list"
                                aria-label="Kode pemulihan"
                            >
                                {recoveryCodesList.length ? (
                                    recoveryCodesList.map((code, index) => (
                                        <div
                                            key={index}
                                            role="listitem"
                                            className="select-text"
                                        >
                                            {code}
                                        </div>
                                    ))
                                ) : (
                                    <div
                                        className="space-y-2"
                                        aria-label="Memuat kode pemulihan"
                                    >
                                        {Array.from(
                                            { length: 8 },
                                            (_, index) => (
                                                <div
                                                    key={index}
                                                    className="h-4 animate-pulse rounded bg-muted-foreground/20"
                                                    aria-hidden="true"
                                                />
                                            ),
                                        )}
                                    </div>
                                )}
                            </div>

                            <div className="text-sm text-muted-foreground select-none">
                                <p id="regenerate-warning">
                                    Setiap kode hanya bisa dipakai sekali dan
                                    hangus setelah dipakai. Kalau kodenya hampir
                                    habis, tekan{' '}
                                    <span className="font-bold">
                                        Buat kode baru
                                    </span>{' '}
                                    di atas.
                                </p>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}
