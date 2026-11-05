import { Form } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { SectionCard } from '@/components/section-card';
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import { disable, enable } from '@/routes/two-factor';

export type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

export default function ManageTwoFactor(props: Props) {
    const requiresConfirmation = props.requiresConfirmation ?? false;
    const twoFactorEnabled = props.twoFactorEnabled ?? false;

    const {
        qrCodeSvg,
        hasSetupData,
        manualSetupKey,
        clearSetupData,
        clearTwoFactorAuthData,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        errors,
    } = useTwoFactorAuth();
    const [showSetupModal, setShowSetupModal] = useState<boolean>(false);
    const prevTwoFactorEnabled = useRef(twoFactorEnabled);

    useEffect(() => {
        if (prevTwoFactorEnabled.current && !twoFactorEnabled) {
            clearTwoFactorAuthData();
        }

        prevTwoFactorEnabled.current = twoFactorEnabled;
    }, [twoFactorEnabled, clearTwoFactorAuthData]);

    if (!(props.canManageTwoFactor ?? false)) {
        return null;
    }

    return (
        <>
            <SectionCard
                title="Verifikasi dua langkah (2FA)"
                description="Tambah lapisan keamanan supaya akunmu lebih sulit dibobol."
            >
                {twoFactorEnabled ? (
                    <div className="grid gap-5">
                        <p className="text-[15px] text-muted-foreground">
                            Saat masuk, kamu akan diminta memasukkan kode acak
                            dari aplikasi authenticator di HP-mu. Status:{' '}
                            <span className="font-semibold text-foreground">
                                aktif
                            </span>
                            .
                        </p>

                        <Form {...disable.form()}>
                            {({ processing }) => (
                                <Button
                                    variant="destructive"
                                    size="lg"
                                    className="w-full @2xl:w-fit"
                                    type="submit"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Matikan 2FA
                                </Button>
                            )}
                        </Form>

                        <TwoFactorRecoveryCodes
                            recoveryCodesList={recoveryCodesList}
                            fetchRecoveryCodes={fetchRecoveryCodes}
                            errors={errors}
                        />
                    </div>
                ) : (
                    <div className="grid gap-5">
                        <p className="text-[15px] text-muted-foreground">
                            Kalau diaktifkan, kamu akan diminta kode acak dari
                            aplikasi authenticator di HP-mu setiap kali masuk.
                            Status:{' '}
                            <span className="font-semibold text-foreground">
                                belum aktif
                            </span>
                            .
                        </p>

                        {hasSetupData ? (
                            <Button
                                size="lg"
                                className="w-full @2xl:w-fit"
                                onClick={() => setShowSetupModal(true)}
                            >
                                <ShieldCheck />
                                Lanjutkan pengaturan
                            </Button>
                        ) : (
                            <Form
                                {...enable.form()}
                                onSuccess={() => setShowSetupModal(true)}
                            >
                                {({ processing }) => (
                                    <Button
                                        size="lg"
                                        className="w-full @2xl:w-fit"
                                        type="submit"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        Aktifkan 2FA
                                    </Button>
                                )}
                            </Form>
                        )}
                    </div>
                )}
            </SectionCard>

            <TwoFactorSetupModal
                isOpen={showSetupModal}
                onClose={() => setShowSetupModal(false)}
                requiresConfirmation={requiresConfirmation}
                twoFactorEnabled={twoFactorEnabled}
                qrCodeSvg={qrCodeSvg}
                manualSetupKey={manualSetupKey}
                clearSetupData={clearSetupData}
                fetchSetupData={fetchSetupData}
                errors={errors}
            />
        </>
    );
}
