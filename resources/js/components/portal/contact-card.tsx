import { usePage } from '@inertiajs/react';
import { Globe, Mail, MapPin, MessageCircle, Phone } from 'lucide-react';
import { SectionCard } from '@/components/section-card';
import { Button } from '@/components/ui/button';
import { whatsappUrl } from '@/lib/format';

/**
 * "Butuh bantuan?" with the school's contact details from its settings.
 * Renders nothing when none were entered.
 */
export function ContactCard() {
    const { contact, organization, name } = usePage().props.brand;
    const hasAny = Object.values(contact).some(Boolean);

    if (!hasAny) {
        return null;
    }

    return (
        <SectionCard
            title="Butuh bantuan?"
            description={`Hubungi panitia ${organization ?? name}.`}
        >
            <div className="grid gap-3">
                {contact.whatsapp && (
                    <Button asChild size="lg" className="w-full">
                        <a
                            href={whatsappUrl(contact.whatsapp)}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <MessageCircle />
                            Chat lewat WhatsApp
                        </a>
                    </Button>
                )}
                <ul className="grid gap-2.5 text-[15px]">
                    {contact.phone && (
                        <li className="flex items-center gap-3">
                            <Phone className="size-4 shrink-0 text-muted-foreground" />
                            <a
                                href={`tel:${contact.phone.replace(/\s/gu, '')}`}
                                className="underline-offset-4 hover:underline"
                            >
                                {contact.phone}
                            </a>
                        </li>
                    )}
                    {contact.email && (
                        <li className="flex items-center gap-3">
                            <Mail className="size-4 shrink-0 text-muted-foreground" />
                            <a
                                href={`mailto:${contact.email}`}
                                className="min-w-0 truncate underline-offset-4 hover:underline"
                            >
                                {contact.email}
                            </a>
                        </li>
                    )}
                    {contact.website && (
                        <li className="flex items-center gap-3">
                            <Globe className="size-4 shrink-0 text-muted-foreground" />
                            <a
                                href={contact.website}
                                target="_blank"
                                rel="noreferrer"
                                className="min-w-0 truncate underline-offset-4 hover:underline"
                            >
                                {contact.website.replace(/^https?:\/\//u, '')}
                            </a>
                        </li>
                    )}
                    {contact.address && (
                        <li className="flex items-start gap-3">
                            <MapPin className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                            <span className="whitespace-pre-line">
                                {contact.address}
                            </span>
                        </li>
                    )}
                </ul>
            </div>
        </SectionCard>
    );
}
