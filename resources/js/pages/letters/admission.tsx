import { Head, usePage } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Letterhead } from '@/components/letterhead';
import { Button } from '@/components/ui/button';

type LetterData = {
    date: string;
    recipient: string;
    subject: string;
    child: {
        name: string;
        nik: string | null;
        organization: string;
        wave: string;
    };
    paragraphs: string[];
    closing: string;
    signer: string;
};

/**
 * The acceptance letter of one child, ready to print on plain paper: the letterhead comes from the settings and the
 * letter carries no number.
 */
export default function AdmissionLetter({ letter }: { letter: LetterData }) {
    const { brand } = usePage().props;

    return (
        <>
            <Head title={`Surat penerimaan ${letter.child.name}`} />
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
                        Cetak surat
                    </Button>
                </div>

                <article
                    aria-label="Surat penerimaan"
                    className="mx-auto grid max-w-3xl gap-6 rounded-2xl border border-neutral-200 bg-white p-6 text-[15px] leading-relaxed shadow-sm sm:p-12 print:max-w-none print:rounded-none print:border-0 print:p-0 print:shadow-none"
                >
                    <Letterhead />

                    <p className="text-right">{letter.date}</p>

                    <div className="grid gap-1">
                        <p>
                            Perihal:{' '}
                            <span className="font-semibold">
                                {letter.subject}
                            </span>
                        </p>
                    </div>

                    <div className="grid gap-0.5">
                        <p>Kepada Yth.</p>
                        <p className="font-semibold">{letter.recipient}</p>
                        <p>di tempat</p>
                    </div>

                    <p>Dengan hormat,</p>

                    {letter.paragraphs.map((paragraph, index) => (
                        <p key={index} className="text-justify">
                            {paragraph}
                        </p>
                    ))}

                    <dl className="grid gap-1 rounded-xl border border-neutral-300 px-4 py-3 sm:grid-cols-[9rem_1fr]">
                        <dt className="text-neutral-600">Nama</dt>
                        <dd className="font-semibold">{letter.child.name}</dd>
                        {letter.child.nik && (
                            <>
                                <dt className="text-neutral-600">NIK</dt>
                                <dd>{letter.child.nik}</dd>
                            </>
                        )}
                        <dt className="text-neutral-600">Lembaga</dt>
                        <dd>{letter.child.organization}</dd>
                        <dt className="text-neutral-600">Gelombang</dt>
                        <dd>{letter.child.wave}</dd>
                    </dl>

                    <div className="mt-4 grid justify-end">
                        <div className="grid w-64 gap-20 text-center">
                            <p>
                                {letter.closing}
                                <br />
                                <span className="font-semibold">
                                    {letter.signer}
                                </span>
                                <br />
                                {brand.organization ?? brand.name}
                            </p>
                            <p className="border-t border-neutral-900 pt-1 text-sm text-neutral-600">
                                Nama dan tanda tangan
                            </p>
                        </div>
                    </div>
                </article>
            </div>
        </>
    );
}
