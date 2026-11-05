import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowUpRight,
    CalendarClock,
    CheckCircle2,
    ChevronRight,
    ClipboardCheck,
    History,
    Megaphone,
    Plus,
    ShieldAlert,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { formatDate, formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import {
    create as createPayment,
    index as payments,
} from '@/routes/admin/payments';
import {
    index as registrants,
    show as showRegistrant,
} from '@/routes/admin/registrants';
import { index as waves } from '@/routes/admin/waves';

type Tone = 'warning' | 'info' | 'danger' | 'success';

type AttentionQueue = {
    key: string;
    label: string;
    hint: string;
    count: number;
    amount: number | null;
    tone: Tone;
};

type Props = {
    attention: AttentionQueue[];
    flow: {
        total: number;
        steps: { status: string; label: string; count: number }[];
    } | null;
    registrations: { last_week: number; previous_week: number } | null;
    payments: {
        pending_count: number;
        pending_amount: number;
        verified_week_amount: number;
        rejected_week_count: number;
    } | null;
    exams: {
        id: number;
        date: string;
        label: string;
        time: string;
        taken: number;
        quota: number;
    }[];
    waves: {
        id: number;
        name: string;
        ends_on: string | null;
        days_left: number | null;
        registrants: number;
    }[];
    activity: {
        id: number;
        registrant_id: number;
        child: string;
        to_status: string;
        to_label: string;
        note: string | null;
        is_bypass: boolean;
        by: string | null;
        at: string;
    }[];
};

const queueIcon: Record<string, LucideIcon> = {
    pending_payments: Wallet,
    in_review: ClipboardCheck,
    needs_identity_review: ShieldAlert,
    to_announce: Megaphone,
    to_place: CheckCircle2,
};

const toneChip: Record<Tone, string> = {
    warning:
        'bg-amber-50 text-amber-700 dark:bg-amber-400/15 dark:text-amber-300',
    info: 'bg-sky-50 text-sky-700 dark:bg-sky-400/15 dark:text-sky-300',
    danger: 'bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-300',
    success:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300',
};

/** Segment colours of the flow bar, in flow order. Labels sit beside each. */
const flowColor: Record<string, string> = {
    draft: 'bg-neutral-300 dark:bg-neutral-600',
    review: 'bg-sky-400',
    revision: 'bg-amber-400',
    validated: 'bg-emerald-400',
    announced: 'bg-lime-400',
    placed: 'bg-neutral-900 dark:bg-neutral-100',
};

function queueHref(key: string) {
    switch (key) {
        case 'pending_payments':
            return payments({ query: { status: 'pending' } });
        case 'in_review':
            return registrants({ query: { status: 'review' } });
        case 'needs_identity_review':
            return registrants({ query: { perlu_dicek: 1 } });
        case 'to_announce':
            return registrants({ query: { status: 'validated' } });
        default:
            return registrants({ query: { status: 'announced' } });
    }
}

function greeting(): string {
    const hour = new Date().getHours();

    if (hour < 11) {
        return 'Selamat pagi';
    }

    if (hour < 15) {
        return 'Selamat siang';
    }

    return hour < 18 ? 'Selamat sore' : 'Selamat malam';
}

function relativeTime(iso: string): string {
    const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60000);

    if (minutes < 1) {
        return 'baru saja';
    }

    if (minutes < 60) {
        return `${minutes} menit lalu`;
    }

    const hours = Math.round(minutes / 60);

    if (hours < 24) {
        return `${hours} jam lalu`;
    }

    return `${Math.round(hours / 24)} hari lalu`;
}

function AttentionList({ queues }: { queues: AttentionQueue[] }) {
    const open = queues.filter((queue) => queue.count > 0);

    return (
        <SectionCard
            title="Perlu tindakan"
            description="Antrean yang menunggu panitia. Klik untuk langsung membuka daftar yang sudah terfilter."
            contentClassName="grid gap-1"
        >
            {open.length === 0 && (
                <Alert variant="success">
                    <CheckCircle2 />
                    <AlertTitle>Semua antrean bersih</AlertTitle>
                    <AlertDescription>
                        Tidak ada yang menunggu panitia saat ini.
                    </AlertDescription>
                </Alert>
            )}

            {queues.map((queue) => {
                const Icon = queueIcon[queue.key] ?? ClipboardCheck;
                const isEmpty = queue.count === 0;

                return (
                    <Link
                        key={queue.key}
                        href={queueHref(queue.key)}
                        prefetch
                        className={cn(
                            '-mx-2 flex items-center gap-4 rounded-2xl px-2 py-3 outline-none hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                            isEmpty && 'opacity-60',
                        )}
                    >
                        <span
                            className={cn(
                                'grid size-11 shrink-0 place-items-center rounded-full',
                                isEmpty
                                    ? 'bg-neutral-100 text-muted-foreground dark:bg-neutral-700/50'
                                    : toneChip[queue.tone],
                            )}
                        >
                            <Icon className="size-5" />
                        </span>
                        <span className="min-w-0 flex-1">
                            <span className="block font-semibold">
                                {queue.label}
                            </span>
                            <span className="block text-sm text-pretty text-muted-foreground">
                                {isEmpty ? 'Tidak ada antrean' : queue.hint}
                            </span>
                        </span>
                        <span className="text-right">
                            <span className="block text-2xl leading-none font-semibold tabular-nums">
                                {queue.count}
                            </span>
                            {queue.amount !== null && queue.count > 0 && (
                                <span className="mt-1 block text-xs text-muted-foreground tabular-nums">
                                    {formatRupiah(queue.amount)}
                                </span>
                            )}
                        </span>
                        <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                    </Link>
                );
            })}
        </SectionCard>
    );
}

function FlowCard({ flow }: { flow: NonNullable<Props['flow']> }) {
    return (
        <SectionCard
            title="Alur pendaftar"
            description={`${flow.total} pendaftar di semua lembaga dan gelombang.`}
        >
            {flow.total === 0 ? (
                <p className="text-[15px] text-muted-foreground">
                    Belum ada pendaftar.
                </p>
            ) : (
                <div className="grid gap-5">
                    <div
                        className="flex h-3 gap-0.5 overflow-hidden rounded-full"
                        role="img"
                        aria-label="Sebaran pendaftar per tahap"
                    >
                        {flow.steps
                            .filter((step) => step.count > 0)
                            .map((step) => (
                                <span
                                    key={step.status}
                                    className={cn(
                                        'first:rounded-l-full last:rounded-r-full',
                                        flowColor[step.status],
                                    )}
                                    style={{
                                        width: `${(step.count / flow.total) * 100}%`,
                                    }}
                                />
                            ))}
                    </div>

                    <ul className="grid gap-0.5">
                        {flow.steps.map((step) => (
                            <li key={step.status}>
                                <Link
                                    href={registrants({
                                        query: { status: step.status },
                                    })}
                                    prefetch
                                    className="-mx-2 flex items-center gap-3 rounded-xl px-2 py-2 text-[15px] outline-none hover:bg-accent focus-visible:outline-2 focus-visible:outline-ring"
                                >
                                    <span
                                        className={cn(
                                            'size-3 shrink-0 rounded-full',
                                            flowColor[step.status],
                                        )}
                                    />
                                    <span className="flex-1">{step.label}</span>
                                    <span className="font-semibold tabular-nums">
                                        {step.count}
                                    </span>
                                    <span className="w-10 text-right text-sm text-muted-foreground tabular-nums">
                                        {Math.round(
                                            (step.count / flow.total) * 100,
                                        )}
                                        %
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </SectionCard>
    );
}

function Trend({ now, before }: { now: number; before: number }) {
    const delta = now - before;

    if (delta === 0) {
        return (
            <span className="text-muted-foreground">
                sama dengan pekan lalu
            </span>
        );
    }

    const Icon = delta > 0 ? ArrowUpRight : ArrowDownRight;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-0.5 font-semibold',
                delta > 0
                    ? 'text-emerald-700 dark:text-emerald-300'
                    : 'text-amber-700 dark:text-amber-300',
            )}
        >
            <Icon className="size-4" />
            {delta > 0 ? 'Naik' : 'Turun'} {Math.abs(delta)} dibanding pekan
            lalu
        </span>
    );
}

export default function Dashboard({
    attention,
    flow,
    registrations,
    payments: paymentSummary,
    exams,
    waves: openWaves,
    activity,
}: Props) {
    const { auth } = usePage().props;
    const firstName = auth.user.name.split(' ')[0];
    const can = (ability: string) => auth.abilities.includes(ability);

    return (
        <>
            <Head title="Beranda" />
            <div className="grid gap-6">
                <PageHeader
                    title={`${greeting()}, ${firstName}`}
                    description={
                        registrations
                            ? `${registrations.last_week} pendaftar baru dalam 7 hari terakhir.`
                            : 'Ringkasan pekerjaan panitia hari ini.'
                    }
                    meta={
                        registrations && (
                            <p className="text-sm">
                                <Trend
                                    now={registrations.last_week}
                                    before={registrations.previous_week}
                                />
                                {paymentSummary && (
                                    <span className="text-muted-foreground">
                                        {' · '}
                                        {formatRupiah(
                                            paymentSummary.verified_week_amount,
                                        )}{' '}
                                        terverifikasi pekan ini
                                    </span>
                                )}
                            </p>
                        )
                    }
                    actions={
                        <>
                            {can('admission::payment.verify') && (
                                <Button variant="outline" asChild>
                                    <Link href={createPayment()}>
                                        <Plus />
                                        Catat pembayaran
                                    </Link>
                                </Button>
                            )}
                            {can('admission::registrant.view') && (
                                <Button asChild>
                                    <Link href={registrants()}>
                                        Lihat pendaftar
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                {attention.length === 0 && !flow ? (
                    <EmptyState
                        icon={ClipboardCheck}
                        title="Belum ada yang bisa ditampilkan"
                        description="Akun ini belum punya akses ke pendaftar atau pembayaran. Hubungi admin untuk menambah izin."
                    />
                ) : (
                    <div className="grid items-start gap-6 @5xl:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">
                        <AttentionList queues={attention} />
                        {flow && <FlowCard flow={flow} />}
                    </div>
                )}

                <div className="grid items-start gap-6 @4xl:grid-cols-2 @7xl:grid-cols-3">
                    {can('admission::registrant.view') && (
                        <SectionCard
                            title="Ujian terdekat"
                            description="Sesi dalam 14 hari ke depan."
                            icon={CalendarClock}
                            contentClassName="grid gap-4"
                        >
                            {exams.length === 0 ? (
                                <p className="text-[15px] text-muted-foreground">
                                    Belum ada sesi ujian terjadwal.
                                </p>
                            ) : (
                                exams.map((exam) => (
                                    <div key={exam.id} className="grid gap-2">
                                        <div className="flex items-baseline justify-between gap-3">
                                            <p className="font-semibold">
                                                {formatDate(exam.date, {
                                                    weekday: 'short',
                                                    day: 'numeric',
                                                    month: 'short',
                                                })}
                                                <span className="ml-2 font-normal text-muted-foreground">
                                                    {exam.label} · {exam.time}
                                                </span>
                                            </p>
                                            <p
                                                className={cn(
                                                    'text-sm font-semibold tabular-nums',
                                                    exam.taken >= exam.quota &&
                                                        'text-amber-700 dark:text-amber-300',
                                                )}
                                            >
                                                {exam.taken}/{exam.quota}
                                            </p>
                                        </div>
                                        <Progress
                                            value={Math.min(
                                                100,
                                                (exam.taken / exam.quota) * 100,
                                            )}
                                            aria-label={`Terisi ${exam.taken} dari ${exam.quota} kursi`}
                                        />
                                    </div>
                                ))
                            )}
                        </SectionCard>
                    )}

                    {can('admission::wave.view') && (
                        <SectionCard
                            title="Gelombang berlangsung"
                            description="Gelombang yang sedang menerima pendaftar."
                            actions={
                                <Button variant="ghost" size="sm" asChild>
                                    <Link href={waves()}>Kelola</Link>
                                </Button>
                            }
                            contentClassName="grid gap-3"
                        >
                            {openWaves.length === 0 ? (
                                <p className="text-[15px] text-muted-foreground">
                                    Tidak ada gelombang yang sedang dibuka.
                                </p>
                            ) : (
                                openWaves.map((wave) => (
                                    <div
                                        key={wave.id}
                                        className="flex items-center justify-between gap-3"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-semibold">
                                                {wave.name}
                                            </p>
                                            <p className="text-sm text-muted-foreground tabular-nums">
                                                {wave.registrants} pendaftar
                                                {wave.ends_on &&
                                                    ` · sampai ${formatDate(wave.ends_on)}`}
                                            </p>
                                        </div>
                                        {wave.days_left !== null && (
                                            <Badge
                                                variant={
                                                    wave.days_left <= 7
                                                        ? 'warning'
                                                        : 'muted'
                                                }
                                            >
                                                {wave.days_left <= 0
                                                    ? 'Hari terakhir'
                                                    : `${wave.days_left} hari lagi`}
                                            </Badge>
                                        )}
                                    </div>
                                ))
                            )}
                        </SectionCard>
                    )}

                    {can('admission::registrant.view') && (
                        <SectionCard
                            title="Aktivitas terbaru"
                            icon={History}
                            contentClassName="grid gap-4"
                        >
                            {activity.length === 0 ? (
                                <p className="text-[15px] text-muted-foreground">
                                    Belum ada aktivitas.
                                </p>
                            ) : (
                                activity.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex items-start gap-3"
                                    >
                                        <span
                                            className={cn(
                                                'mt-1.5 size-2.5 shrink-0 rounded-full',
                                                flowColor[item.to_status],
                                            )}
                                        />
                                        <p className="min-w-0 flex-1 text-[15px]">
                                            <Link
                                                href={showRegistrant(
                                                    item.registrant_id,
                                                )}
                                                className="font-semibold underline-offset-4 hover:underline"
                                            >
                                                {item.child}
                                            </Link>{' '}
                                            <span className="text-muted-foreground">
                                                jadi
                                            </span>{' '}
                                            {item.to_label}
                                            {item.is_bypass && (
                                                <Badge
                                                    variant="warning"
                                                    className="ml-2"
                                                >
                                                    Lewat jalur khusus
                                                </Badge>
                                            )}
                                            <span className="block text-sm text-muted-foreground">
                                                {item.by ?? 'Sistem'} ·{' '}
                                                {relativeTime(item.at)}
                                            </span>
                                        </p>
                                    </div>
                                ))
                            )}
                        </SectionCard>
                    )}
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Beranda', href: dashboard() }],
};
