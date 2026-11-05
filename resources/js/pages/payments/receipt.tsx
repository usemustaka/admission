import { Head, usePage } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatDate, formatRupiah } from '@/lib/format';
import type { ReceiptData } from '@/types/billing';

/**
 * The printable receipt of one verified payment. It sits outside the app layout so a parent opens it without any
 * navigation around it, and the paper is fixed to black on white so it prints the same in dark mode.
 */
export default function PaymentReceipt({ receipt }: { receipt: ReceiptData }) {
    const { brand } = usePage().props;
    const contact = [
        brand.contact.address,
        [brand.contact.phone, brand.contact.email].filter(Boolean).join(' · '),
    ].filter(Boolean);

    return (
        <>
            <Head title={`Kwitansi ${receipt.number}`} />
            <div className="min-h-svh bg-neutral-100 px-4 py-6 text-neutral-900 print:bg-white print:p-0">
                <div className="mx-auto mb-4 flex max-w-3xl flex-wrap items-center justify-between gap-2 print:hidden">
                    <Button
                        variant="ghost"
                        onClick={() => window.history.back()}
                    >
                        <ArrowLeft />
                        Kembali
                    </Button>
                    <Button onClick={() => window.print()}>
                        <Printer />
                        Cetak kwitansi
                    </Button>
                </div>

                <article
                    aria-label={`Kwitansi ${receipt.number}`}
                    className="mx-auto grid max-w-3xl gap-8 rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-10 print:max-w-none print:rounded-none print:border-0 print:p-0 print:shadow-none"
                >
                    <header className="flex flex-wrap items-start justify-between gap-4 border-b-2 border-neutral-900 pb-5">
                        <div className="flex items-center gap-4">
                            {brand.logo_url && (
                                <img
                                    src={brand.logo_url}
                                    alt=""
                                    className="h-14 w-auto max-w-40 object-contain"
                                />
                            )}
                            <div className="grid gap-0.5">
                                <p className="text-lg leading-tight font-bold">
                                    {brand.organization ?? brand.name}
                                </p>
                                {brand.organization &&
                                    brand.organization !== brand.name && (
                                        <p className="text-sm text-neutral-600">
                                            {brand.name}
                                        </p>
                                    )}
                                {contact.map((line) => (
                                    <p
                                        key={line}
                                        className="text-sm text-neutral-600"
                                    >
                                        {line}
                                    </p>
                                ))}
                            </div>
                        </div>
                        <div className="grid gap-0.5 text-right">
                            <p className="text-2xl font-bold tracking-wide">
                                KWITANSI
                            </p>
                            <p className="text-sm font-semibold tabular-nums">
                                {receipt.number}
                            </p>
                            <p className="text-sm text-neutral-600">
                                {formatDate(receipt.paid_on, {
                                    day: 'numeric',
                                    month: 'long',
                                    year: 'numeric',
                                })}
                            </p>
                        </div>
                    </header>

                    <dl className="grid gap-3 text-[15px] sm:grid-cols-[10rem_1fr]">
                        <dt className="text-neutral-600">Telah terima dari</dt>
                        <dd className="font-semibold">{receipt.payer}</dd>
                        <dt className="text-neutral-600">Cara bayar</dt>
                        <dd>{receipt.method}</dd>
                    </dl>

                    <table className="w-full text-[15px]">
                        <caption className="sr-only">
                            Rincian pembayaran
                        </caption>
                        <thead>
                            <tr className="border-b border-neutral-300 text-left text-xs tracking-wide text-neutral-600 uppercase">
                                <th className="py-2 pr-3 font-semibold">
                                    Untuk
                                </th>
                                <th className="py-2 pr-3 font-semibold">
                                    Rincian
                                </th>
                                <th className="py-2 text-right font-semibold">
                                    Jumlah
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-neutral-200">
                            {receipt.lines.map((line, index) => (
                                <tr key={index} className="align-top">
                                    <td className="py-2.5 pr-3">
                                        <span className="block font-medium">
                                            {line.child}
                                        </span>
                                        <span className="block text-sm text-neutral-600">
                                            {line.organization}
                                        </span>
                                    </td>
                                    <td className="py-2.5 pr-3">
                                        <span className="block">
                                            {line.item}
                                        </span>
                                        <span className="block text-sm text-neutral-600">
                                            Tagihan {line.bill}
                                        </span>
                                    </td>
                                    <td className="py-2.5 text-right tabular-nums">
                                        {formatRupiah(line.amount)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="border-t-2 border-neutral-900">
                                <td
                                    colSpan={2}
                                    className="py-3 pr-3 font-semibold"
                                >
                                    Total
                                </td>
                                <td className="py-3 text-right text-lg font-bold tabular-nums">
                                    {formatRupiah(receipt.total)}
                                </td>
                            </tr>
                        </tfoot>
                    </table>

                    <p className="rounded-xl bg-neutral-100 px-4 py-3 text-[15px] print:border print:border-neutral-300 print:bg-white">
                        <span className="text-neutral-600">Terbilang: </span>
                        <span className="font-semibold italic">
                            {receipt.total_words}
                        </span>
                    </p>

                    {receipt.note && (
                        <p className="text-sm text-neutral-600">
                            Catatan: {receipt.note}
                        </p>
                    )}

                    <footer className="grid gap-1 text-sm text-neutral-600">
                        {receipt.verified_by && (
                            <p>
                                Diverifikasi oleh{' '}
                                <span className="font-semibold text-neutral-900">
                                    {receipt.verified_by}
                                </span>
                                {receipt.verified_at &&
                                    ` pada ${receipt.verified_at}`}
                            </p>
                        )}
                        <p>Dicetak dari sistem {brand.name}.</p>
                    </footer>
                </article>
            </div>
        </>
    );
}
