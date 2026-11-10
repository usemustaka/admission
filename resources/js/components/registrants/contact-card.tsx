import {
    BadgeCheck,
    KeyRound,
    Mail,
    MailWarning,
    MessageCircle,
    Phone,
} from 'lucide-react';
import { MessageLinks } from '@/components/registrants/message-links';
import type { RegistrantMessage } from '@/types/registrant';
import { SectionCard } from '@/components/section-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { whatsappUrl } from '@/lib/format';

export type ParentAccountData = {
    email: string;
    verified: boolean;
    can_reset: boolean;
};

/**
 * How to reach the family: who to call first, the ready-made WhatsApp messages that fit the registration right now,
 * and the parent's login, with a way to set a new password directly or to send a reset link.
 */
export function ContactCard({
    contact,
    hasFamily,
    identityPending,
    messages,
    account,
    onResetPassword,
    onSetPassword,
}: {
    contact: { name: string; phone: string; label: string } | null;
    hasFamily: boolean;
    identityPending: boolean;
    messages: RegistrantMessage[];
    account: ParentAccountData | null;
    onResetPassword: () => void;
    onSetPassword: () => void;
}) {
    return (
        <SectionCard
            title="Kontak dan akun"
            icon={Phone}
            compact
            contentClassName="grid gap-4"
        >
            {contact ? (
                <div className="grid gap-1.5 text-sm">
                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        {contact.label}
                    </p>
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-semibold">{contact.name}</span>
                        <span className="text-muted-foreground tabular-nums">
                            {contact.phone}
                        </span>
                        <Button
                            variant="outline"
                            size="icon"
                            className="size-8"
                            asChild
                        >
                            <a
                                href={`tel:${contact.phone}`}
                                aria-label={`Telepon ${contact.name}`}
                            >
                                <Phone />
                            </a>
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            className="size-8"
                            asChild
                        >
                            <a
                                href={whatsappUrl(contact.phone)}
                                target="_blank"
                                rel="noreferrer"
                                aria-label={`WhatsApp ${contact.name}`}
                            >
                                <MessageCircle />
                            </a>
                        </Button>
                    </div>
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">
                    {hasFamily
                        ? 'Belum ada kontak utama dengan nomor HP, jadi pengumuman tidak bisa dikirim otomatis.'
                        : 'Belum ada orang tua atau wali yang tertaut.'}
                </p>
            )}

            {identityPending && (
                <p className="text-xs text-muted-foreground">
                    Keluarga yang tercatat disembunyikan sampai NIK dicocokkan.
                </p>
            )}

            {messages.length > 0 && (
                <div className="border-t border-line pt-4">
                    <MessageLinks messages={messages} />
                </div>
            )}

            {account && (
                <div className="grid gap-2 border-t border-line pt-4 text-sm">
                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Akun orang tua
                    </p>
                    <p className="flex flex-wrap items-center gap-2">
                        <span className="font-medium break-all">
                            {account.email}
                        </span>
                        {account.verified ? (
                            <Badge variant="success">
                                <BadgeCheck />
                                Email terverifikasi
                            </Badge>
                        ) : (
                            <Badge variant="warning">
                                <MailWarning />
                                Email belum diverifikasi
                            </Badge>
                        )}
                    </p>
                    {account.can_reset && (
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={onSetPassword}
                            >
                                <KeyRound />
                                Atur password langsung
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={onResetPassword}
                            >
                                <Mail />
                                Kirim tautan lewat email
                            </Button>
                        </div>
                    )}
                </div>
            )}
        </SectionCard>
    );
}
