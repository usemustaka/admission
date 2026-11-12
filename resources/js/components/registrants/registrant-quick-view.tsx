import { Link, router } from '@inertiajs/react';
import {
    BadgeCheck,
    ExternalLink,
    GraduationCap,
    Megaphone,
    MessageCircle,
    MessageSquareWarning,
    Phone,
    RefreshCw,
    ShieldAlert,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { PersonAvatar } from '@/components/person-avatar';
import { QuickView } from '@/components/quick-view';
import { AnnounceWarnings } from '@/components/registrants/announce-warnings';
import { RelativeTime } from '@/components/registrants/relative-time';
import { StatusBadge } from '@/components/status-badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { MessageLinks } from '@/components/registrants/message-links';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { toastFirstError } from '@/hooks/use-flash-toast';
import { formatDate, formatRupiah } from '@/lib/format';
import { accept, announce, quickView, show } from '@/routes/admin/registrants';
import type { RegistrantQuickViewData } from '@/types/registrant';

type Loaded = { id: number; data: RegistrantQuickViewData | null };

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="grid gap-3 border-t border-line pt-5">
            <h3 className="text-sm font-semibold">{title}</h3>
            {children}
        </section>
    );
}

function QuickViewSkeleton() {
    return (
        <div className="grid gap-6" aria-busy="true" aria-label="Memuat">
            <div className="flex items-center gap-4">
                <Skeleton className="size-14 rounded-full" />
                <div className="grid flex-1 gap-2">
                    <Skeleton className="h-5 w-2/3" />
                    <Skeleton className="h-4 w-1/2" />
                </div>
            </div>
            <Skeleton className="h-24 w-full rounded-2xl" />
            <Skeleton className="h-20 w-full rounded-2xl" />
            <Skeleton className="h-28 w-full rounded-2xl" />
        </div>
    );
}

function ContactLinks({
    phone,
    whatsappUrl,
    name,
}: {
    phone: string | null;
    whatsappUrl: string | null;
    name: string;
}) {
    if (!phone) {
        return (
            <span className="text-sm text-muted-foreground">
                Belum ada nomor HP
            </span>
        );
    }

    return (
        <span className="flex items-center gap-1.5">
            <a
                href={`tel:${phone}`}
                className="rounded-lg text-sm font-medium tabular-nums outline-none hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                {phone}
            </a>
            <Button
                variant="outline"
                size="icon"
                className="size-10 md:size-8"
                asChild
            >
                <a href={`tel:${phone}`} aria-label={`Telepon ${name}`}>
                    <Phone />
                </a>
            </Button>
            {whatsappUrl && (
                <Button
                    variant="outline"
                    size="icon"
                    className="size-10 md:size-8"
                    asChild
                >
                    <a
                        href={whatsappUrl}
                        target="_blank"
                        rel="noreferrer"
                        aria-label={`WhatsApp ${name}`}
                    >
                        <MessageCircle />
                    </a>
                </Button>
            )}
        </span>
    );
}

function QuickViewBody({ data }: { data: RegistrantQuickViewData }) {
    const { child, bills, exam } = data;

    return (
        <div className="grid gap-5">
            <div className="flex items-center gap-4">
                <PersonAvatar
                    name={child.name}
                    src={child.photo_url}
                    size="lg"
                />
                <div className="grid min-w-0 gap-1">
                    <p className="truncate text-lg font-semibold">
                        {child.name}
                    </p>
                    <p className="text-sm text-muted-foreground tabular-nums">
                        {child.nik ?? 'Belum ada NIK'}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {data.organization} · {data.wave}
                    </p>
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <StatusBadge status={data.status} />
                {data.needs_identity_review && (
                    <Badge variant="danger">
                        <ShieldAlert />
                        Cek NIK
                    </Badge>
                )}
                <span className="text-sm text-muted-foreground">
                    Mendaftar <RelativeTime value={data.registered_at} />
                </span>
            </div>

            {data.needs_identity_review && (
                <Alert variant="warning">
                    <ShieldAlert />
                    <AlertDescription>
                        NIK sudah ada, tapi namanya berbeda. Cocokkan dulu di
                        halaman detail sebelum data diproses.
                    </AlertDescription>
                </Alert>
            )}

            <Section title="Orang tua dan wali">
                {data.needs_identity_review && data.contact ? (
                    <div className="grid gap-1.5 text-sm">
                        <span className="flex flex-wrap items-center gap-2">
                            <span className="font-semibold">
                                {data.contact.name}
                            </span>
                            <Badge variant="info">{data.contact.label}</Badge>
                        </span>
                        <ContactLinks
                            phone={data.contact.phone}
                            whatsappUrl={data.contact.whatsapp_url}
                            name={data.contact.name}
                        />
                    </div>
                ) : data.family.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Belum ada orang tua atau wali yang tertaut.
                    </p>
                ) : (
                    <ul className="grid gap-4">
                        {data.family.map((member) => (
                            <li
                                key={member.id}
                                className="grid gap-1.5 text-sm"
                            >
                                <span className="flex flex-wrap items-center gap-2">
                                    <span className="font-semibold">
                                        {member.name}
                                    </span>
                                    <Badge variant="muted">
                                        {member.relation}
                                    </Badge>
                                    {member.is_primary_contact && (
                                        <Badge variant="info">
                                            Kontak utama
                                        </Badge>
                                    )}
                                </span>
                                <ContactLinks
                                    phone={member.phone}
                                    whatsappUrl={member.whatsapp_url}
                                    name={member.name}
                                />
                            </li>
                        ))}
                    </ul>
                )}
            </Section>

            <MessageLinks messages={data.messages} />

            <Section title="Tagihan">
                {bills.status === 'none' ? (
                    <p className="text-sm text-muted-foreground">
                        Belum ada tagihan terbit.
                    </p>
                ) : (
                    <div className="grid gap-3">
                        <div className="flex items-center justify-between gap-3">
                            <StatusBadge status={bills.status} />
                            {bills.pending > 0 && (
                                <span className="text-sm text-muted-foreground tabular-nums">
                                    {formatRupiah(bills.pending)} menunggu
                                    verifikasi
                                </span>
                            )}
                        </div>
                        <dl className="grid grid-cols-3 gap-3 text-sm">
                            {[
                                ['Total', bills.total],
                                ['Dibayar', bills.paid],
                                ['Sisa', bills.outstanding],
                            ].map(([label, amount]) => (
                                <div key={label} className="grid gap-0.5">
                                    <dt className="text-xs text-muted-foreground">
                                        {label}
                                    </dt>
                                    <dd className="font-semibold tabular-nums">
                                        {formatRupiah(Number(amount))}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                        <ul className="grid gap-1.5 text-sm text-muted-foreground">
                            {bills.items.map((bill) => (
                                <li
                                    key={bill.id}
                                    className="flex justify-between gap-3 tabular-nums"
                                >
                                    <span>{bill.type_label}</span>
                                    <span>
                                        {formatRupiah(bill.paid)} /{' '}
                                        {formatRupiah(bill.total)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </Section>

            <Section title="Ujian">
                <p className="text-sm">
                    {exam.session ? (
                        <>
                            <span className="font-semibold">
                                {formatDate(exam.session.date, {
                                    weekday: 'long',
                                    day: 'numeric',
                                    month: 'long',
                                })}
                            </span>
                            , {exam.session.label}
                        </>
                    ) : (
                        <span className="text-muted-foreground">
                            Belum memilih sesi ujian
                        </span>
                    )}
                </p>
                {exam.result.aspects.length > 0 && (
                    <ul className="grid gap-2 text-sm">
                        {exam.result.aspects.map((aspect) => (
                            <li
                                key={aspect.name}
                                className="flex items-center justify-between gap-3"
                            >
                                <span>{aspect.name}</span>
                                <span className="flex items-center gap-2 tabular-nums">
                                    {aspect.score === null
                                        ? 'Belum dinilai'
                                        : `${aspect.score_label} (KKM ${aspect.passing_label})`}
                                    {aspect.passed !== null && (
                                        <Badge
                                            variant={
                                                aspect.passed
                                                    ? 'success'
                                                    : 'danger'
                                            }
                                        >
                                            {aspect.passed
                                                ? 'Tuntas'
                                                : 'Belum tuntas'}
                                        </Badge>
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </Section>

            {data.readiness.length > 0 && !data.needs_identity_review && (
                <Section title="Yang masih kurang">
                    <ul className="grid list-disc gap-1 pl-5 text-sm">
                        {data.readiness.map((gap) => (
                            <li key={gap}>{gap}</li>
                        ))}
                    </ul>
                </Section>
            )}

            <Section title="Riwayat terakhir">
                {data.timeline.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Belum ada perubahan status.
                    </p>
                ) : (
                    <ol className="grid gap-3">
                        {data.timeline.map((entry) => (
                            <li key={entry.id} className="grid gap-0.5 text-sm">
                                <span className="flex flex-wrap items-center gap-2 font-semibold">
                                    {entry.to_label}
                                    {entry.is_bypass && (
                                        <Badge variant="warning">
                                            Dilompati
                                        </Badge>
                                    )}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    <RelativeTime value={entry.at} />
                                    {entry.by && ` · ${entry.by}`}
                                </span>
                                {entry.note && (
                                    <span className="text-muted-foreground">
                                        {entry.note}
                                    </span>
                                )}
                            </li>
                        ))}
                    </ol>
                )}
            </Section>
        </div>
    );
}

/**
 * Side panel of the registrant list. It fetches a short summary when it opens and offers the workflow steps
 * that need no extra input; anything that asks the committee for a note or a year goes to the detail page.
 */
export function RegistrantQuickView({
    registrantId,
    open,
    onOpenChange,
    onChanged,
    detailQuery,
}: {
    registrantId: number | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Called after a workflow step succeeded, so the list can refresh. */
    onChanged?: () => void;
    /** The list filters and order, passed on to the detail page so its queue follows the list. */
    detailQuery?: Record<string, string | number | boolean | undefined>;
}) {
    const [loaded, setLoaded] = useState<Loaded | null>(null);
    const [reloadKey, setReloadKey] = useState(0);
    const [processing, setProcessing] = useState<'accept' | 'announce' | null>(
        null,
    );
    const [confirming, setConfirming] = useState<'accept' | 'announce' | null>(
        null,
    );

    useEffect(() => {
        if (registrantId === null) {
            return;
        }

        const controller = new AbortController();

        fetch(quickView(registrantId).url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                return response.json() as Promise<RegistrantQuickViewData>;
            })
            .then((data) => setLoaded({ id: registrantId, data }))
            .catch((error: unknown) => {
                if ((error as Error).name !== 'AbortError') {
                    setLoaded({ id: registrantId, data: null });
                }
            });

        return () => controller.abort();
    }, [registrantId, reloadKey]);

    const isLoading = loaded === null || loaded.id !== registrantId;
    const data = isLoading ? null : loaded.data;

    function run(step: 'accept' | 'announce') {
        if (registrantId === null) {
            return;
        }

        setProcessing(step);
        router.post(
            (step === 'accept' ? accept : announce)(registrantId).url,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setReloadKey((key) => key + 1);
                    onChanged?.();
                },
                onError: (errors) => {
                    toastFirstError(errors);
                    setReloadKey((key) => key + 1);
                },
                onFinish: () => {
                    setProcessing(null);
                    setConfirming(null);
                },
            },
        );
    }

    const footer =
        registrantId === null ? null : (
            <>
                {data?.can.accept && (
                    <Button
                        disabled={processing !== null}
                        onClick={() => setConfirming('accept')}
                    >
                        {processing === 'accept' ? <Spinner /> : <BadgeCheck />}
                        Terima data
                    </Button>
                )}
                {data?.can.announce && (
                    <Button
                        disabled={processing !== null}
                        onClick={() => setConfirming('announce')}
                    >
                        {processing === 'announce' ? (
                            <Spinner />
                        ) : (
                            <Megaphone />
                        )}
                        Tandai sudah diumumkan
                    </Button>
                )}
                {data?.can.revise && (
                    <Button variant="outline" asChild>
                        <Link href={show(registrantId, { query: detailQuery })}>
                            <MessageSquareWarning />
                            Minta revisi
                        </Link>
                    </Button>
                )}
                {data?.can.place && (
                    <Button variant="outline" asChild>
                        <Link href={show(registrantId, { query: detailQuery })}>
                            <GraduationCap />
                            Tempatkan siswa
                        </Link>
                    </Button>
                )}
                <Button variant="outline" asChild>
                    <Link href={show(registrantId, { query: detailQuery })}>
                        <ExternalLink />
                        Buka detail
                    </Link>
                </Button>
            </>
        );

    return (
        <>
            <QuickView
                open={open}
                onOpenChange={onOpenChange}
                title={data?.child.name ?? 'Ringkasan pendaftar'}
                description="Ringkasan data pendaftar"
                footer={footer}
            >
                {isLoading ? (
                    <QuickViewSkeleton />
                ) : data === null ? (
                    <div className="grid justify-items-start gap-3">
                        <Alert variant="destructive">
                            <ShieldAlert />
                            <AlertDescription>
                                Ringkasan belum bisa dimuat. Coba lagi, atau
                                buka halaman detailnya.
                            </AlertDescription>
                        </Alert>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setLoaded(null);
                                setReloadKey((key) => key + 1);
                            }}
                        >
                            <RefreshCw />
                            Coba lagi
                        </Button>
                    </div>
                ) : (
                    <QuickViewBody data={data} />
                )}
            </QuickView>
            <ConfirmDialog
                open={confirming !== null}
                onOpenChange={(next) => !next && setConfirming(null)}
                title={
                    confirming === 'accept'
                        ? 'Terima data ini?'
                        : 'Tandai sudah diumumkan?'
                }
                description={
                    confirming === 'accept'
                        ? 'Data dinyatakan valid dan tagihan pelunasan terbit kalau aturannya punya.'
                        : 'Pastikan pengumumannya sudah dikirim ke kontak utama. Setelah ditandai, pendaftar lanjut ke tahap penempatan.'
                }
                confirmLabel={
                    confirming === 'accept' ? 'Ya, terima' : 'Ya, tandai'
                }
                processing={processing !== null}
                onConfirm={() => confirming && run(confirming)}
            >
                {confirming === 'announce' && data && (
                    <AnnounceWarnings warnings={data.announce_warnings} />
                )}
            </ConfirmDialog>
        </>
    );
}
