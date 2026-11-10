import { Link } from '@inertiajs/react';
import {
    ArrowLeftRight,
    ArrowRightLeft,
    BadgeCheck,
    CheckCircle2,
    CircleAlert,
    GraduationCap,
    Megaphone,
    MessageCircle,
    MessageSquareWarning,
    PencilLine,
    SkipForward,
    Trash2,
} from 'lucide-react';
import { SectionCard } from '@/components/section-card';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { RegistrantAbilities } from '@/types/registrant';

type Guidance = { text: string };

function guidance(status: string, needsIdentityReview: boolean): Guidance {
    if (needsIdentityReview) {
        return {
            text: 'Selama NIK belum dicocokkan, data ini belum bisa diterima atau diumumkan.',
        };
    }

    switch (status) {
        case 'review':
            return {
                text: 'Periksa datanya. Kalau sudah benar, terima. Kalau ada yang salah, kembalikan ke orang tua.',
            };
        case 'draft':
            return {
                text: 'Orang tua masih melengkapi data. Kamu bisa membantu lewat Ubah data.',
            };
        case 'revision':
            return {
                text: 'Menunggu orang tua memperbaiki data sesuai catatan revisi.',
            };
        case 'validated':
            return {
                text: 'Kirim pengumuman ke kontak utama, lalu tandai sudah diumumkan.',
            };
        case 'announced':
            return {
                text: 'Hasil sudah diumumkan. Tempatkan anak ini ke tahun ajaran.',
            };
        default:
            return { text: 'Anak ini sudah tercatat sebagai siswa.' };
    }
}

/**
 * The card at the top of the detail rail: what the committee does next with this registrant, the actions that
 * do it, and what is still missing from the data. Every action is handed in; this card only decides what to show.
 */
export function NextStepCard({
    status,
    needsIdentityReview,
    can,
    readiness,
    hasNext,
    processing,
    editHref,
    whatsappUrl,
    onAccept,
    onAnnounce,
    onRevise,
    onPlace,
    onBypass,
    onDelete,
    onResolveIdentity,
    onRepointIdentity,
    correctIdentityHref,
}: {
    status: string;
    needsIdentityReview: boolean;
    can: RegistrantAbilities;
    readiness: string[];
    /** Whether another registrant waits in the same status queue. */
    hasNext: boolean;
    processing: boolean;
    editHref: string;
    whatsappUrl: string | null;
    onAccept: (goNext: boolean) => void;
    onAnnounce: (goNext: boolean) => void;
    onRevise: () => void;
    onPlace: () => void;
    onBypass: () => void;
    onDelete: () => void;
    onResolveIdentity: () => void;
    onRepointIdentity: () => void;
    correctIdentityHref: string;
}) {
    const checklistVisible = [
        'draft',
        'revision',
        'review',
        'validated',
    ].includes(status);
    const parentFills = status === 'draft' || status === 'revision';

    return (
        <SectionCard
            title="Langkah berikutnya"
            description={guidance(status, needsIdentityReview).text}
            contentClassName="grid gap-3"
        >
            {!can.manage && (
                <p className="text-sm text-muted-foreground">
                    Kamu hanya bisa melihat data ini, tidak bisa memprosesnya.
                </p>
            )}

            {can.manage && needsIdentityReview && (
                <>
                    <Button className="w-full" onClick={onResolveIdentity}>
                        <BadgeCheck />
                        NIK sudah cocok
                    </Button>
                    <Button
                        variant="outline"
                        className="w-full"
                        onClick={onRepointIdentity}
                    >
                        <ArrowRightLeft />
                        Ganti NIK pendaftaran
                    </Button>
                    <Button variant="ghost" className="w-full" asChild>
                        <Link href={correctIdentityHref}>
                            Koreksi nama atau NIK anak
                        </Link>
                    </Button>
                </>
            )}

            {can.accept && (
                <>
                    <Button
                        className="w-full"
                        disabled={processing}
                        onClick={() => onAccept(false)}
                    >
                        {processing ? <Spinner /> : <BadgeCheck />}
                        Terima data
                    </Button>
                    {hasNext && (
                        <Button
                            variant="dark"
                            className="w-full"
                            disabled={processing}
                            onClick={() => onAccept(true)}
                        >
                            <SkipForward />
                            Terima dan buka berikutnya
                        </Button>
                    )}
                </>
            )}
            {can.revise && (
                <Button variant="outline" className="w-full" onClick={onRevise}>
                    <MessageSquareWarning />
                    Minta revisi
                </Button>
            )}

            {can.announce && (
                <>
                    {whatsappUrl && (
                        <Button variant="outline" className="w-full" asChild>
                            <a
                                href={whatsappUrl}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <MessageCircle />
                                Kirim lewat WhatsApp
                            </a>
                        </Button>
                    )}
                    <Button
                        className="w-full"
                        disabled={processing}
                        onClick={() => onAnnounce(false)}
                    >
                        {processing ? <Spinner /> : <Megaphone />}
                        Tandai sudah diumumkan
                    </Button>
                    {hasNext && (
                        <Button
                            variant="dark"
                            className="w-full"
                            disabled={processing}
                            onClick={() => onAnnounce(true)}
                        >
                            <SkipForward />
                            Tandai dan buka berikutnya
                        </Button>
                    )}
                </>
            )}

            {can.place && (
                <Button className="w-full" onClick={onPlace}>
                    <GraduationCap />
                    Tempatkan siswa
                </Button>
            )}

            {can.manage && (
                <div className="flex flex-wrap gap-2 border-t border-line pt-3">
                    <Button variant="outline" asChild>
                        <Link href={editHref}>
                            <PencilLine />
                            Ubah data
                        </Link>
                    </Button>
                    {can.bypass && (
                        <Button variant="ghost" onClick={onBypass}>
                            <ArrowLeftRight />
                            Lompati status
                        </Button>
                    )}
                </div>
            )}

            {can.delete && (
                <div className="border-t border-line pt-3">
                    <Button
                        variant="ghost"
                        className="text-destructive hover:text-destructive"
                        onClick={onDelete}
                    >
                        <Trash2 />
                        Hapus pendaftar
                    </Button>
                </div>
            )}

            {checklistVisible && (
                <div className="grid gap-2 border-t border-line pt-3">
                    <h3 className="text-sm font-semibold">Kelengkapan data</h3>
                    {readiness.length === 0 ? (
                        <p className="flex items-center gap-2 text-sm text-emerald-700 dark:text-emerald-300">
                            <CheckCircle2 className="size-4 shrink-0" />
                            {parentFills
                                ? 'Data sudah lengkap, siap dikirim orang tua.'
                                : 'Data sudah lengkap.'}
                        </p>
                    ) : (
                        <>
                            <ul className="grid gap-1.5 text-sm">
                                {readiness.map((gap) => (
                                    <li key={gap} className="flex gap-2">
                                        <CircleAlert className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-300" />
                                        {gap}
                                    </li>
                                ))}
                            </ul>
                            {can.manage && !needsIdentityReview && (
                                <Link
                                    href={editHref}
                                    className="w-fit text-sm font-semibold underline underline-offset-4"
                                >
                                    Lengkapi lewat Ubah data
                                </Link>
                            )}
                        </>
                    )}
                </div>
            )}
        </SectionCard>
    );
}
