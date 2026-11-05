import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Clock,
    Download,
    ExternalLink,
    Landmark,
    Scale,
} from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { formatLocalDate } from '@/components/payments/payment-format';
import { SectionCard } from '@/components/section-card';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index, reconciliation, show } from '@/routes/admin/payments';
import { exportMethod } from '@/routes/admin/payments/reconciliation';
import type { ReconciliationData } from '@/types/billing';

function shiftDay(date: string, days: number): string {
    const [year, month, day] = date.split('-').map(Number);

    return formatLocalDate(new Date(year, month - 1, day + days));
}

function describeAge(days: number): string {
    return days === 0 ? 'Baru masuk hari ini' : `Menunggu ${days} hari`;
}

export default function PaymentReconciliation({
    date,
    today,
    methods,
    verified,
    pending,
    list_limit: listLimit,
    can,
}: ReconciliationData) {
    // Typed in by the cashier to compare with the bank statement; it never leaves the browser.
    const [bankTotal, setBankTotal] = useState('');
    const bankAmount = bankTotal.trim() === '' ? null : Number(bankTotal);
    const difference =
        bankAmount === null || Number.isNaN(bankAmount)
            ? null
            : bankAmount - verified.transfer_total;

    function goTo(next: string) {
        router.get(
            reconciliation().url,
            { tanggal: next === today ? undefined : next },
            { preserveScroll: true, replace: true },
        );
    }

    const isToday = date === today;
    const dayLabel = formatDate(date, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    return (
        <>
            <Head title="Rekonsiliasi harian" />
            <div className="grid gap-6">
                <PageHeader
                    icon={Scale}
                    title="Rekonsiliasi harian"
                    description="Cocokkan uang yang diverifikasi hari itu dengan rekening dan kas. Dihitung menurut tanggal verifikasi, zona waktu WIB."
                    actions={
                        can.export && (
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        exportMethod({
                                            query: isToday
                                                ? undefined
                                                : { tanggal: date },
                                        }).url
                                    }
                                >
                                    <Download />
                                    Ekspor CSV
                                </a>
                            </Button>
                        )
                    }
                />

                <div className="flex flex-wrap items-end gap-3">
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label="Hari sebelumnya"
                        onClick={() => goTo(shiftDay(date, -1))}
                    >
                        <ChevronLeft />
                    </Button>
                    <div className="grid gap-1.5">
                        <Label
                            htmlFor="tanggal"
                            className="text-xs text-muted-foreground"
                        >
                            Tanggal
                        </Label>
                        <Input
                            id="tanggal"
                            type="date"
                            value={date}
                            onChange={(event) =>
                                event.target.value && goTo(event.target.value)
                            }
                        />
                    </div>
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label="Hari berikutnya"
                        disabled={isToday}
                        onClick={() => goTo(shiftDay(date, 1))}
                    >
                        <ChevronRight />
                    </Button>
                    {!isToday && (
                        <Button variant="ghost" onClick={() => goTo(today)}>
                            Ke hari ini
                        </Button>
                    )}
                    <p className="pb-2 text-sm font-medium text-muted-foreground">
                        {dayLabel}
                    </p>
                </div>

                <section
                    aria-label="Ringkasan hari ini"
                    className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4"
                >
                    <StatCard
                        label="Terverifikasi"
                        value={formatRupiah(verified.total)}
                        hint={`${verified.count} pembayaran`}
                        icon={Landmark}
                    />
                    {methods.map((method) => (
                        <StatCard
                            key={method.value}
                            label={method.label}
                            value={formatRupiah(method.total)}
                            hint={`${method.count} pembayaran`}
                        />
                    ))}
                    <StatCard
                        label="Masih menunggu"
                        value={formatRupiah(pending.total)}
                        hint={`${pending.count} dibayar hari itu, belum diverifikasi`}
                        tone={pending.count > 0 ? 'warning' : 'default'}
                        icon={Clock}
                    />
                </section>

                <SectionCard
                    title="Cocokkan dengan rekening"
                    description="Isi saldo masuk dari mutasi rekening untuk hari ini. Angkanya tidak disimpan."
                >
                    <div className="grid gap-4 sm:grid-cols-[minmax(0,16rem)_1fr] sm:items-end">
                        <div className="grid gap-1.5">
                            <Label htmlFor="rekening">Total di rekening</Label>
                            <Input
                                id="rekening"
                                type="number"
                                inputMode="numeric"
                                min={0}
                                placeholder="Rp"
                                className="tabular-nums"
                                value={bankTotal}
                                onChange={(event) =>
                                    setBankTotal(event.target.value)
                                }
                            />
                        </div>
                        <p
                            role="status"
                            className={cn(
                                'pb-2 text-sm font-semibold tabular-nums',
                                difference === null &&
                                    'font-normal text-muted-foreground',
                                difference === 0 && 'text-success',
                                difference !== null &&
                                    difference !== 0 &&
                                    'text-destructive-foreground',
                            )}
                        >
                            {difference === null &&
                                `Dibandingkan dengan transfer terverifikasi ${formatRupiah(verified.transfer_total)}.`}
                            {difference === 0 &&
                                `Cocok dengan transfer terverifikasi ${formatRupiah(verified.transfer_total)}.`}
                            {difference !== null &&
                                difference > 0 &&
                                `Rekening lebih ${formatRupiah(difference)} dari transfer terverifikasi (${formatRupiah(verified.transfer_total)}). Cek apakah ada transfer yang belum dicatat.`}
                            {difference !== null &&
                                difference < 0 &&
                                `Rekening kurang ${formatRupiah(-difference)} dari transfer terverifikasi (${formatRupiah(verified.transfer_total)}). Cek bukti transfer yang sudah diverifikasi.`}
                        </p>
                    </div>
                </SectionCard>

                <SectionCard
                    title="Diterima tapi belum diverifikasi"
                    description="Pembayaran dengan tanggal bayar hari itu yang masih menunggu. Belum dihitung sebagai uang masuk."
                >
                    {pending.rows.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Tidak ada. Semua yang dibayar hari itu sudah
                            diproses.
                        </p>
                    ) : (
                        <ul className="grid divide-y divide-line">
                            {pending.rows.map((payment) => (
                                <li
                                    key={payment.id}
                                    className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-3 first:pt-0 last:pb-0"
                                >
                                    <span className="grid gap-0.5">
                                        <Link
                                            href={show(payment.id)}
                                            className="w-fit font-semibold underline-offset-4 hover:underline"
                                        >
                                            {payment.payer}
                                        </Link>
                                        <span className="text-xs text-muted-foreground">
                                            {payment.method}
                                            {payment.for.length > 0 &&
                                                ` · Untuk ${payment.for.join(', ')}`}
                                        </span>
                                    </span>
                                    <span className="flex items-center gap-4">
                                        <span
                                            className={cn(
                                                'text-sm',
                                                payment.age_days > 0
                                                    ? 'font-semibold text-amber-700 dark:text-amber-300'
                                                    : 'text-muted-foreground',
                                            )}
                                        >
                                            {describeAge(payment.age_days)}
                                        </span>
                                        <span className="font-semibold tabular-nums">
                                            {formatRupiah(payment.amount)}
                                        </span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                    {pending.rows.length < pending.count && (
                        <p className="mt-3 text-sm text-muted-foreground">
                            Menampilkan {pending.rows.length} dari{' '}
                            {pending.count}. Sisanya ada di ekspor CSV.
                        </p>
                    )}
                </SectionCard>

                <SectionCard
                    title="Pembayaran terverifikasi hari itu"
                    description="Diurutkan menurut jam verifikasi."
                    contentClassName="px-0"
                >
                    {verified.rows.length === 0 ? (
                        <div className="px-6">
                            <EmptyState
                                icon={Landmark}
                                title="Belum ada yang diverifikasi"
                                description="Tidak ada pembayaran yang diverifikasi pada tanggal ini."
                                action={
                                    <Button variant="outline" asChild>
                                        <Link href={index()}>
                                            Ke daftar pembayaran
                                        </Link>
                                    </Button>
                                }
                            />
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="pl-6">Jam</TableHead>
                                    <TableHead>Pembayar</TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Cara bayar
                                    </TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Diverifikasi oleh
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Nominal
                                    </TableHead>
                                    <TableHead className="w-12 pr-6">
                                        <span className="sr-only">Detail</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {verified.rows.map((payment) => (
                                    <TableRow key={payment.id}>
                                        <TableCell className="pl-6 tabular-nums">
                                            {payment.verified_time ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <span className="grid">
                                                <span className="font-semibold">
                                                    {payment.payer}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {payment.for.length > 0
                                                        ? `Untuk ${payment.for.join(', ')}`
                                                        : 'Tanpa rincian'}
                                                </span>
                                            </span>
                                        </TableCell>
                                        <TableCell className="hidden md:table-cell">
                                            {payment.method}
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell">
                                            {payment.verified_by ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right font-semibold tabular-nums">
                                            {formatRupiah(payment.amount)}
                                        </TableCell>
                                        <TableCell className="pr-6 text-right">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                            >
                                                <Link
                                                    href={show(payment.id)}
                                                    aria-label={`Buka detail pembayaran ${payment.payer}`}
                                                >
                                                    <ExternalLink />
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                    {verified.rows.length < verified.count && (
                        <p className="mt-3 px-6 text-sm text-muted-foreground">
                            Menampilkan {listLimit} dari {verified.count}.
                            Sisanya ada di ekspor CSV.
                        </p>
                    )}
                </SectionCard>
            </div>
        </>
    );
}

PaymentReconciliation.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Pembayaran', href: index() },
        { title: 'Rekonsiliasi', href: '#' },
    ],
};
