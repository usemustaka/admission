import { router, usePage } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { toastFirstError } from '@/hooks/use-flash-toast';
import { levelsOf } from '@/lib/exam-scale';
import { scores as scoresRoute } from '@/routes/admin/registrants';
import type { ExamResultData } from '@/types/registrant';

const ANNOUNCED_STATUSES = ['announced', 'placed'];

/**
 * One score per exam aspect. Each is checked against the KKM of its aspect as you type; the registration
 * counts as passed only when every aspect has a score at or above its KKM. Enter saves, and only the aspects
 * that changed are sent, so the others keep the score they have. Once the result was announced, changing a
 * score needs a tick that says so. `status` is read from the page's `registrant` when it is not given.
 */
export function ExamScoresForm({
    registrantId,
    aspects,
    initial,
    canEdit,
    status,
}: {
    registrantId: number;
    aspects: ExamResultData['aspects'];
    initial: (number | null)[];
    canEdit: boolean;
    status?: string;
}) {
    const pageStatus = usePage<{ registrant?: { status?: string } }>().props
        .registrant?.status;
    const announced = ANNOUNCED_STATUSES.includes(status ?? pageStatus ?? '');

    const [values, setValues] = useState<string[]>(
        initial.map((score) => (score === null ? '' : String(score))),
    );
    const [processing, setProcessing] = useState(false);
    const [confirmAnnounced, setConfirmAnnounced] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const scored = aspects.map((aspect, index) => {
        const raw = values[index] ?? '';
        const score = raw === '' ? null : Number(raw);

        return {
            ...aspect,
            score,
            passed: score === null ? null : score >= aspect.passing_score,
        };
    });
    const complete = scored.every((aspect) => aspect.score !== null);
    const passed = complete && scored.every((aspect) => aspect.passed);

    const changed = aspects.reduce<Record<number, number | null>>(
        (changes, _aspect, index) => {
            const current = values[index] ?? '';
            const before = initial[index] ?? null;

            if (current !== (before === null ? '' : String(before))) {
                changes[index] = current === '' ? null : Number(current);
            }

            return changes;
        },
        {},
    );
    const dirty = Object.keys(changed).length > 0;
    const askConfirmation =
        announced && (dirty || Boolean(errors.confirm_after_announce));

    function save(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!canEdit || processing || !dirty) {
            return;
        }

        setProcessing(true);
        router.put(
            scoresRoute(registrantId).url,
            {
                scores: changed,
                confirm_after_announce: confirmAnnounced,
            },
            {
                preserveScroll: true,
                onError: (errors) => {
                    setErrors(errors);
                    toastFirstError(
                        errors,
                        (key) =>
                            /^scores\.\d+$/.test(key) ||
                            key === 'confirm_after_announce',
                    );
                },
                onSuccess: () => setErrors({}),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <form onSubmit={save} className="grid gap-5">
            <div className="grid gap-4 sm:grid-cols-2">
                {scored.map((aspect, index) => (
                    <div key={index} className="grid gap-2">
                        <Label htmlFor={`score-${index}`}>
                            {aspect.name}
                            <span className="font-normal text-muted-foreground">
                                {' '}
                                (KKM {aspect.passing_label})
                            </span>
                        </Label>
                        <div className="flex items-center gap-3">
                            {levelsOf(aspect.scale).length > 0 ? (
                                <select
                                    id={`score-${index}`}
                                    disabled={!canEdit}
                                    value={values[index] ?? ''}
                                    aria-invalid={Boolean(
                                        errors[`scores.${index}`],
                                    )}
                                    onChange={(event) =>
                                        setValues((current) =>
                                            current.map((value, i) =>
                                                i === index
                                                    ? event.target.value
                                                    : value,
                                            ),
                                        )
                                    }
                                    className="h-10 w-40 rounded-md border border-input bg-background px-3"
                                >
                                    <option value="">Pilih</option>
                                    {levelsOf(aspect.scale).map((level) => (
                                        <option
                                            key={level.value}
                                            value={String(level.value)}
                                        >
                                            {level.label}
                                        </option>
                                    ))}
                                </select>
                            ) : (
                                <Input
                                    id={`score-${index}`}
                                    type="number"
                                    min={0}
                                    max={100}
                                    inputMode="numeric"
                                    disabled={!canEdit}
                                    value={values[index] ?? ''}
                                    aria-invalid={Boolean(
                                        errors[`scores.${index}`],
                                    )}
                                    onChange={(event) =>
                                        setValues((current) =>
                                            current.map((value, i) =>
                                                i === index
                                                    ? event.target.value
                                                    : value,
                                            ),
                                        )
                                    }
                                    className="max-w-32"
                                />
                            )}
                            {aspect.passed !== null && (
                                <Badge
                                    variant={
                                        aspect.passed ? 'success' : 'danger'
                                    }
                                    className="gap-1"
                                >
                                    {aspect.passed ? <Check /> : <X />}
                                    {aspect.passed ? 'Tuntas' : 'Belum tuntas'}
                                </Badge>
                            )}
                        </div>
                        {errors[`scores.${index}`] && (
                            <p className="text-sm font-medium text-destructive-foreground">
                                {errors[`scores.${index}`]}
                            </p>
                        )}
                    </div>
                ))}
            </div>

            {canEdit && askConfirmation && (
                <div className="grid gap-1.5">
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="confirm-after-announce"
                            checked={confirmAnnounced}
                            onCheckedChange={(checked) =>
                                setConfirmAnnounced(checked === true)
                            }
                            aria-invalid={Boolean(
                                errors.confirm_after_announce,
                            )}
                        />
                        <Label
                            htmlFor="confirm-after-announce"
                            className="text-sm font-medium"
                        >
                            Saya sadar nilai ini sudah diumumkan
                        </Label>
                    </div>
                    {errors.confirm_after_announce && (
                        <p className="text-sm font-medium text-destructive-foreground">
                            {errors.confirm_after_announce}
                        </p>
                    )}
                </div>
            )}

            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm">
                    {complete ? (
                        <span
                            className={
                                passed
                                    ? 'font-semibold text-success'
                                    : 'font-semibold text-destructive-foreground'
                            }
                        >
                            {passed
                                ? 'Tuntas di semua aspek'
                                : 'Belum tuntas: ada aspek di bawah KKM'}
                        </span>
                    ) : (
                        <span className="text-muted-foreground">
                            Hasil akhir keluar setelah semua nilai terisi.
                        </span>
                    )}
                </p>
                {canEdit && (
                    <Button type="submit" disabled={processing || !dirty}>
                        {processing && <Spinner />}
                        Simpan nilai
                    </Button>
                )}
            </div>
        </form>
    );
}
