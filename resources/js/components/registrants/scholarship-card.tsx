import { router } from '@inertiajs/react';
import { HandHeart } from 'lucide-react';
import { useState } from 'react';
import { ChoiceCardGroup } from '@/components/choice-card-group';
import { FormField } from '@/components/form-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { toastFirstError } from '@/hooks/use-flash-toast';
import { formatDate, formatRupiah } from '@/lib/format';
import { scholarship } from '@/routes/admin/registrants';
import type { Option } from '@/types/people';

export type ScholarshipData = {
    /** Kinds the evidence points at. Nothing is decided by them. */
    suggested: string[];
    kinds: Option[];
    items: { bill_item_id: number; label: string; outstanding: number }[];
    granted: {
        id: number;
        amount: number;
        date: string;
        note: string | null;
    }[];
    /** Why no scholarship can be added right now, or null. */
    blocked: string | null;
};

type Row = { checked: boolean; amount: string };

/**
 * Shows what the parent supplied as evidence and lets staff with the permission grant a scholarship. A scholarship
 * is recorded as a verified payment of the method "Beasiswa": the bill stays as it is and shows more paid.
 */
export function ScholarshipCard({
    registrantId,
    data,
}: {
    registrantId: number;
    data: ScholarshipData;
}) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [kind, setKind] = useState(data.suggested[0] ?? data.kinds[0]?.value);
    const [note, setNote] = useState('');
    const [rows, setRows] = useState<Record<number, Row>>({});
    const [errors, setErrors] = useState<Record<string, string>>({});

    const labelOf = (value: string) =>
        data.kinds.find((option) => option.value === value)?.label ?? value;

    function openDialog() {
        setRows(
            Object.fromEntries(
                data.items.map((item) => [
                    item.bill_item_id,
                    { checked: true, amount: String(item.outstanding) },
                ]),
            ),
        );
        setErrors({});
        setOpen(true);
    }

    function submit() {
        const items = data.items
            .filter((item) => rows[item.bill_item_id]?.checked)
            .map((item) => ({
                bill_item_id: item.bill_item_id,
                amount: Number(rows[item.bill_item_id].amount || 0),
            }));

        setProcessing(true);
        router.post(
            scholarship(registrantId).url,
            { kind, items, note },
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onError: (received) => {
                    setErrors(received);
                    toastFirstError(received, (key) => key.startsWith('items'));
                },
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <div className="grid gap-4">
            {data.suggested.length > 0 ? (
                <div className="grid gap-2">
                    <p className="text-sm text-muted-foreground">
                        Dari berkas dan isian orang tua, anak ini berpotensi
                        mendapat beasiswa:
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {data.suggested.map((value) => (
                            <Badge key={value} variant="info">
                                {labelOf(value)}
                            </Badge>
                        ))}
                    </div>
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Orang tua belum melampirkan bukti yang mengarah ke beasiswa
                    tertentu. Panitia tetap bisa memberikannya kalau perlu.
                </p>
            )}

            {data.granted.length > 0 && (
                <ul className="grid gap-2 border-t border-line pt-3">
                    {data.granted.map((grant) => (
                        <li key={grant.id} className="text-sm">
                            <span className="font-semibold">
                                {formatRupiah(grant.amount)}
                            </span>{' '}
                            <span className="text-muted-foreground">
                                · {formatDate(grant.date)} ·{' '}
                                {grant.note ?? 'Beasiswa'}
                            </span>
                        </li>
                    ))}
                </ul>
            )}

            {data.blocked ? (
                <p className="text-sm text-muted-foreground">{data.blocked}</p>
            ) : (
                <div>
                    <Button onClick={openDialog}>
                        <HandHeart />
                        Beasiswakan anak ini
                    </Button>
                </div>
            )}

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Beasiswakan anak ini</DialogTitle>
                        <DialogDescription>
                            Beasiswa dicatat sebagai pembayaran. Tagihan tidak
                            berubah, hanya tampil lebih banyak yang sudah
                            dibayar.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-5">
                        <FormField
                            label="Jenis beasiswa"
                            required
                            error={errors.kind}
                        >
                            {() => (
                                <ChoiceCardGroup
                                    columns={3}
                                    value={kind}
                                    onValueChange={setKind}
                                    options={data.kinds}
                                />
                            )}
                        </FormField>

                        <fieldset className="grid gap-3">
                            <legend className="text-[15px] font-medium">
                                Tagihan yang ditutup
                            </legend>
                            {data.items.map((item) => {
                                const row = rows[item.bill_item_id];

                                return (
                                    <div
                                        key={item.bill_item_id}
                                        className="grid grid-cols-[auto_1fr_9rem] items-center gap-3"
                                    >
                                        <Checkbox
                                            checked={row?.checked ?? false}
                                            aria-label={item.label}
                                            onCheckedChange={(checked) =>
                                                setRows({
                                                    ...rows,
                                                    [item.bill_item_id]: {
                                                        ...row,
                                                        checked:
                                                            checked === true,
                                                    },
                                                })
                                            }
                                        />
                                        <span className="text-sm">
                                            {item.label}
                                            <span className="block text-muted-foreground">
                                                Sisa{' '}
                                                {formatRupiah(item.outstanding)}
                                            </span>
                                        </span>
                                        <Input
                                            inputMode="numeric"
                                            aria-label={`Nominal ${item.label}`}
                                            value={row?.amount ?? ''}
                                            disabled={!row?.checked}
                                            onChange={(event) =>
                                                setRows({
                                                    ...rows,
                                                    [item.bill_item_id]: {
                                                        ...row,
                                                        amount: event.target.value.replace(
                                                            /\D/g,
                                                            '',
                                                        ),
                                                    },
                                                })
                                            }
                                        />
                                    </div>
                                );
                            })}
                            {Object.entries(errors)
                                .filter(([key]) => key.startsWith('items'))
                                .map(([key, message]) => (
                                    <p
                                        key={key}
                                        className="text-sm font-medium text-destructive-foreground"
                                    >
                                        {message}
                                    </p>
                                ))}
                        </fieldset>

                        <FormField
                            label="Catatan"
                            hint="Alasan atau dasar keputusan, supaya tercatat"
                            error={errors.note}
                        >
                            {(control) => (
                                <Textarea
                                    className="min-h-20"
                                    value={note}
                                    onChange={(event) =>
                                        setNote(event.target.value)
                                    }
                                    {...control}
                                />
                            )}
                        </FormField>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            disabled={processing}
                            onClick={submit}
                        >
                            {processing && <Spinner />}
                            Beasiswakan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
