import { CircleAlert, CircleCheck } from 'lucide-react';
import { FormField } from '@/components/form-field';
import PasswordInput from '@/components/password-input';
import { SetupNote } from '@/components/setup/setup-note';
import { setupSteps } from '@/components/setup/setup-form';
import type { SetupCheck } from '@/components/setup/setup-form';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';

export function StepWelcome({
    checks,
    requiresToken,
    token,
    tokenError,
    onTokenChange,
}: {
    checks: SetupCheck[];
    requiresToken: boolean;
    token: string;
    tokenError?: string;
    onTokenChange: (value: string) => void;
}) {
    const problems = checks.filter((check) => !check.ok && !check.soft);

    return (
        <div className="grid gap-6">
            <div className="grid gap-3">
                <h2 className="text-[15px] font-semibold">
                    Yang akan kita atur
                </h2>
                <ol className="grid gap-2 text-[15px]">
                    {setupSteps.slice(1).map((step, index) => (
                        <li key={step.label} className="flex gap-3">
                            <span
                                aria-hidden="true"
                                className="grid size-6 shrink-0 place-items-center rounded-full bg-lime-100 text-xs font-bold text-lime-800 dark:bg-lime-400/15 dark:text-lime-300"
                            >
                                {index + 1}
                            </span>
                            <span>
                                <span className="font-semibold">
                                    {step.title}.
                                </span>{' '}
                                <span className="text-muted-foreground">
                                    {step.blurb}
                                </span>
                            </span>
                        </li>
                    ))}
                </ol>
            </div>

            {checks.length > 0 && (
                <div className="grid gap-3">
                    <h2 className="text-[15px] font-semibold">
                        Pemeriksaan awal
                    </h2>

                    {problems.length > 0 && (
                        <Alert variant="warning">
                            <CircleAlert />
                            <AlertTitle>Ada yang perlu dicek dulu</AlertTitle>
                            <AlertDescription>
                                <p>
                                    Setup tetap bisa dibuka, tapi bisa gagal
                                    disimpan. Perbaiki yang bertanda "Perlu
                                    dicek", lalu muat ulang halaman ini.
                                </p>
                            </AlertDescription>
                        </Alert>
                    )}

                    <ul className="grid divide-y divide-line rounded-2xl border border-line px-4">
                        {checks.map((check) => (
                            <li key={check.label} className="grid gap-1 py-3">
                                <div className="flex items-center justify-between gap-3">
                                    <span className="flex items-center gap-2.5 text-[15px] font-medium">
                                        {check.ok ? (
                                            <CircleCheck
                                                aria-hidden="true"
                                                className="size-5 shrink-0 text-emerald-600 dark:text-emerald-400"
                                            />
                                        ) : (
                                            <CircleAlert
                                                aria-hidden="true"
                                                className="size-5 shrink-0 text-amber-600 dark:text-amber-400"
                                            />
                                        )}
                                        {check.label}
                                    </span>
                                    <Badge
                                        variant={
                                            check.ok ? 'success' : 'warning'
                                        }
                                    >
                                        {check.ok ? 'Siap' : 'Perlu dicek'}
                                    </Badge>
                                </div>
                                {!check.ok && check.hint && (
                                    <p className="pl-[30px] text-sm text-muted-foreground">
                                        {check.hint}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {requiresToken && (
                <FormField
                    label="Kode setup"
                    required
                    error={tokenError}
                    hint="Ada di file .env, pada baris SETUP_TOKEN. Ini mencegah orang lain menyelesaikan setup lebih dulu."
                >
                    {(control) => (
                        <PasswordInput
                            {...control}
                            name="setup_token"
                            autoComplete="off"
                            value={token}
                            onChange={(event) =>
                                onTokenChange(event.target.value)
                            }
                        />
                    )}
                </FormField>
            )}

            {!requiresToken && (
                <SetupNote>
                    <p>
                        Siapkan nama sekolah atau yayasan, dan satu alamat email
                        untuk akun admin pertama.
                    </p>
                </SetupNote>
            )}
        </div>
    );
}
