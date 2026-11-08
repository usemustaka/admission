import { Head, usePage } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Letterhead } from '@/components/letterhead';
import { Button } from '@/components/ui/button';
import { formatDate, formatRupiah } from '@/lib/format';

type SettlementReceiptData = {
    number: string;
    date: string;
    payer: string;
    child: string;
    organization: string;
    items: { bill: string; name: string; amount: number }[];
    payments: { date: string; method: string; amount: number }[];
    total: number;
    paid: number;
    total_words: string;
};

/**
 * The receipt that confirms a child's registration is fully paid ("kwitansi lunas"): every bill item and every
 * payment that covered it, with the total in words.
 */
export default function SettlementReceipt({
    receipt,
}: {
    receipt: SettlementReceiptData;
}) {
    const { brand } = usePage().props;

    return (
        <>
            <Head title={`Kwitansi lunas ${receipt.child}`} />
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
                    aria-label={`Kwitansi lunas ${receipt.number}`}
                    className="mx-auto grid max-w-3xl gap-6 rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-10 print:max-w-none print:rounded-none print:border-0 print:p-0 print:shadow-none"
                >
                    <Letterhead />

                    <div className="flex flex-wrap items-end justify-between gap-2">
                        <div>
                            <p className="text-2xl font-bold tracking-wide">
                                KWITANSI LUNAS
                            </p>
                            <p className="text-sm font-semibold tabular-nums">
                                {receipt.number}
                            </p>
                        </div>
                        <p className="text-sm text-neutral-600">
                            {formatDate(receipt.date, {
                                day: 'numeric',
                                month: 'long',
                                year: 'numeric',
                            })}
                        </p>
                    </div>

                    <dl className="grid gap-2 text-[15px] sm:grid-cols-[10rem_1fr]">
                        <dt className="text-neutral-600">Telah terima dari</dt>
                        <dd className="font-semibold">{receipt.payer}</dd>
                        <dt className="text-neutral-600">Untuk</dt>
                        <dd>
                            Pembayaran ananda{' '}
                            <span className="font-semibold">
                                {receipt.child}
                            </span>
                            , {receipt.organization}
                        </dd>
                    </dl>

                    <table className="w-full text-[15px]">
                        <caption className="sr-only">Rincian tagihan</caption>
                        <thead>
                            <tr className="border-b border-neutral-300 text-left text-xs tracking-wide text-neutral-600 uppercase">
                                <th className="py-2 pr-3 font-semibold">
                                    Rincian
                                </th>
                                <th className="py-2 text-right font-semibold">
                                    Jumlah
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-neutral-200">
                            {receipt.items.map((item, index) => (
                                <tr key={index}>
                                    <td className="py-2 pr-3">
                                        {item.name}
                                        <span className="block text-sm text-neutral-600">
                                            Tagihan {item.bill}
                                        </span>
                                    </td>
                                    <td className="py-2 text-right tabular-nums">
                                        {formatRupiah(item.amount)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="border-t-2 border-neutral-900">
                                <td className="py-3 pr-3 font-semibold">
                                    Total
                                </td>
                                <td className="py-3 text-right text-lg font-bold tabular-nums">
                                    {formatRupiah(receipt.total)}
                                </td>
                            </tr>
                        </tfoot>
                    </table>

                    <section className="grid gap-2">
                        <h2 className="text-sm font-semibold">
                            Pembayaran yang diterima
                        </h2>
                        <ul className="grid gap-1 text-[15px]">
                            {receipt.payments.map((payment, index) => (
                                <li
                                    key={index}
                                    className="flex justify-between gap-3"
                                >
                                    <span>
                                        {formatDate(payment.date, {
                                            day: 'numeric',
                                            month: 'long',
                                            year: 'numeric',
                                        })}{' '}
                                        · {payment.method}
                                    </span>
                                    <span className="tabular-nums">
                                        {formatRupiah(payment.amount)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>

                    <p className="rounded-xl bg-neutral-100 px-4 py-3 text-[15px] print:border print:border-neutral-300 print:bg-white">
                        <span className="text-neutral-600">Terbilang: </span>
                        <span className="font-semibold italic">
                            {receipt.total_words}
                        </span>
                    </p>

                    <p className="w-fit -rotate-3 rounded-lg border-4 border-neutral-900 px-5 py-1 text-3xl font-black tracking-widest">
                        LUNAS
                    </p>

                    <footer className="text-sm text-neutral-600">
                        Dicetak dari sistem {brand.name}.
                    </footer>
                </article>
            </div>
        </>
    );
}
