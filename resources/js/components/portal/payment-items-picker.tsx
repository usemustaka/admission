import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { selectedRows } from '@/components/payments/payment-items-picker';
import { formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { OpenGroup, Selection } from '@/types/billing';

/**
 * Picks which bill items a payment covers, laid out for a phone: each item is
 * a big tappable row, and the amount field appears under it once ticked.
 * The rows come out in the same order as `selectedRows`, so the server's
 * errors line up with them.
 */
export function PortalPaymentItemsPicker({
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
                <fieldset key={group.bill_id} className="grid gap-2">
                    <legend className="mb-1 text-base font-semibold">
                        {group.registrant.child_name}
                        <span className="font-normal text-muted-foreground">
                            {' '}
                            · Tagihan {group.type_label}
                        </span>
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
                                className={cn(
                                    'grid gap-3 rounded-xl border p-3.5 transition-colors',
                                    isSelected
                                        ? 'border-neutral-900 bg-lime-50 dark:border-lime-400 dark:bg-lime-400/10'
                                        : 'border-input',
                                )}
                            >
                                <label className="flex min-h-11 cursor-pointer items-center gap-3">
                                    <Checkbox
                                        checked={isSelected}
                                        onCheckedChange={(checked) =>
                                            toggle(
                                                item.id,
                                                item.outstanding,
                                                checked === true,
                                            )
                                        }
                                        className="size-6"
                                    />
                                    <span className="grid flex-1 gap-0.5">
                                        <span className="text-[15px] font-semibold">
                                            {item.name}
                                        </span>
                                        <span className="text-sm text-muted-foreground tabular-nums">
                                            Sisa{' '}
                                            {formatRupiah(item.outstanding)}
                                            {item.outstanding !== item.amount &&
                                                ` dari ${formatRupiah(item.amount)}`}
                                        </span>
                                    </span>
                                </label>

                                {isSelected && (
                                    <div className="grid gap-1.5">
                                        <label
                                            htmlFor={`amount-${item.id}`}
                                            className="text-[15px] font-semibold"
                                        >
                                            Nominal yang dibayar (Rp)
                                        </label>
                                        <Input
                                            id={`amount-${item.id}`}
                                            type="number"
                                            inputMode="numeric"
                                            min={1}
                                            max={item.outstanding}
                                            aria-label={`Nominal ${item.name}`}
                                            value={selection[item.id] ?? ''}
                                            aria-invalid={Boolean(error)}
                                            onChange={(event) =>
                                                onChange({
                                                    ...selection,
                                                    [item.id]:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                        {error && (
                                            <p className="text-[15px] font-medium text-destructive-foreground">
                                                {error}
                                            </p>
                                        )}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </fieldset>
            ))}

            {errors.items && (
                <p className="text-[15px] font-medium text-destructive-foreground">
                    {errors.items}
                </p>
            )}
        </div>
    );
}
