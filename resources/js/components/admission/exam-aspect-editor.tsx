import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { examAspects } from '@/routes/admin/rulesets';
import type { ExamAspectRow } from '@/types/admission';

export function ExamAspectEditor({
    rulesetId,
    initial,
    canEdit,
}: {
    rulesetId: number;
    initial: ExamAspectRow[];
    canEdit: boolean;
}) {
    const form = useForm<{ aspects: ExamAspectRow[] }>({ aspects: initial });
    const errors = form.errors as Record<string, string>;

    function setAspects(aspects: ExamAspectRow[]) {
        form.setData('aspects', aspects);
    }

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(examAspects(rulesetId).url, { preserveScroll: true });
            }}
            className="grid gap-4"
        >
            {form.data.aspects.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    Belum ada aspek ujian. Tambahkan, misalnya Tes Tulis atau
                    Wawancara.
                </p>
            )}

            {form.data.aspects.map((aspect, index) => (
                <div
                    key={aspect.id ?? `new-${index}`}
                    className="grid gap-2 sm:grid-cols-[1fr_9rem_auto] sm:items-start"
                >
                    <div className="grid gap-1">
                        <Input
                            aria-label="Nama aspek"
                            placeholder="Nama aspek, mis. Tes Tulis"
                            value={aspect.name}
                            disabled={!canEdit}
                            aria-invalid={Boolean(
                                errors[`aspects.${index}.name`],
                            )}
                            onChange={(e) =>
                                setAspects(
                                    form.data.aspects.map((row, i) =>
                                        i === index
                                            ? { ...row, name: e.target.value }
                                            : row,
                                    ),
                                )
                            }
                        />
                        {errors[`aspects.${index}.name`] && (
                            <p className="text-sm font-medium text-destructive-foreground">
                                {errors[`aspects.${index}.name`]}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-1">
                        <Input
                            aria-label="KKM"
                            type="number"
                            inputMode="numeric"
                            min={0}
                            max={100}
                            placeholder="KKM (0-100)"
                            value={aspect.passing_score}
                            disabled={!canEdit}
                            aria-invalid={Boolean(
                                errors[`aspects.${index}.passing_score`],
                            )}
                            onChange={(e) =>
                                setAspects(
                                    form.data.aspects.map((row, i) =>
                                        i === index
                                            ? {
                                                  ...row,
                                                  passing_score: e.target.value,
                                              }
                                            : row,
                                    ),
                                )
                            }
                        />
                        {errors[`aspects.${index}.passing_score`] && (
                            <p className="text-sm font-medium text-destructive-foreground">
                                {errors[`aspects.${index}.passing_score`]}
                            </p>
                        )}
                    </div>
                    {canEdit && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Hapus aspek"
                            onClick={() =>
                                setAspects(
                                    form.data.aspects.filter(
                                        (_, i) => i !== index,
                                    ),
                                )
                            }
                        >
                            <Trash2 />
                        </Button>
                    )}
                </div>
            ))}

            {canEdit && (
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="max-md:h-10"
                        onClick={() =>
                            setAspects([
                                ...form.data.aspects,
                                { name: '', passing_score: 70 },
                            ])
                        }
                    >
                        <Plus />
                        Tambah aspek
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        Simpan aspek ujian
                    </Button>
                </div>
            )}
        </form>
    );
}
