import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { formatRupiah } from '@/lib/format';
import type { OpenGroup, Selection } from '@/types/billing';

/**
 * The rows that a payment pays, in the order the form submits them. Errors from the server refer to that order.
 */
export function selectedRows(
    groups: OpenGroup[],
    selection: Selection,
): { bill_item_id: number; amount: number }[] {
    return groups
        .flatMap((group) => group.items)
        .filter((item) => item.id in selection)
        .map((item) => ({
            bill_item_id: item.id,
            amount: Number(selection[item.id]) || 0,
        }));
}

export function PaymentItemsPicker({
    groups,
    selection,
    onChange,
    errors,
}: {
    groups: OpenGroup[];
    selection: Selection;
    onChange: (selection: Selection) => void;
    errors: Record<string, string>;
}) {
    const rows = selectedRows(groups, selection);
    const total = rows.reduce((sum, row) => sum + row.amount, 0);

    function toggle(itemId: number, outstanding: number, checked: boolean) {
        const next = { ...selection };

        if (checked) {
            next[itemId] = String(outstanding);
        } else {
            delete next[itemId];
        }

        onChange(next);
    }

    return (
        <div className="grid gap-4">
            {groups.map((group) => (
                <fieldset
                    key={group.bill_id}
                    className="grid gap-3 border-t border-line pt-4 first:border-t-0 first:pt-0"
                >
                    <legend className="mb-3 text-sm font-semibold">
                        {group.registrant.child_name} · Tagihan{' '}
                        {group.type_label}
                    </legend>
                    {group.items.map((item) => {
                        const index = rows.findIndex(
                            (row) => row.bill_item_id === item.id,
                        );
                        const error =
                            errors[`items.${index}.amount`] ??
                            errors[`items.${index}.bill_item_id`];
                        const isSelected = item.id in selection;

                        return (
                            <div
                                key={item.id}
                                className="grid gap-2 @2xl:grid-cols-[1fr_11rem] @2xl:items-start"
                            >
                                <label className="flex items-start gap-3 text-sm">
                                    <Checkbox
                                        checked={isSelected}
                                        onCheckedChange={(checked) =>
                                            toggle(
                                                item.id,
                                                item.outstanding,
                                                checked === true,
                                            )
                                        }
                                        className="mt-0.5"
                                    />
                                    <span className="grid gap-0.5">
                                        <span className="font-semibold">
                                            {item.name}
                                        </span>
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            Sisa{' '}
                                            {formatRupiah(item.outstanding)}
                                            {item.outstanding !== item.amount &&
                                                ` dari ${formatRupiah(item.amount)}`}
                                        </span>
                                    </span>
                                </label>
                                <div className="grid gap-1">
                                    <Input
                                        type="number"
                                        inputMode="numeric"
                                        className="tabular-nums"
                                        min={1}
                                        max={item.outstanding}
                                        aria-label={`Nominal ${item.name}`}
                                        placeholder="Nominal (Rp)"
                                        disabled={!isSelected}
                                        value={selection[item.id] ?? ''}
                                        aria-invalid={Boolean(error)}
                                        onChange={(event) =>
                                            onChange({
                                                ...selection,
                                                [item.id]: event.target.value,
                                            })
                                        }
                                    />
                                    {error && (
                                        <p className="text-sm font-medium text-destructive-foreground">
                                            {error}
                                        </p>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </fieldset>
            ))}

            {errors.items && (
                <p className="text-sm font-medium text-destructive-foreground">
                    {errors.items}
                </p>
            )}

            <p className="flex items-baseline justify-between gap-3 border-t border-line pt-4 text-lg font-semibold tracking-tight tabular-nums">
                <span className="text-sm font-medium text-muted-foreground">
                    Total dibayar
                </span>
                {formatRupiah(total)}
            </p>
        </div>
    );
}
