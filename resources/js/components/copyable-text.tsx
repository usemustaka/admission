import { Check, Copy, MessageCircle } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { whatsappUrl } from '@/lib/format';

/**
 * Text block meant to be copied or sent via WhatsApp, e.g. admission
 * announcements to a guardian's phone number.
 */
export function CopyableText({
    text,
    phone,
}: {
    text: string;
    /** When given, shows a button that opens WhatsApp to this number. */
    phone?: string;
}) {
    const [copiedText, copy] = useClipboard();
    const isCopied = copiedText === text;

    async function handleCopy() {
        if (await copy(text)) {
            toast.success('Teks sudah disalin');
        } else {
            toast.error('Gagal menyalin, coba salin manual ya');
        }
    }

    return (
        <div className="grid gap-3 rounded-2xl border bg-sage-50/60 p-4 dark:bg-card">
            <p className="text-sm leading-relaxed whitespace-pre-wrap">
                {text}
            </p>
            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={handleCopy}
                >
                    {isCopied ? <Check /> : <Copy />}
                    {isCopied ? 'Tersalin' : 'Salin teks'}
                </Button>
                {phone && (
                    <Button asChild size="sm">
                        <a
                            href={whatsappUrl(phone, text)}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <MessageCircle />
                            Kirim lewat WhatsApp
                        </a>
                    </Button>
                )}
            </div>
        </div>
    );
}
