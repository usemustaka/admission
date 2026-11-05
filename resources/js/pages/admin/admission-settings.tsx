import { Head, useForm } from '@inertiajs/react';
import { Megaphone } from 'lucide-react';
import { useMemo } from 'react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import { update } from '@/routes/admin/admission-settings';
import { index } from '@/routes/admin/registrants';

const sampleValues: Record<string, string> = {
    '{nama}': 'Ahmad Fauzi',
    '{lembaga}': 'SMP Mustaka',
    '{gelombang}': 'Gelombang 1',
    '{hasil}': 'tuntas',
};

export default function AdmissionSettings({
    template,
    defaultTemplate,
    placeholders,
}: {
    template: string;
    defaultTemplate: string;
    placeholders: { token: string; description: string }[];
}) {
    const form = useForm({ template });
    const preview = useMemo(
        () =>
            Object.entries(sampleValues).reduce(
                (text, [token, value]) => text.replaceAll(token, value),
                form.data.template,
            ),
        [form.data.template],
    );

    function submit(event: FormEvent) {
        event.preventDefault();
        form.put(update().url, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Templat pengumuman" />
            <form
                onSubmit={submit}
                className="mx-auto grid w-full max-w-3xl gap-6"
            >
                <PageHeader
                    icon={Megaphone}
                    title="Templat pengumuman"
                    description="Teks ini dipakai untuk pengumuman hasil pendaftaran, baik disalin maupun dikirim lewat WhatsApp ke kontak utama anak."
                />

                <SectionCard title="Isi pesan">
                    <div className="grid gap-5">
                        <FormField
                            label="Teks pengumuman"
                            required
                            error={form.errors.template}
                        >
                            {(control) => (
                                <Textarea
                                    className="min-h-48"
                                    value={form.data.template}
                                    onChange={(event) =>
                                        form.setData(
                                            'template',
                                            event.target.value,
                                        )
                                    }
                                    {...control}
                                />
                            )}
                        </FormField>

                        <div className="grid gap-2">
                            <p className="text-sm font-semibold">
                                Kata yang otomatis diganti
                            </p>
                            <ul className="grid gap-2">
                                {placeholders.map((placeholder) => (
                                    <li
                                        key={placeholder.token}
                                        className="flex flex-wrap items-center gap-2 text-sm"
                                    >
                                        <Badge
                                            variant="muted"
                                            className="font-mono"
                                        >
                                            {placeholder.token}
                                        </Badge>
                                        <span className="text-muted-foreground">
                                            {placeholder.description}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </SectionCard>

                <SectionCard
                    title="Contoh hasilnya"
                    description="Begini kira-kira pesan yang diterima wali, dengan data contoh."
                >
                    <p
                        className="rounded-2xl border border-line bg-neutral-50 p-4 text-sm leading-relaxed whitespace-pre-wrap dark:bg-card"
                        aria-live="polite"
                    >
                        {preview}
                    </p>
                </SectionCard>

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            form.setData('template', defaultTemplate)
                        }
                    >
                        Pakai teks bawaan
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
        { title: 'Templat pengumuman', href: '#' },
    ],
};
