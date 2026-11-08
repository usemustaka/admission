import { Head, Link, useForm } from '@inertiajs/react';
import { FileText, HandHeart } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { DocumentSlotRow } from '@/components/portal/document-slot-row';
import type { DocumentSlot } from '@/components/portal/document-slot-row';
import { SectionCard } from '@/components/section-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { show } from '@/routes/portal/children';
import { support } from '@/routes/portal/children/documents';
import type { PortalRegistrant } from '@/types/portal';

type DocumentGroup = {
    type: string;
    label: string;
    /** True, false, or null for supporting files, which are never required. */
    required: boolean | null;
    max_files: number;
    slots: DocumentSlot[];
};

export default function ChildDocuments({
    registrant,
    requirements,
    supporting,
    support: supportValues,
    canEditRequired,
    canEditSupporting,
    maxKilobytes,
}: {
    registrant: PortalRegistrant;
    requirements: DocumentGroup[];
    supporting: DocumentGroup[];
    support: { kip_number: string | null; note: string | null };
    canEditRequired: boolean;
    canEditSupporting: boolean;
    maxKilobytes: number;
}) {
    const form = useForm({
        kip_number: supportValues.kip_number ?? '',
        support_note: supportValues.note ?? '',
    });

    function saveSupport(event: FormEvent) {
        event.preventDefault();
        form.put(support(registrant.id).url, { preserveScroll: true });
    }

    return (
        <>
            <Head title={`Berkas ${registrant.child.name}`} />
            <div className="grid gap-4">
                <PageHeader
                    title="Berkas"
                    description="Unggah berkas dalam format PDF atau JPG, maksimal 1 MB per berkas. Foto dari HP juga boleh, asal jelas terbaca."
                />

                {requirements.length > 0 && (
                    <SectionCard
                        title="Berkas pendaftaran"
                        description={
                            canEditRequired
                                ? 'Berkas bertanda wajib harus lengkap sebelum data dikirim.'
                                : 'Berkas tidak bisa diubah selama data diperiksa.'
                        }
                        icon={FileText}
                    >
                        <div className="grid gap-6">
                            {requirements.map((group) => (
                                <section
                                    key={group.type}
                                    className="grid gap-2"
                                >
                                    <h3 className="flex items-center gap-2 text-[15px] font-semibold">
                                        {group.label}
                                        <Badge
                                            variant={
                                                group.required
                                                    ? 'warning'
                                                    : 'muted'
                                            }
                                        >
                                            {group.required
                                                ? 'Wajib'
                                                : 'Opsional'}
                                        </Badge>
                                    </h3>
                                    <ul className="divide-y divide-line">
                                        {group.slots.map((slot) => (
                                            <DocumentSlotRow
                                                key={slot.slot}
                                                registrantId={registrant.id}
                                                type={group.type}
                                                slot={slot}
                                                maxKilobytes={maxKilobytes}
                                                canEdit={canEditRequired}
                                            />
                                        ))}
                                    </ul>
                                </section>
                            ))}
                        </div>
                    </SectionCard>
                )}

                <SectionCard
                    title="Prestasi dan bantuan"
                    description="Boleh dilewati. Kalau anak punya prestasi, hafalan, atau kartu bantuan, unggah buktinya di sini supaya panitia bisa mempertimbangkan. Bisa ditambah kapan saja sebelum anak ditempatkan."
                    icon={HandHeart}
                >
                    <div className="grid gap-6">
                        {supporting.map((group) => (
                            <section key={group.type} className="grid gap-2">
                                <h3 className="text-[15px] font-semibold">
                                    {group.label}
                                </h3>
                                <ul className="divide-y divide-line">
                                    {group.slots.map((slot) => (
                                        <DocumentSlotRow
                                            key={slot.slot}
                                            registrantId={registrant.id}
                                            type={group.type}
                                            slot={slot}
                                            maxKilobytes={maxKilobytes}
                                            canEdit={canEditSupporting}
                                        />
                                    ))}
                                </ul>
                            </section>
                        ))}

                        <form
                            onSubmit={saveSupport}
                            className="grid gap-4 border-t border-line pt-4"
                        >
                            <FormField
                                label="Nomor KIP"
                                hint="Kalau punya Kartu Indonesia Pintar"
                                error={form.errors.kip_number}
                            >
                                {(control) => (
                                    <Input
                                        value={form.data.kip_number}
                                        disabled={!canEditSupporting}
                                        onChange={(e) =>
                                            form.setData(
                                                'kip_number',
                                                e.target.value,
                                            )
                                        }
                                        {...control}
                                    />
                                )}
                            </FormField>
                            <FormField
                                label="Keterangan tambahan"
                                hint="Ceritakan singkat hal lain yang perlu panitia ketahui"
                                error={form.errors.support_note}
                            >
                                {(control) => (
                                    <Textarea
                                        className="min-h-20"
                                        value={form.data.support_note}
                                        disabled={!canEditSupporting}
                                        onChange={(e) =>
                                            form.setData(
                                                'support_note',
                                                e.target.value,
                                            )
                                        }
                                        {...control}
                                    />
                                )}
                            </FormField>
                            {canEditSupporting && (
                                <div>
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={form.processing}
                                    >
                                        {form.processing && <Spinner />}
                                        Simpan keterangan
                                    </Button>
                                </div>
                            )}
                        </form>
                    </div>
                </SectionCard>

                <Button variant="outline" size="lg" asChild>
                    <Link href={show(registrant.id)}>Kembali</Link>
                </Button>
            </div>
        </>
    );
}
