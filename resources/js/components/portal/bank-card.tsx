import { usePage } from '@inertiajs/react';
import { Check, Copy, Landmark } from 'lucide-react';
import { toast } from 'sonner';
import { SectionCard } from '@/components/section-card';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';

/**
 * Where to send the transfer, taken from the account the school entered in
 * its settings. Renders nothing until an account number is set.
 */
export function BankCard() {
    const { payment } = usePage().props.brand;
    const [copied, copy] = useClipboard();

    if (!payment.account_number) {
        return null;
    }

    async function handleCopy() {
        if (await copy(payment.account_number ?? '')) {
            toast.success('Nomor rekening sudah disalin');
        } else {
            toast.error('Gagal menyalin, coba salin manual ya');
        }
    }

    return (
        <SectionCard title="Transfer ke rekening ini" icon={Landmark}>
            <div className="grid gap-3">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {payment.bank_name ?? 'Rekening'}
                        {payment.account_holder &&
                            ` · a.n. ${payment.account_holder}`}
                    </p>
                    <p className="mt-1 text-2xl font-semibold tracking-tight tabular-nums">
                        {payment.account_number}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    className="w-full"
                    onClick={handleCopy}
                >
                    {copied === payment.account_number ? <Check /> : <Copy />}
                    {copied === payment.account_number
                        ? 'Tersalin'
                        : 'Salin nomor rekening'}
                </Button>
                {payment.instructions && (
                    <p className="text-sm whitespace-pre-line text-muted-foreground">
                        {payment.instructions}
                    </p>
                )}
            </div>
        </SectionCard>
    );
}
