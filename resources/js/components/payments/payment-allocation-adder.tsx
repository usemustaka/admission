import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatRupiah } from '@/lib/format';
import type { OpenGroup } from '@/types/billing';

/**
 * Open items of the payer's family that the payment does not pay yet, so the committee can move part of the
 * money to another child or bill while verifying. `used` are the item ids already in the division.
 */
export function PaymentAllocationAdder({
    groups,
    used,
    onAdd,
}: {
    groups: OpenGroup[];
    used: ReadonlySet<number>;
    onAdd: (itemId: number) => void;
}) {
    const available = groups
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => !used.has(item.id)),
        }))
        .filter((group) => group.items.length > 0);

    if (available.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Tidak ada rincian terbuka lain di keluarga ini.
            </p>
        );
    }

    return (
        <div className="grid gap-4">
            {available.map((group) => (
                <fieldset
                    key={group.bill_id}
                    className="grid gap-2 border-t border-line pt-3 first:border-t-0 first:pt-0"
                >
                    <legend className="mb-2 text-sm font-semibold">
                        {group.registrant.child_name} · Tagihan{' '}
                        {group.type_label}
                    </legend>
                    {group.items.map((item) => (
                        <div
                            key={item.id}
                            className="flex items-center justify-between gap-3"
                        >
                            <span className="grid gap-0.5 text-sm">
                                <span className="font-medium">{item.name}</span>
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    Sisa {formatRupiah(item.outstanding)}
                                </span>
                            </span>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                aria-label={`Tambah ${item.name} untuk ${group.registrant.child_name}`}
                                onClick={() => onAdd(item.id)}
                            >
                                <Plus />
                                Tambah
                            </Button>
                        </div>
                    ))}
                </fieldset>
            ))}
        </div>
    );
}
