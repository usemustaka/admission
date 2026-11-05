import { Head, Link, useForm } from '@inertiajs/react';
import { Wallet } from 'lucide-react';
import { toast } from 'sonner';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { ChoiceCardGroup } from '@/components/choice-card-group';
import { EmptyState } from '@/components/empty-state';
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
import { Skeleton } from '@/components/ui/skeleton';
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
    const [searched, setSearched] = useState(false);

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
            try {
                const response = await fetch(
                    search({ query: { q: term } }).url,
                    {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    },
                );

                if (response.ok) {
                    setCandidates((await response.json()).data);
                    setSearched(true);
                } else {
                    toast.error('Pencarian gagal. Coba lagi sebentar lagi.');
                }
            } catch (error) {
                if ((error as Error).name !== 'AbortError') {
                    toast.error('Pencarian gagal. Cek koneksimu.');
                }
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
        setGroups([]);
        setLoaded(false);
        form.setData((data) => ({
            ...data,
            payer_person_id: String(candidate.id),
            items: [],
        }));

        try {
            const response = await fetch(
                openItems({ query: { payer: candidate.id } }).url,
                { headers: { Accept: 'application/json' } },
            );

            if (response.ok) {
                setGroups((await response.json()).groups);
            } else {
                toast.error('Tagihan tidak bisa dimuat. Coba pilih ulang.');
            }
        } catch {
            toast.error('Tagihan tidak bisa dimuat. Cek koneksimu.');
        }

        setLoaded(true);
    }

    function clearPayer() {
        setPayer(null);
        setGroups([]);
        setSelection({});
        setLoaded(false);
        setSearched(false);
        form.setData((data) => ({ ...data, payer_person_id: '', items: [] }));
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
                className="mx-auto grid w-full max-w-3xl gap-6"
            >
                <PageHeader
                    icon={Wallet}
                    title="Catat pembayaran"
                    description="Untuk pembayaran yang diterima langsung, misalnya tunai. Pembayaran yang dicatat di sini langsung terverifikasi."
                />

                <SectionCard title="Siapa yang membayar?">
                    {payer ? (
                        <div className="flex items-center gap-3">
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
                                variant="outline"
                                size="sm"
                                onClick={clearPayer}
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
                                aria-label="Cari pembayar"
                                autoFocus
                            />
                            {searched &&
                                term.trim().length >= 2 &&
                                candidates.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        Tidak ada orang dengan nama itu. Coba
                                        nama lain atau NIK-nya.
                                    </p>
                                )}
                            <ul className="grid gap-1">
                                {(term.trim().length >= 2
                                    ? candidates
                                    : []
                                ).map((candidate) => (
                                    <li key={candidate.id}>
                                        <button
                                            type="button"
                                            onClick={() => choose(candidate)}
                                            className="flex min-h-12 w-full items-center gap-3 rounded-xl p-2 text-left outline-none hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
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

                {payer && !loaded && (
                    <div
                        className="grid gap-3"
                        aria-busy="true"
                        aria-label="Memuat tagihan"
                    >
                        <Skeleton className="h-6 w-48" />
                        <Skeleton className="h-24 w-full rounded-2xl" />
                    </div>
                )}

                {payer && loaded && groups.length === 0 && (
                    <EmptyState
                        icon={Wallet}
                        title="Tidak ada tagihan terbuka"
                        description={`${payer.name} tidak punya tagihan yang masih harus dibayar.`}
                    />
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
        { title: 'Catat', href: '#' },
    ],
};
