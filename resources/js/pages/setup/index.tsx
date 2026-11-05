import { Head, useForm } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { CSSProperties, FormEvent } from 'react';
import { SetupReview } from '@/components/setup/setup-review';
import {
    buildPayload,
    lastStep,
    setupSteps,
    stepOfError,
    validateStep,
} from '@/components/setup/setup-form';
import type {
    SetSection,
    SetupCheck,
    SetupContext,
    SetupDefaults,
    SetupErrors,
    SetupExisting,
    SetupFormData,
} from '@/components/setup/setup-form';
import { SetupProgress } from '@/components/setup/setup-progress';
import { StepAdmin } from '@/components/setup/step-admin';
import { StepContact } from '@/components/setup/step-contact';
import { StepIdentity } from '@/components/setup/step-identity';
import { StepOrganizations } from '@/components/setup/step-organizations';
import { StepWelcome } from '@/components/setup/step-welcome';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { accentVariables, isHexColor } from '@/lib/accent';
import { store } from '@/routes/setup';
import type { CategoryOption } from '@/types/academic';

type Props = {
    requiresToken: boolean;
    checks: SetupCheck[];
    existing: SetupExisting;
    categories: CategoryOption[];
    defaults: SetupDefaults;
};

const focusable =
    'input:not([type="hidden"]):not([tabindex="-1"]):not([disabled]), textarea:not([disabled]), select:not([disabled]), button[role="combobox"]';

/** Names the wizard steps that still hold errors, e.g. "2 (Identitas) dan 4 (Lembaga dan tahun ajaran)". */
function describeSteps(steps: number[]): string {
    const parts = steps.map(
        (index) => `${index + 1} (${setupSteps[index].title})`,
    );

    return parts.length > 1
        ? `${parts.slice(0, -1).join(', ')} dan ${parts[parts.length - 1]}`
        : (parts[0] ?? '');
}

export default function SetupIndex({
    requiresToken,
    checks,
    existing,
    categories,
    defaults,
}: Props) {
    const hasExistingOrganizations = existing.organizations.length > 0;
    const hasExistingYears = existing.years.length > 0;

    const form = useForm<SetupFormData>({
        setup_token: '',
        brand: {
            name: defaults.app_name,
            accent_color: defaults.accent_color,
        },
        logo: null,
        contact: { phone: '', whatsapp: '', email: '', address: '' },
        payment: { bank_name: '', account_number: '', account_holder: '' },
        admin: {
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
        },
        organizations: hasExistingOrganizations
            ? []
            : [{ name: '', category: '' }],
        year: {
            name: existing.years.includes(defaults.year.name)
                ? ''
                : defaults.year.name,
            starts_on: defaults.year.starts_on,
            ends_on: defaults.year.ends_on,
            with_periods: true,
        },
        wave: { name: '', starts_on: '', ends_on: '' },
    });
    const { data } = form;

    const [step, setStep] = useState(0);
    const [reached, setReached] = useState(0);
    const [yearOpen, setYearOpen] = useState(!hasExistingYears);
    const [waveOpen, setWaveOpen] = useState(false);
    const [clientErrors, setClientErrors] = useState<SetupErrors>({});
    const [focusTick, setFocusTick] = useState(0);
    const [logoUrl, setLogoUrl] = useState<string | null>(null);

    const bodyRef = useRef<HTMLDivElement>(null);
    const headingRef = useRef<HTMLHeadingElement>(null);
    const mounted = useRef(false);

    const errors: SetupErrors = {
        ...(form.errors as SetupErrors),
        ...clientErrors,
    };
    const errorKeys = Object.keys(errors).filter((key) => errors[key]);
    const errorSteps = new Set<number>();
    const unmappedMessages: string[] = [];

    errorKeys.forEach((key) => {
        const owner = stepOfError(key);

        if (owner === null) {
            unmappedMessages.push(errors[key] as string);
        } else {
            errorSteps.add(owner);
        }
    });

    const context: SetupContext = {
        requiresToken,
        hasExistingOrganizations,
        yearOpen,
        waveOpen,
    };
    const meta = setupSteps[step];
    const accent = data.brand.accent_color;
    const themeStyle = (
        isHexColor(accent) ? accentVariables(accent) : {}
    ) as CSSProperties;

    useEffect(() => {
        if (!data.logo) {
            setLogoUrl(null);

            return;
        }

        const url = URL.createObjectURL(data.logo);
        setLogoUrl(url);

        return () => URL.revokeObjectURL(url);
    }, [data.logo]);

    useEffect(() => {
        if (!mounted.current) {
            mounted.current = true;

            return;
        }

        const frame = requestAnimationFrame(() => {
            const body = bodyRef.current;
            const target =
                body?.querySelector<HTMLElement>('[aria-invalid="true"]') ??
                body?.querySelector<HTMLElement>(focusable) ??
                headingRef.current;

            target?.focus();
        });

        return () => cancelAnimationFrame(frame);
    }, [step, focusTick]);

    function forget(match: (key: string) => boolean) {
        setClientErrors((current) => {
            const keys = Object.keys(current).filter(match);

            if (keys.length === 0) {
                return current;
            }

            const next = { ...current };
            keys.forEach((key) => delete next[key]);

            return next;
        });

        const serverKeys = Object.keys(form.errors).filter(match);

        if (serverKeys.length > 0) {
            form.clearErrors(...(serverKeys as never[]));
        }
    }

    const setSection: SetSection = (section, values) => {
        form.setData(
            (current) =>
                ({
                    ...current,
                    [section]: { ...current[section], ...values },
                }) as SetupFormData,
        );

        const fields = Object.keys(values);

        forget(
            (key) =>
                fields.some((field) => key === `${section}.${field}`) ||
                (section === 'admin' &&
                    fields.some((field) => field.startsWith('password')) &&
                    key.startsWith('admin.password')) ||
                (section === 'year' && key === 'year') ||
                (section === 'wave' && key === 'wave'),
        );
    };

    function changeOrganizations(
        rows: SetupFormData['organizations'],
        changedIndex?: number,
    ) {
        form.setData('organizations', rows);
        forget((key) =>
            changedIndex === undefined
                ? key.startsWith('organizations')
                : key === 'organizations' ||
                  key.startsWith(`organizations.${changedIndex}.`),
        );
    }

    function goTo(index: number) {
        setClientErrors({});
        setStep(index);
    }

    function next() {
        const found = validateStep(step, data, context);

        if (Object.keys(found).length > 0) {
            setClientErrors(found);
            setFocusTick((tick) => tick + 1);

            return;
        }

        if (step === 3) {
            const filled = data.organizations.filter(
                (row) => row.name.trim() !== '' || row.category.trim() !== '',
            );

            if (filled.length !== data.organizations.length) {
                form.setData('organizations', filled);
            }
        }

        setReached((current) => Math.max(current, step + 1));
        goTo(step + 1);
    }

    function submit() {
        form.transform((current) => buildPayload(current, context) as never);
        form.post(store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onError: (serverErrors) => {
                const owners = Object.keys(serverErrors)
                    .map(stepOfError)
                    .filter((owner): owner is number => owner !== null);

                if (
                    Object.keys(serverErrors).some((key) =>
                        key.startsWith('year'),
                    )
                ) {
                    setYearOpen(true);
                }

                if (
                    Object.keys(serverErrors).some((key) =>
                        key.startsWith('wave'),
                    )
                ) {
                    setWaveOpen(true);
                }

                if (owners.length > 0) {
                    const first = Math.min(...owners);
                    setReached(lastStep);
                    goTo(first);
                }

                setFocusTick((tick) => tick + 1);
            },
        });
    }

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        if (step < lastStep) {
            next();

            return;
        }

        submit();
    }

    const clientProblem = Object.keys(clientErrors).length > 0;
    const stepList = [...errorSteps].sort((a, b) => a - b);

    return (
        <>
            <Head title="Pengaturan awal" />
            <div
                style={themeStyle}
                className="grid min-h-svh place-items-start bg-neutral-50 px-4 py-8 sm:place-items-center sm:py-12 dark:bg-background"
            >
                <div className="w-full max-w-[640px]">
                    <div className="mb-6 flex items-center justify-center gap-2.5">
                        {logoUrl ? (
                            <img
                                src={logoUrl}
                                alt=""
                                className="h-8 w-auto max-w-40 object-contain"
                            />
                        ) : (
                            <span
                                aria-hidden="true"
                                className="grid size-8 place-items-center rounded-[9px] bg-primary text-lg font-extrabold text-primary-foreground"
                            >
                                {(
                                    data.brand.name.trim()[0] ?? '?'
                                ).toUpperCase()}
                            </span>
                        )}
                        <span className="truncate text-lg font-extrabold tracking-tight">
                            {data.brand.name.trim() || 'Pengaturan awal'}
                        </span>
                    </div>

                    <form
                        onSubmit={handleSubmit}
                        noValidate
                        className="overflow-hidden rounded-3xl border border-line bg-card"
                    >
                        <div className="grid gap-6 p-5 sm:p-8">
                            <SetupProgress
                                labels={setupSteps.map((item) => item.label)}
                                current={step}
                                reached={reached}
                                errorSteps={errorSteps}
                                onSelect={goTo}
                            />

                            <p className="sr-only" aria-live="polite">
                                Langkah {step + 1} dari {setupSteps.length}:{' '}
                                {meta.title}
                            </p>

                            <div className="grid gap-2">
                                <h1
                                    ref={headingRef}
                                    tabIndex={-1}
                                    className="text-[26px] leading-tight font-semibold tracking-tight outline-none"
                                >
                                    {meta.title}
                                </h1>
                                <p className="text-[15px] text-muted-foreground">
                                    {meta.description}
                                </p>
                            </div>

                            {clientProblem && (
                                <Alert variant="destructive">
                                    <CircleAlert />
                                    <AlertTitle>Belum lengkap</AlertTitle>
                                    <AlertDescription>
                                        <p>
                                            Lengkapi isian yang bertanda galat
                                            di bawah, lalu lanjut.
                                        </p>
                                    </AlertDescription>
                                </Alert>
                            )}

                            {!clientProblem &&
                                (stepList.length > 0 ||
                                    unmappedMessages.length > 0) && (
                                    <Alert variant="destructive">
                                        <CircleAlert />
                                        <AlertTitle>
                                            Setup belum bisa disimpan
                                        </AlertTitle>
                                        <AlertDescription>
                                            {stepList.length > 0 && (
                                                <p>
                                                    Ada isian yang perlu
                                                    diperbaiki di langkah{' '}
                                                    {describeSteps(stepList)}.
                                                </p>
                                            )}
                                            {unmappedMessages.map((message) => (
                                                <p key={message}>{message}</p>
                                            ))}
                                        </AlertDescription>
                                    </Alert>
                                )}

                            <div ref={bodyRef} key={step} className="anim-fade">
                                {step === 0 && (
                                    <StepWelcome
                                        checks={checks}
                                        requiresToken={requiresToken}
                                        token={data.setup_token}
                                        tokenError={errors.setup_token}
                                        onTokenChange={(value) => {
                                            form.setData('setup_token', value);
                                            forget(
                                                (key) => key === 'setup_token',
                                            );
                                        }}
                                    />
                                )}
                                {step === 1 && (
                                    <StepIdentity
                                        data={data}
                                        errors={errors}
                                        setSection={setSection}
                                        logoUrl={logoUrl}
                                        defaultAccent={defaults.accent_color}
                                        onLogoChange={(file) => {
                                            form.setData('logo', file);
                                            forget((key) => key === 'logo');
                                        }}
                                    />
                                )}
                                {step === 2 && (
                                    <StepAdmin
                                        data={data}
                                        errors={errors}
                                        setSection={setSection}
                                    />
                                )}
                                {step === 3 && (
                                    <StepOrganizations
                                        data={data}
                                        errors={errors}
                                        existing={existing}
                                        categories={categories}
                                        setSection={setSection}
                                        onOrganizationsChange={
                                            changeOrganizations
                                        }
                                        yearOpen={yearOpen}
                                        onYearOpenChange={setYearOpen}
                                        waveOpen={waveOpen}
                                        onWaveOpenChange={setWaveOpen}
                                    />
                                )}
                                {step === 4 && (
                                    <div className="grid gap-8">
                                        <StepContact
                                            data={data}
                                            errors={errors}
                                            setSection={setSection}
                                        />
                                        <SetupReview
                                            data={data}
                                            existing={existing}
                                            yearOpen={yearOpen}
                                            waveOpen={waveOpen}
                                        />
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="flex items-center justify-between gap-3 border-t border-line px-5 py-4 sm:px-8">
                            {step > 0 ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="lg"
                                    disabled={form.processing}
                                    onClick={() => goTo(step - 1)}
                                >
                                    Kembali
                                </Button>
                            ) : (
                                <span />
                            )}
                            <Button
                                key={step === lastStep ? 'finish' : 'next'}
                                type="submit"
                                size="lg"
                                className="flex-1 sm:min-w-40 sm:flex-none"
                                disabled={form.processing}
                                data-test="setup-submit-button"
                            >
                                {form.processing && <Spinner />}
                                {step === lastStep
                                    ? 'Selesaikan setup'
                                    : 'Lanjut'}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}
