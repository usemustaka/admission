import { Head, Link, useForm } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ChoiceCardGroup } from '@/components/choice-card-group';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { selectedRows } from '@/components/payments/payment-items-picker';
import { BankCard } from '@/components/portal/bank-card';
import { PortalPaymentItemsPicker } from '@/components/portal/payment-items-picker';
import { ProofUpload } from '@/components/portal/proof-upload';
import { StickyActions } from '@/components/portal/sticky-actions';
import { SectionCard } from '@/components/section-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatRupiah } from '@/lib/format';
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
    const total = form.data.items.reduce((sum, row) => sum + row.amount, 0);

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
                <div className="grid gap-4">
                    <PageHeader title="Konfirmasi pembayaran" />
                    <EmptyState
                        icon={CircleCheck}
                        title="Tidak ada yang perlu dibayar"
                        description="Semua tagihan sudah dibayar atau sedang menunggu verifikasi panitia."
                        action={
                            <Button variant="outline" size="lg" asChild>
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
            <form onSubmit={submit} className="grid gap-4">
                <PageHeader
                    title="Konfirmasi pembayaran"
                    description={
                        focus
                            ? `Untuk tagihan ${focus.child.name}. Sudah transfer? Unggah buktinya, lalu kirim.`
                            : 'Sudah transfer? Pilih rincian yang kamu bayar, unggah buktinya, lalu kirim. Bisa untuk beberapa anak sekaligus.'
                    }
                />

                <BankCard />

                <SectionCard
                    title="1. Yang kamu bayar"
                    description="Centang rincian yang sudah kamu transfer."
                >
                    <PortalPaymentItemsPicker
                        groups={groups}
                        selection={selection}
                        onChange={change}
                        errors={errors}
                    />
                </SectionCard>

                <SectionCard title="2. Bukti pembayaran">
                    <div className="grid gap-4">
                        <FormField
                            label="Foto atau PDF bukti transfer"
                            required
                            hint="Maksimal 4 MB"
                            error={form.errors.proof}
                        >
                            {(control) => (
                                <ProofUpload
                                    value={form.data.proof}
                                    onChange={(file) =>
                                        form.setData('proof', file)
                                    }
                                    control={control}
                                />
                            )}
                        </FormField>
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
                            label="Cara bayar"
                            required
                            error={form.errors.method}
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
                        >
                            {(control) => (
                                <Textarea
                                    className="min-h-24"
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

                <StickyActions>
                    <p className="flex items-baseline justify-between gap-3">
                        <span className="text-[15px] font-medium text-muted-foreground">
                            Total dibayar
                        </span>
                        <span className="text-xl font-bold tracking-tight tabular-nums">
                            {formatRupiah(total)}
                        </span>
                    </p>
                    <div className="flex gap-3">
                        <Button variant="outline" size="lg" asChild>
                            <Link href={backUrl}>Batal</Link>
                        </Button>
                        <Button
                            type="submit"
                            size="lg"
                            className="flex-1"
                            disabled={
                                form.processing || form.data.items.length === 0
                            }
                        >
                            {form.processing && <Spinner />}
                            Kirim konfirmasi
                        </Button>
                    </div>
                </StickyActions>
            </form>
        </>
    );
}
