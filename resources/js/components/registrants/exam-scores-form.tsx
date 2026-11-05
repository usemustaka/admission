import { router } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { toastFirstError } from '@/hooks/use-flash-toast';
import { scores as scoresRoute } from '@/routes/admin/registrants';
import type { ExamResultData } from '@/types/registrant';

/**
 * One score per exam aspect. Each is checked against the KKM of its aspect as you type; the registration
 * counts as passed only when every aspect has a score at or above its KKM.
 */
export function ExamScoresForm({
    registrantId,
    aspects,
    initial,
    canEdit,
}: {
    registrantId: number;
    aspects: ExamResultData['aspects'];
    initial: (number | null)[];
    canEdit: boolean;
}) {
    const [values, setValues] = useState<string[]>(
        initial.map((score) => (score === null ? '' : String(score))),
    );
    const [processing, setProcessing] = useState(false);
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

    function save() {
        setProcessing(true);
        router.put(
            scoresRoute(registrantId).url,
            {
                scores: values.map((value) =>
                    value === '' ? null : Number(value),
                ),
            },
            {
                preserveScroll: true,
                onError: (errors) => {
                    setErrors(errors);
                    toastFirstError(errors, (key) => /^scores\.\d+$/.test(key));
                },
                onSuccess: () => setErrors({}),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <div className="grid gap-5">
            <div className="grid gap-4 sm:grid-cols-2">
                {scored.map((aspect, index) => (
                    <div key={index} className="grid gap-2">
                        <Label htmlFor={`score-${index}`}>
                            {aspect.name}
                            <span className="font-normal text-muted-foreground">
                                {' '}
                                (KKM {aspect.passing_score})
                            </span>
                        </Label>
                        <div className="flex items-center gap-3">
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
                    <Button type="button" onClick={save} disabled={processing}>
                        {processing && <Spinner />}
                        Simpan nilai
                    </Button>
                )}
            </div>
        </div>
    );
}
