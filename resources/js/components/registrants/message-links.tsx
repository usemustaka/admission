import { MessageCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { RegistrantMessage } from '@/types/registrant';

/**
 * WhatsApp messages that fit this registration right now. Each opens WhatsApp with the template filled in, so the
 * committee only has to press send.
 */
export function MessageLinks({ messages }: { messages: RegistrantMessage[] }) {
    if (messages.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-2">
            <p className="text-sm font-semibold">Kirim pesan WhatsApp</p>
            <div className="flex flex-wrap gap-2">
                {messages.map((message) => (
                    <Button
                        key={message.kind}
                        variant="outline"
                        size="sm"
                        asChild
                    >
                        <a href={message.url} target="_blank" rel="noreferrer">
                            <MessageCircle />
                            {message.label}
                        </a>
                    </Button>
                ))}
            </div>
        </div>
    );
}
