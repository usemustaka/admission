import { useForm } from '@inertiajs/react';
import { ChoiceCardGroup } from '@/components/choice-card-group';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { documents as saveDocuments } from '@/routes/admin/rulesets';

export type DocumentRequirementRow = {
    type: string;
    label: string;
    choice: 'none' | 'optional' | 'required';
};

const choices = [
    { value: 'none', label: 'Tidak diminta' },
    { value: 'optional', label: 'Opsional' },
    { value: 'required', label: 'Wajib' },
];

export function DocumentRequirementEditor({
    rulesetId,
    initial,
    canEdit,
}: {
    rulesetId: number;
    initial: DocumentRequirementRow[];
    canEdit: boolean;
}) {
    const form = useForm<{ documents: Record<string, string> }>({
        documents: Object.fromEntries(
            initial.map((row) => [row.type, row.choice]),
        ),
    });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(saveDocuments(rulesetId).url, {
                    preserveScroll: true,
                });
            }}
            className="grid gap-5"
        >
            <p className="text-sm text-muted-foreground">
                Berkas yang <strong>wajib</strong> harus lengkap sebelum data
                bisa dikirim. Berkas opsional boleh dilewati. Sertifikat
                prestasi, KIP, dan berkas pendukung beasiswa selalu tersedia dan
                tidak pernah wajib. Perubahan hanya berlaku untuk anak yang
                mendaftar setelah ini.
            </p>

            {initial.map((row) => (
                <div key={row.type} className="grid gap-2">
                    <p className="text-[15px] font-medium">{row.label}</p>
                    <fieldset disabled={!canEdit}>
                        <ChoiceCardGroup
                            columns={3}
                            value={form.data.documents[row.type]}
                            onValueChange={(choice) =>
                                form.setData('documents', {
                                    ...form.data.documents,
                                    [row.type]: choice,
                                })
                            }
                            options={choices}
                        />
                    </fieldset>
                </div>
            ))}

            {canEdit && (
                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        Simpan berkas
                    </Button>
                </div>
            )}
        </form>
    );
}
