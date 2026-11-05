import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { newRowKey } from '@/components/admission/row-key';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatRupiah } from '@/lib/format';
import { bills } from '@/routes/admin/rulesets';
import type { BillItemRow } from '@/types/admission';

type BillsData = {
    registration: BillItemRow[];
    settlement: BillItemRow[];
};

const groups: {
    key: keyof BillsData;
    title: string;
    hint: string;
}[] = [
    {
        key: 'registration',
        title: 'Tagihan pendaftaran',
        hint: 'Terbit otomatis saat anak didaftarkan. Kosongkan kalau tidak ada biaya pendaftaran, tahap ini akan dilewati.',
    },
    {
        key: 'settlement',
        title: 'Tagihan pelunasan',
        hint: 'Terbit setelah data anak dinyatakan valid.',
    },
];

export function BillEditor({
    rulesetId,
    initial,
    canEdit,
}: {
    rulesetId: number;
    initial: BillsData;
    canEdit: boolean;
}) {
    const form = useForm<{ bills: BillsData }>({ bills: initial });

    function setItems(key: keyof BillsData, items: BillItemRow[]) {
        form.setData('bills', { ...form.data.bills, [key]: items });
    }

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(bills(rulesetId).url, { preserveScroll: true });
            }}
            className="grid gap-8"
        >
            <p className="text-sm text-muted-foreground">
                Perubahan hanya berlaku untuk anak yang mendaftar setelah ini.
                Tagihan pendaftar yang sudah terdaftar tidak berubah.
            </p>

            {groups.map((group) => {
                const items = form.data.bills[group.key];
                const total = items.reduce(
                    (sum, item) => sum + (Number(item.amount) || 0),
                    0,
                );

                return (
                    <fieldset key={group.key} className="grid gap-3">
                        <legend className="text-sm font-semibold">
                            {group.title}
                        </legend>
                        <p className="text-sm text-muted-foreground">
                            {group.hint}
                        </p>

                        {items.map((item, index) => {
                            const prefix = `bills.${group.key}.${index}`;
                            const nameError = (
                                form.errors as Record<string, string>
                            )[`${prefix}.name`];
                            const amountError = (
                                form.errors as Record<string, string>
                            )[`${prefix}.amount`];

                            return (
                                <div
                                    key={item.id ?? item.key ?? `new-${index}`}
                                    className="grid gap-2 sm:grid-cols-[1fr_12rem_auto] sm:items-start"
                                >
                                    <div className="grid gap-1">
                                        <Input
                                            aria-label="Nama rincian"
                                            placeholder="Nama rincian, mis. Uang pangkal"
                                            value={item.name}
                                            disabled={!canEdit}
                                            aria-invalid={Boolean(nameError)}
                                            onChange={(e) =>
                                                setItems(
                                                    group.key,
                                                    items.map((row, i) =>
                                                        i === index
                                                            ? {
                                                                  ...row,
                                                                  name: e.target
                                                                      .value,
                                                              }
                                                            : row,
                                                    ),
                                                )
                                            }
                                        />
                                        {nameError && (
                                            <p className="text-sm font-medium text-destructive-foreground">
                                                {nameError}
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-1">
                                        <Input
                                            aria-label="Nominal"
                                            type="number"
                                            inputMode="numeric"
                                            min={0}
                                            placeholder="Nominal (Rp)"
                                            value={item.amount}
                                            disabled={!canEdit}
                                            aria-invalid={Boolean(amountError)}
                                            onChange={(e) =>
                                                setItems(
                                                    group.key,
                                                    items.map((row, i) =>
                                                        i === index
                                                            ? {
                                                                  ...row,
                                                                  amount: e
                                                                      .target
                                                                      .value,
                                                              }
                                                            : row,
                                                    ),
                                                )
                                            }
                                        />
                                        {amountError && (
                                            <p className="text-sm font-medium text-destructive-foreground">
                                                {amountError}
                                            </p>
                                        )}
                                    </div>
                                    {canEdit && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Hapus rincian"
                                            onClick={() =>
                                                setItems(
                                                    group.key,
                                                    items.filter(
                                                        (_, i) => i !== index,
                                                    ),
                                                )
                                            }
                                        >
                                            <Trash2 />
                                        </Button>
                                    )}
                                </div>
                            );
                        })}

                        <div className="flex flex-wrap items-center justify-between gap-2">
                            {canEdit ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="max-md:h-10"
                                    onClick={() =>
                                        setItems(group.key, [
                                            ...items,
                                            {
                                                key: newRowKey(),
                                                name: '',
                                                amount: '',
                                            },
                                        ])
                                    }
                                >
                                    <Plus />
                                    Tambah rincian
                                </Button>
                            ) : (
                                <span />
                            )}
                            <p className="text-sm font-semibold tabular-nums">
                                Total {formatRupiah(total)}
                            </p>
                        </div>
                    </fieldset>
                );
            })}

            {canEdit && (
                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        Simpan tagihan
                    </Button>
                </div>
            )}
        </form>
    );
}
