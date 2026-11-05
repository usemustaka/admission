import { MessageCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { whatsappUrl } from '@/lib/format';

/**
 * Small button that opens a WhatsApp chat with the given number.
 */
export function WhatsAppLink({
    phone,
    label,
    variant = 'outline',
}: {
    phone: string;
    /** Spoken name of who gets the message, e.g. "Ibu Siti". */
    label: string;
    variant?: 'outline' | 'ghost';
}) {
    return (
        <Button
            variant={variant}
            size="icon-sm"
            className="max-md:size-10"
            asChild
        >
            <a
                href={whatsappUrl(phone)}
                target="_blank"
                rel="noreferrer"
                aria-label={`Kirim WhatsApp ke ${label}`}
                title="Kirim WhatsApp"
            >
                <MessageCircle />
            </a>
        </Button>
    );
}
