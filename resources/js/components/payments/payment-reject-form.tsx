import { useState } from 'react';
import { FormField } from '@/components/form-field';
import { REJECTION_REASONS } from '@/components/payments/payment-format';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

/**
 * Asks why a payment is turned down. The reason is what the parent reads, so it is required; a few common
 * reasons fill the box in one tap.
 */
export function PaymentRejectForm({
    error,
    processing,
    onCancel,
    onSubmit,
}: {
    error?: string;
    processing: boolean;
    onCancel: () => void;
    onSubmit: (reason: string) => void;
}) {
    const [reason, setReason] = useState('');

    return (
        <form
            className="grid gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                onSubmit(reason.trim());
            }}
        >
            <FormField
                label="Alasan penolakan"
                hint="Orang tua akan membaca ini dan bisa mengirim ulang."
                error={error}
                required
            >
                {(control) => (
                    <Textarea
                        autoFocus
                        maxLength={500}
                        className="min-h-20"
                        placeholder="Contoh: bukti transfer tidak terbaca"
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                        {...control}
                    />
                )}
            </FormField>

            <div className="flex flex-wrap gap-2" aria-label="Alasan umum">
                {REJECTION_REASONS.map((suggestion) => (
                    <button
                        key={suggestion}
                        type="button"
                        onClick={() => setReason(suggestion)}
                        className="min-h-9 rounded-full border border-neutral-300 px-3 text-[13px] font-medium outline-none hover:border-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring dark:border-neutral-600"
                    >
                        {suggestion}
                    </button>
                ))}
            </div>

            <div className="flex flex-wrap justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    disabled={processing}
                    onClick={onCancel}
                >
                    Batal
                </Button>
                <Button
                    type="submit"
                    variant="destructive"
                    disabled={processing || reason.trim() === ''}
                >
                    {processing && <Spinner />}
                    Tolak pembayaran
                </Button>
            </div>
        </form>
    );
}
