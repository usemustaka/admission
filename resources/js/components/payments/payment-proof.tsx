import { FileText, ImageOff, Paperclip } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { proof } from '@/routes/payments';

/**
 * The proof of a payment as the committee checks it: the picture itself when it is an image, a link when it is a
 * PDF, and a plain note when nothing was attached.
 */
export function PaymentProof({
    paymentId,
    hasProof,
    isImage,
    isTransfer,
}: {
    paymentId: number;
    hasProof: boolean;
    isImage: boolean;
    /** A transfer without proof deserves a second look; cash does not need one. */
    isTransfer: boolean;
}) {
    const [broken, setBroken] = useState(false);
    const url = proof(paymentId).url;

    if (!hasProof) {
        return (
            <div className="flex items-start gap-3 rounded-2xl border border-dashed border-neutral-300 p-4 text-sm dark:border-border">
                <Paperclip className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                <p className="text-muted-foreground">
                    {isTransfer
                        ? 'Tidak ada bukti. Cocokkan dulu dengan mutasi rekening sebelum memverifikasi.'
                        : 'Tidak ada bukti, biasanya karena dibayar tunai.'}
                </p>
            </div>
        );
    }

    if (!isImage || broken) {
        return (
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-line p-4">
                <span className="flex items-center gap-3 text-sm">
                    {broken ? (
                        <ImageOff className="size-5 text-muted-foreground" />
                    ) : (
                        <FileText className="size-5 text-muted-foreground" />
                    )}
                    <span className="font-medium">
                        {broken
                            ? 'Gambar tidak bisa ditampilkan'
                            : 'Bukti berupa PDF'}
                    </span>
                </span>
                <Button variant="outline" size="sm" asChild>
                    <a href={url} target="_blank" rel="noreferrer">
                        Buka bukti
                    </a>
                </Button>
            </div>
        );
    }

    return (
        <a
            href={url}
            target="_blank"
            rel="noreferrer"
            className="group grid gap-2 rounded-2xl outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >
            <img
                src={url}
                alt="Bukti pembayaran"
                loading="lazy"
                onError={() => setBroken(true)}
                className="max-h-80 w-full rounded-2xl border border-line bg-neutral-50 object-contain dark:bg-card"
            />
            <span className="text-xs text-muted-foreground group-hover:text-foreground">
                Klik untuk membuka ukuran penuh
            </span>
        </a>
    );
}
