import { Head, useForm } from '@inertiajs/react';
import { Megaphone, RotateCcw } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { ValueTabs } from '@/components/section-tabs';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import { update } from '@/routes/admin/admission-settings';
import { index } from '@/routes/admin/registrants';

type MessageTemplate = {
    kind: string;
    label: string;
    description: string;
    template: string;
    default: string;
    tokens: { token: string; description: string }[];
    /** Token name (without braces) to the sample value shown in the preview. */
    sample: Record<string, string>;
};

const tabLabels: Record<string, string> = {
    'announcement-passed': 'Pengumuman tuntas',
    'announcement-failed': 'Belum tuntas',
    'announcement-incomplete': 'Nilai belum ada',
    revision: 'Perlu diperbaiki',
    payment: 'Pembayaran',
    exam: 'Ujian',
    'payment-arrears': 'Tunggakan',
};

function fillSample(text: string, sample: Record<string, string>): string {
    return Object.entries(sample).reduce(
        (result, [name, value]) => result.replaceAll(`{${name}}`, value),
        text,
    );
}

export default function AdmissionSettings({
    templates,
    limits,
}: {
    templates: MessageTemplate[];
    limits: { max: number; comfortable: number };
}) {
    const form = useForm({
        templates: Object.fromEntries(
            templates.map((item) => [item.kind, item.template]),
        ) as Record<string, string>,
    });
    const [kind, setKind] = useState(templates[0].kind);
    const textarea = useRef<HTMLTextAreaElement>(null);

    const current =
        templates.find((item) => item.kind === kind) ?? templates[0];
    const text = form.data.templates[current.kind] ?? '';
    const preview = useMemo(
        () => fillSample(text, current.sample),
        [text, current.sample],
    );
    const error = (form.errors as Record<string, string | undefined>)[
        `templates.${current.kind}`
    ];
    const changedKinds = templates.filter(
        (item) => form.data.templates[item.kind] !== item.template,
    );

    function setText(value: string) {
        form.setData('templates', {
            ...form.data.templates,
            [current.kind]: value,
        });
    }

    /** Puts `{token}` where the cursor is (or replaces the selection) and keeps the cursor after it. */
    function insertToken(token: string) {
        const field = textarea.current;
        const start = field?.selectionStart ?? text.length;
        const end = field?.selectionEnd ?? text.length;

        setText(text.slice(0, start) + token + text.slice(end));

        requestAnimationFrame(() => {
            field?.focus();
            field?.setSelectionRange(
                start + token.length,
                start + token.length,
            );
        });
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.put(update().url, {
            preserveScroll: true,
            onError: (errors) => {
                const failed = templates.find(
                    (item) => errors[`templates.${item.kind}`],
                );

                if (failed) {
                    setKind(failed.kind);
                }
            },
        });
    }

    const previewLength = preview.length;
    const hasErrors = Object.keys(form.errors).length > 0;

    return (
        <>
            <Head title="Templat pesan" />
            <form
                onSubmit={submit}
                className="mx-auto grid w-full max-w-3xl gap-6"
            >
                <PageHeader
                    icon={Megaphone}
                    title="Templat pesan"
                    description="Teks WhatsApp untuk pengumuman hasil, pengingat revisi, pembayaran, ujian, dan tunggakan. Pesan dikirim ke kontak utama anak, atau wali lain yang punya nomor HP."
                />

                <ValueTabs
                    label="Jenis pesan"
                    value={kind}
                    onChange={setKind}
                    items={templates.map((item) => ({
                        value: item.kind,
                        label: tabLabels[item.kind] ?? item.label,
                    }))}
                />

                <SectionCard
                    title={current.label}
                    description={current.description}
                >
                    <div className="grid gap-5">
                        <FormField
                            label="Teks pesan"
                            error={error}
                            hint={
                                text.trim() === ''
                                    ? 'Dikosongkan: dipakai teks bawaan.'
                                    : undefined
                            }
                        >
                            {(control) => (
                                <Textarea
                                    ref={textarea}
                                    className="min-h-48"
                                    value={text}
                                    maxLength={limits.max}
                                    onChange={(event) =>
                                        setText(event.target.value)
                                    }
                                    {...control}
                                />
                            )}
                        </FormField>

                        <div className="grid gap-2">
                            <p className="text-sm font-semibold">
                                Sisipkan kata yang otomatis diganti
                            </p>
                            <ul className="flex flex-wrap gap-2">
                                {current.tokens.map((item) => (
                                    <li key={item.token}>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="tabular-nums"
                                            title={item.description}
                                            onClick={() =>
                                                insertToken(item.token)
                                            }
                                        >
                                            {item.token}
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                            <dl className="grid gap-1 text-sm text-muted-foreground">
                                {current.tokens.map((item) => (
                                    <div
                                        key={item.token}
                                        className="flex flex-wrap gap-x-2"
                                    >
                                        <dt className="tabular-nums">
                                            {item.token}
                                        </dt>
                                        <dd>{item.description}</dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    </div>
                </SectionCard>

                <SectionCard
                    title="Contoh hasilnya"
                    description="Begini kira-kira pesan yang diterima wali, dengan data contoh."
                >
                    <div className="grid gap-3">
                        <p
                            className="rounded-2xl border border-line bg-neutral-50 p-4 text-sm leading-relaxed whitespace-pre-wrap dark:bg-card"
                            aria-live="polite"
                        >
                            {preview}
                        </p>
                        <p
                            className={
                                previewLength > limits.comfortable
                                    ? 'text-sm font-medium text-amber-700 dark:text-amber-300'
                                    : 'text-sm text-muted-foreground'
                            }
                        >
                            <span className="tabular-nums">
                                {previewLength}
                            </span>{' '}
                            karakter setelah diisi
                            {previewLength > limits.comfortable
                                ? `. Agak panjang untuk WhatsApp; pesan di bawah ${limits.comfortable} karakter lebih mudah dibaca sampai habis.`
                                : '. Panjangnya nyaman untuk WhatsApp.'}
                        </p>
                    </div>
                </SectionCard>

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                    {changedKinds.length > 0 && (
                        <p className="text-sm text-muted-foreground sm:mr-auto">
                            {changedKinds.length} templat belum disimpan.
                        </p>
                    )}
                    {hasErrors && (
                        <p className="text-sm font-medium text-destructive-foreground sm:mr-auto">
                            Ada templat yang perlu diperbaiki.
                        </p>
                    )}
                    <Button
                        type="button"
                        variant="outline"
                        disabled={text === current.default}
                        onClick={() => setText(current.default)}
                    >
                        <RotateCcw />
                        Kembalikan bawaan
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        Simpan templat
                    </Button>
                </div>
            </form>
        </>
    );
}

AdmissionSettings.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Pendaftar', href: index() },
        { title: 'Templat pesan', href: '#' },
    ],
};
