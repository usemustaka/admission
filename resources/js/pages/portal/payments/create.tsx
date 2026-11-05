import { Head, Link, useForm } from '@inertiajs/react';
import { CircleCheck, Receipt } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ChoiceCardGroup } from '@/components/choice-card-group';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import {
    PaymentItemsPicker,
    selectedRows,
} from '@/components/payments/payment-items-picker';
import { SectionCard } from '@/components/section-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/portal/bills';
import { bills } from '@/routes/portal/children';
import { store } from '@/routes/portal/payments';
import type { OpenGroup, Selection } from '@/types/billing';
import type { Option } from '@/types/people';
import type { PortalRegistrant } from '@/types/portal';

export default function CreatePayment({
    focus,
    groups,
    methods,
}: {
    focus: PortalRegistrant | null;
    groups: OpenGroup[];
    methods: Option[];
}) {
    const [selection, setSelection] = useState<Selection>({});
    const form = useForm({
        items: [] as { bill_item_id: number; amount: number }[],
        paid_on: new Date().toISOString().slice(0, 10),
        method: 'transfer',
        note: '',
        proof: null as File | null,
    });
    const errors = form.errors as Record<string, string>;
    const backUrl = focus ? bills(focus.id) : index();

    function change(next: Selection) {
        setSelection(next);
        form.setData('items', selectedRows(groups, next));
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(store().url, { forceFormData: true });
    }

    if (groups.length === 0) {
        return (
            <>
                <Head title="Konfirmasi pembayaran" />
                <div className="mx-auto grid w-full max-w-2xl gap-6 px-4 py-8">
                    <PageHeader title="Konfirmasi pembayaran" />
                    <EmptyState
                        icon={CircleCheck}
                        title="Tidak ada yang perlu dibayar"
                        description="Semua tagihan sudah dibayar atau sedang menunggu verifikasi panitia."
                        action={
                            <Button variant="outline" asChild>
                                <Link href={backUrl}>Kembali ke tagihan</Link>
                            </Button>
                        }
                    />
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Konfirmasi pembayaran" />
            <form
                onSubmit={submit}
                className="mx-auto grid w-full max-w-3xl gap-6 px-4 py-8"
            >
                <PageHeader
                    icon={Receipt}
                    title="Konfirmasi pembayaran"
                    description={
                        focus
                            ? `Untuk tagihan ${focus.child.name}.`
                            : 'Sudah transfer? Pilih rincian yang kamu bayar, unggah buktinya, lalu kirim. Bisa untuk beberapa anak sekaligus.'
                    }
                />

                <SectionCard title="Yang kamu bayar">
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
                                    max={new Date().toISOString().slice(0, 10)}
                                    value={form.data.paid_on}
                                    onChange={(e) =>
                                        form.setData('paid_on', e.target.value)
                                    }
                                    {...control}
                                />
                            )}
                        </FormField>
                        <FormField
                            label="Bukti pembayaran"
                            required
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
                                    placeholder="Contoh: transfer dari rekening ayah"
                                    value={form.data.note}
                                    onChange={(e) =>
                                        form.setData('note', e.target.value)
                                    }
                                    {...control}
                                />
                            )}
                        </FormField>
                    </div>
                </SectionCard>

                <div className="flex justify-end gap-3">
                    <Button variant="outline" asChild>
                        <Link href={backUrl}>Batal</Link>
                    </Button>
                    <Button
                        type="submit"
                        disabled={
                            form.processing || form.data.items.length === 0
                        }
                    >
                        {form.processing && <Spinner />}
                        Kirim konfirmasi
                    </Button>
                </div>
            </form>
        </>
    );
}
