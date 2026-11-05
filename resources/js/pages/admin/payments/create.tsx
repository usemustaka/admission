import { Head, Link, useForm } from '@inertiajs/react';
import { Wallet } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { ChoiceCardGroup } from '@/components/choice-card-group';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import {
    PaymentItemsPicker,
    selectedRows,
} from '@/components/payments/payment-items-picker';
import { PersonAvatar } from '@/components/person-avatar';
import { SearchInput } from '@/components/search-input';
import { SectionCard } from '@/components/section-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import { index, openItems, store } from '@/routes/admin/payments';
import { search } from '@/routes/admin/people';
import type { OpenGroup, Selection } from '@/types/billing';
import type { Option } from '@/types/people';

type Candidate = { id: number; name: string; nik: string | null };

export default function RecordPayment({ methods }: { methods: Option[] }) {
    const [term, setTerm] = useState('');
    const [candidates, setCandidates] = useState<Candidate[]>([]);
    const [payer, setPayer] = useState<Candidate | null>(null);
    const [groups, setGroups] = useState<OpenGroup[]>([]);
    const [selection, setSelection] = useState<Selection>({});
    const [loaded, setLoaded] = useState(false);

    const form = useForm({
        payer_person_id: '',
        items: [] as { bill_item_id: number; amount: number }[],
        paid_on: new Date().toISOString().slice(0, 10),
        method: 'cash',
        note: '',
        proof: null as File | null,
    });
    const errors = form.errors as Record<string, string>;

    useEffect(() => {
        if (payer || term.trim().length < 2) {
            return;
        }

        const controller = new AbortController();
        const timeout = setTimeout(async () => {
            const response = await fetch(search({ query: { q: term } }).url, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });

            if (response.ok) {
                setCandidates((await response.json()).data);
            }
        }, 250);

        return () => {
            clearTimeout(timeout);
            controller.abort();
        };
    }, [term, payer]);

    async function choose(candidate: Candidate) {
        setPayer(candidate);
        setSelection({});
        setLoaded(false);
        form.setData((data) => ({
            ...data,
            payer_person_id: String(candidate.id),
            items: [],
        }));

        const response = await fetch(
            openItems({ query: { payer: candidate.id } }).url,
            { headers: { Accept: 'application/json' } },
        );

        if (response.ok) {
            setGroups((await response.json()).groups);
        }

        setLoaded(true);
    }

    function change(next: Selection) {
        setSelection(next);
        form.setData('items', selectedRows(groups, next));
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(store().url, { forceFormData: true });
    }

    return (
        <>
            <Head title="Catat pembayaran" />
            <form
                onSubmit={submit}
                className="mx-auto grid w-full max-w-3xl gap-6 p-4 md:p-6"
            >
                <PageHeader
                    icon={Wallet}
                    title="Catat pembayaran"
                    description="Untuk pembayaran yang diterima langsung, misalnya tunai. Pembayaran yang dicatat di sini langsung terverifikasi."
                />

                <SectionCard title="Siapa yang membayar?">
                    {payer ? (
                        <div className="flex items-center gap-3 rounded-2xl border bg-sage-50 p-3 dark:bg-secondary/40">
                            <PersonAvatar name={payer.name} />
                            <div className="grid flex-1 leading-tight">
                                <span className="font-semibold">
                                    {payer.name}
                                </span>
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    {payer.nik ?? 'NIK belum ada'}
                                </span>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setPayer(null);
                                    setGroups([]);
                                    setSelection({});
                                    form.setData((data) => ({
                                        ...data,
                                        payer_person_id: '',
                                        items: [],
                                    }));
                                }}
                            >
                                Ganti
                            </Button>
                        </div>
                    ) : (
                        <div className="grid gap-3">
                            <SearchInput
                                value={term}
                                onValueChange={setTerm}
                                placeholder="Cari orang tua atau wali…"
                            />
                            <ul className="grid gap-1">
                                {candidates.map((candidate) => (
                                    <li key={candidate.id}>
                                        <button
                                            type="button"
                                            onClick={() => choose(candidate)}
                                            className="flex w-full items-center gap-3 rounded-xl p-2 text-left hover:bg-sage-50 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none dark:hover:bg-muted"
                                        >
                                            <PersonAvatar
                                                name={candidate.name}
                                                size="sm"
                                            />
                                            <span className="grid leading-tight">
                                                <span className="text-sm font-semibold">
                                                    {candidate.name}
                                                </span>
                                                <span className="text-xs text-muted-foreground tabular-nums">
                                                    {candidate.nik ??
                                                        'NIK belum ada'}
                                                </span>
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                    {errors.payer_person_id && (
                        <p className="mt-2 text-sm font-medium text-destructive-foreground">
                            {errors.payer_person_id}
                        </p>
                    )}
                </SectionCard>

                {payer && loaded && groups.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        {payer.name} tidak punya tagihan yang masih terbuka.
                    </p>
                )}

                {groups.length > 0 && (
                    <>
                        <SectionCard title="Yang dibayar">
                            <PaymentItemsPicker
                                groups={groups}
                                selection={selection}
                                onChange={change}
                                errors={errors}
                            />
                        </SectionCard>

                        <SectionCard title="Detail pembayaran">
                            <div className="grid gap-6 sm:grid-cols-2">
                                <FormField
                                    label="Tanggal bayar"
                                    required
                                    error={form.errors.paid_on}
                                >
                                    {(control) => (
                                        <Input
                                            type="date"
                                            value={form.data.paid_on}
                                            onChange={(e) =>
                                                form.setData(
                                                    'paid_on',
                                                    e.target.value,
                                                )
                                            }
                                            {...control}
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    label="Bukti (boleh kosong)"
                                    hint="Foto atau PDF, maksimal 4 MB"
                                    error={form.errors.proof}
                                >
                                    {(control) => (
                                        <Input
                                            type="file"
                                            accept="image/jpeg,image/png,application/pdf"
                                            onChange={(e) =>
                                                form.setData(
                                                    'proof',
                                                    e.target.files?.[0] ?? null,
                                                )
                                            }
                                            {...control}
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    label="Cara bayar"
                                    required
                                    error={form.errors.method}
                                    className="sm:col-span-2"
                                >
                                    {() => (
                                        <ChoiceCardGroup
                                            columns={2}
                                            value={form.data.method}
                                            onValueChange={(method) =>
                                                form.setData('method', method)
                                            }
                                            options={methods}
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    label="Catatan (boleh kosong)"
                                    error={form.errors.note}
                                    className="sm:col-span-2"
                                >
                                    {(control) => (
                                        <Textarea
                                            className="min-h-20"
                                            value={form.data.note}
                                            onChange={(e) =>
                                                form.setData(
                                                    'note',
                                                    e.target.value,
                                                )
                                            }
                                            {...control}
                                        />
                                    )}
                                </FormField>
                            </div>
                        </SectionCard>

                        <div className="flex justify-end gap-3">
                            <Button variant="outline" asChild>
                                <Link href={index()}>Batal</Link>
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    form.data.items.length === 0
                                }
                            >
                                {form.processing && <Spinner />}
                                Catat pembayaran
                            </Button>
                        </div>
                    </>
                )}
            </form>
        </>
    );
}

RecordPayment.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Pembayaran', href: index() },
    ],
};
