import { router, usePage } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { ChoiceCardGroup } from '@/components/choice-card-group';
import { DuplicateCandidates } from '@/components/people/duplicate-candidates';
import { FormField } from '@/components/form-field';
import { PersonAvatar } from '@/components/person-avatar';
import { SearchInput } from '@/components/search-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { formatDate } from '@/lib/format';
import { search } from '@/routes/admin/people';
import { store } from '@/routes/admin/people/parentships';
import type { DuplicateCandidate, Option } from '@/types/people';

type Candidate = {
    id: number;
    name: string;
    nik: string | null;
    birth_date: string | null;
    phone_masked: string | null;
    /** Already a parent or guardian of this child. */
    linked: boolean;
};

function candidateDetails(candidate: Candidate): string {
    return [
        candidate.nik ?? 'NIK belum ada',
        candidate.birth_date
            ? `Lahir ${formatDate(new Date(`${candidate.birth_date}T00:00:00`))}`
            : null,
        candidate.phone_masked ?? 'Belum ada telepon',
    ]
        .filter(Boolean)
        .join(' · ');
}

export function AddParentDialog({
    childId,
    relations,
    kinds,
    trigger,
}: {
    childId: number;
    relations: Option[];
    kinds: Option[];
    /** Replaces the default button, for placing the action elsewhere on the page. */
    trigger?: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [term, setTerm] = useState('');
    const [candidates, setCandidates] = useState<Candidate[]>([]);
    const [searchedTerm, setSearchedTerm] = useState('');
    const [selected, setSelected] = useState<Candidate | null>(null);
    const [relation, setRelation] = useState('');
    const [kind, setKind] = useState('');
    const [isPrimaryContact, setIsPrimaryContact] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const flash = usePage().flash as { duplicates?: DuplicateCandidate[] };
    const duplicates = errors.duplicate ? (flash.duplicates ?? []) : [];

    useEffect(() => {
        if (!open || selected || term.trim().length < 2) {
            return;
        }

        const controller = new AbortController();
        const timeout = setTimeout(async () => {
            const response = await fetch(
                search({ query: { q: term, except: childId } }).url,
                {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                },
            );

            if (response.ok) {
                setCandidates((await response.json()).data);
                setSearchedTerm(term);
            }
        }, 250);

        return () => {
            clearTimeout(timeout);
            controller.abort();
        };
    }, [term, open, selected, childId]);

    function reset() {
        setTerm('');
        setCandidates([]);
        setSearchedTerm('');
        setSelected(null);
        setRelation('');
        setKind('');
        setIsPrimaryContact(false);
        setErrors({});
    }

    function submit(confirmDuplicate = false) {
        if (!selected) {
            return;
        }

        router.post(
            store(childId).url,
            {
                related_person_id: selected.id,
                relation,
                kind: relation === 'guardian' ? null : kind || null,
                is_primary_contact: isPrimaryContact,
                ...(confirmDuplicate ? { confirm_duplicate: true } : {}),
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (formErrors) => setErrors(formErrors),
                onSuccess: () => {
                    setOpen(false);
                    reset();
                },
            },
        );
    }

    const needsKind = relation === 'father' || relation === 'mother';
    const canSubmit = selected && relation && (!needsKind || kind);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    reset();
                }
            }}
        >
            <DialogTrigger asChild>
                {trigger ?? (
                    <Button variant="outline" size="sm" className="max-md:h-10">
                        <UserPlus />
                        Tambah orang tua / wali
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Tambah orang tua atau wali</DialogTitle>
                    <DialogDescription>
                        Cari orang yang sudah tercatat lewat nama atau NIK.
                        Belum ada? Tambahkan dulu lewat menu Orang.
                    </DialogDescription>
                </DialogHeader>

                {selected ? (
                    <div className="grid gap-5">
                        <div className="flex items-center gap-3 rounded-2xl border bg-neutral-50 p-3 dark:bg-secondary/40">
                            <PersonAvatar name={selected.name} size="md" />
                            <div className="grid flex-1 leading-tight">
                                <span className="font-semibold">
                                    {selected.name}
                                </span>
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    {candidateDetails(selected)}
                                </span>
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="max-md:h-10"
                                onClick={() => {
                                    setSelected(null);
                                    setErrors({});
                                }}
                            >
                                Ganti
                            </Button>
                        </div>

                        {duplicates.length > 0 && (
                            <DuplicateCandidates
                                message={`Ada orang lain yang datanya mirip dengan ${selected.name}. Kalau orang tuanya sebenarnya salah satu dari mereka, pilih yang itu supaya keluarganya tidak terbelah.`}
                                candidates={duplicates}
                                action={(candidate) => (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            setSelected({
                                                id: candidate.id,
                                                name: candidate.name,
                                                nik: candidate.has_nik
                                                    ? 'NIK tercatat'
                                                    : null,
                                                birth_date:
                                                    candidate.birth_date,
                                                phone_masked:
                                                    candidate.phone_masked,
                                                linked: false,
                                            });
                                            setErrors({});
                                        }}
                                    >
                                        Pakai yang ini
                                    </Button>
                                )}
                            >
                                <Button
                                    type="button"
                                    size="sm"
                                    className="w-fit"
                                    disabled={!canSubmit || processing}
                                    onClick={() => submit(true)}
                                >
                                    Tetap pakai {selected.name}
                                </Button>
                            </DuplicateCandidates>
                        )}

                        <FormField
                            label="Hubungannya"
                            required
                            error={errors.relation ?? errors.related_person_id}
                        >
                            {() => (
                                <ChoiceCardGroup
                                    columns={3}
                                    value={relation}
                                    onValueChange={setRelation}
                                    options={relations}
                                />
                            )}
                        </FormField>

                        {needsKind && (
                            <FormField
                                label="Kandung atau tiri?"
                                required
                                error={errors.kind}
                            >
                                {() => (
                                    <ChoiceCardGroup
                                        columns={2}
                                        value={kind}
                                        onValueChange={setKind}
                                        options={kinds}
                                    />
                                )}
                            </FormField>
                        )}

                        <label className="flex items-center gap-3 text-sm font-medium">
                            <Switch
                                checked={isPrimaryContact}
                                onCheckedChange={setIsPrimaryContact}
                            />
                            Jadikan kontak utama (nomor HP-nya dipakai untuk
                            pengumuman)
                        </label>
                    </div>
                ) : (
                    <div className="grid gap-3">
                        <SearchInput
                            value={term}
                            onValueChange={setTerm}
                            placeholder="Ketik minimal 2 huruf…"
                            autoFocus
                        />
                        <ul className="grid gap-1">
                            {candidates.map((candidate) => (
                                <li key={candidate.id}>
                                    <button
                                        type="button"
                                        disabled={candidate.linked}
                                        onClick={() => setSelected(candidate)}
                                        className="flex w-full items-center gap-3 rounded-xl p-2 text-left transition-colors hover:bg-neutral-50 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:bg-transparent dark:hover:bg-muted"
                                    >
                                        <PersonAvatar
                                            name={candidate.name}
                                            size="sm"
                                        />
                                        <span className="grid min-w-0 flex-1 leading-tight">
                                            <span className="text-sm font-semibold">
                                                {candidate.name}
                                            </span>
                                            <span className="text-xs text-muted-foreground tabular-nums">
                                                {candidateDetails(candidate)}
                                            </span>
                                        </span>
                                        {candidate.linked && (
                                            <Badge variant="muted">
                                                Sudah tercatat
                                            </Badge>
                                        )}
                                    </button>
                                </li>
                            ))}
                            {searchedTerm === term &&
                                candidates.length === 0 && (
                                    <li className="p-2 text-sm text-muted-foreground">
                                        Belum ada yang cocok.
                                    </li>
                                )}
                        </ul>
                    </div>
                )}

                <DialogFooter className="gap-2">
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Batal
                    </Button>
                    <Button
                        disabled={!canSubmit || processing}
                        onClick={() => submit()}
                    >
                        {processing && <Spinner />}
                        Tambahkan
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
