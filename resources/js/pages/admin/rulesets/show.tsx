import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    ClipboardList,
    FileText,
    ListChecks,
    Pencil,
    Trash2,
    TriangleAlert,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import { BillEditor } from '@/components/admission/bill-editor';
import { DocumentRequirementEditor } from '@/components/admission/document-requirement-editor';
import type {
    CustomDocumentRow,
    DocumentRequirementRow,
} from '@/components/admission/document-requirement-editor';
import { RequiredFieldsEditor } from '@/components/admission/required-fields-editor';
import type { RequiredFieldGroup } from '@/components/admission/required-fields-editor';
import { ExamAspectEditor } from '@/components/admission/exam-aspect-editor';
import { RulesetConditions } from '@/components/admission/ruleset-conditions';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { edit as editSchedule } from '@/routes/admin/exams/schedule';
import { destroy, edit, index, show } from '@/routes/admin/rulesets';
import type {
    BillItemRow,
    ExamAspectRow,
    RulesetSummary,
} from '@/types/admission';

export default function RulesetShow({
    ruleset,
    bills,
    aspects,
    documents,
    customDocuments,
    requiredFields,
    can,
}: {
    ruleset: RulesetSummary;
    bills: { registration: BillItemRow[]; settlement: BillItemRow[] };
    aspects: ExamAspectRow[];
    documents: DocumentRequirementRow[];
    customDocuments: CustomDocumentRow[];
    requiredFields: { groups: RequiredFieldGroup[]; selected: string[] };
    can: { update: boolean; delete: boolean };
}) {
    const [confirmDelete, setConfirmDelete] = useState(false);
    const { abilities } = usePage().props.auth;
    const canManageSchedule = abilities.includes('admission::exam.manage');

    return (
        <>
            <Head title={ruleset.name} />
            <div className="grid gap-6">
                <PageHeader
                    icon={ListChecks}
                    title={ruleset.name}
                    description="Isi tagihan dan aspek ujian untuk pendaftar yang cocok dengan syarat aturan ini."
                    actions={
                        <>
                            {can.update && (
                                <Button variant="outline" asChild>
                                    <Link href={edit(ruleset.id)}>
                                        <Pencil />
                                        Ubah syarat
                                    </Link>
                                </Button>
                            )}
                            {can.delete && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Hapus aturan"
                                    onClick={() => setConfirmDelete(true)}
                                >
                                    <Trash2 />
                                </Button>
                            )}
                        </>
                    }
                />

                {ruleset.shadowed_by && (
                    <Alert variant="warning">
                        <TriangleAlert />
                        <AlertTitle>Aturan ini tidak pernah dipakai</AlertTitle>
                        <AlertDescription>
                            Aturan{' '}
                            <Link
                                href={show(ruleset.shadowed_by.id)}
                                className="underline"
                            >
                                {ruleset.shadowed_by.name}
                            </Link>{' '}
                            dicek lebih dulu dan cocok dengan semua pendaftar
                            yang cocok dengan aturan ini. Naikkan urutan aturan
                            ini di daftar aturan.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 @5xl:grid-cols-[minmax(0,1fr)_24rem] @5xl:items-start">
                    <div className="grid min-w-0 gap-6">
                        <SectionCard
                            title="Tagihan"
                            description="Rincian biaya yang akan ditagihkan ke pendaftar."
                            icon={Wallet}
                        >
                            <BillEditor
                                key={JSON.stringify(bills)}
                                rulesetId={ruleset.id}
                                initial={bills}
                                canEdit={can.update}
                            />
                        </SectionCard>

                        <SectionCard
                            title="Aspek ujian"
                            description="Bagian yang dinilai saat ujian, beserta KKM (nilai minimal untuk tuntas)."
                            icon={ListChecks}
                        >
                            <ExamAspectEditor
                                key={JSON.stringify(aspects)}
                                rulesetId={ruleset.id}
                                initial={aspects}
                                canEdit={can.update}
                            />
                        </SectionCard>

                        <SectionCard
                            title="Data yang wajib diisi"
                            description="Detail yang harus lengkap sebelum orang tua bisa mengirim data. Atur sesuai kebutuhan lembaga ini."
                            icon={ClipboardList}
                        >
                            <RequiredFieldsEditor
                                key={JSON.stringify(requiredFields.selected)}
                                rulesetId={ruleset.id}
                                groups={requiredFields.groups}
                                initial={requiredFields.selected}
                                canEdit={can.update}
                            />
                        </SectionCard>

                        <SectionCard
                            title="Berkas pendaftaran"
                            description="Berkas yang diminta dari orang tua, dan mana yang wajib."
                            icon={FileText}
                        >
                            <DocumentRequirementEditor
                                key={JSON.stringify([
                                    documents,
                                    customDocuments,
                                ])}
                                rulesetId={ruleset.id}
                                initial={documents}
                                initialCustom={customDocuments}
                                canEdit={can.update}
                            />
                        </SectionCard>

                        <SectionCard
                            title="Jadwal ujian diatur di menu Ujian, bukan per aturan"
                            description="Tanggal, sesi, dan kuota dipakai bersama oleh semua pendaftar di lembaga dan gelombang yang sama, apa pun aturannya."
                            icon={CalendarClock}
                            actions={
                                canManageSchedule && (
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={editSchedule({
                                                query: {
                                                    ...(ruleset.organization_id !==
                                                        null && {
                                                        lembaga:
                                                            ruleset.organization_id,
                                                    }),
                                                    ...(ruleset.wave_id !==
                                                        null && {
                                                        gelombang:
                                                            ruleset.wave_id,
                                                    }),
                                                },
                                            })}
                                        >
                                            Atur jadwal ujian
                                        </Link>
                                    </Button>
                                )
                            }
                        >
                            <p className="text-sm text-muted-foreground">
                                Aturan ini hanya menentukan tagihan dan aspek
                                ujian.
                            </p>
                        </SectionCard>
                    </div>

                    <aside className="@5xl:sticky @5xl:top-20">
                        <SectionCard title="Ringkasan">
                            <dl className="grid gap-4 text-sm">
                                <div className="grid gap-1.5">
                                    <dt className="text-muted-foreground">
                                        Berlaku untuk
                                    </dt>
                                    <dd>
                                        <RulesetConditions ruleset={ruleset} />
                                    </dd>
                                </div>
                                <div className="flex items-center justify-between gap-3">
                                    <dt className="text-muted-foreground">
                                        Status
                                    </dt>
                                    <dd>
                                        {ruleset.is_active ? (
                                            <Badge variant="success">
                                                Aktif
                                            </Badge>
                                        ) : (
                                            <Badge variant="muted">
                                                Tidak aktif
                                            </Badge>
                                        )}
                                    </dd>
                                </div>
                                <div className="flex items-center justify-between gap-3">
                                    <dt className="text-muted-foreground">
                                        Urutan dicek
                                    </dt>
                                    <dd className="font-semibold tabular-nums">
                                        Ke-{ruleset.priority}
                                    </dd>
                                </div>
                                <div className="flex items-center justify-between gap-3">
                                    <dt className="text-muted-foreground">
                                        Pendaftar yang memakai
                                    </dt>
                                    <dd className="font-semibold tabular-nums">
                                        {ruleset.registrants_count}
                                    </dd>
                                </div>
                            </dl>
                        </SectionCard>
                    </aside>
                </div>
            </div>

            <ConfirmDialog
                open={confirmDelete}
                onOpenChange={setConfirmDelete}
                tone={ruleset.registrants_count > 0 ? 'default' : 'danger'}
                title={
                    ruleset.registrants_count > 0
                        ? 'Aturan ini belum bisa dihapus'
                        : `Hapus aturan ${ruleset.name}?`
                }
                description={
                    ruleset.registrants_count > 0
                        ? `${ruleset.registrants_count} pendaftar sudah memakai aturan ini, jadi aturan tidak bisa dihapus. Nonaktifkan saja lewat Ubah syarat supaya tidak dipakai pendaftar baru.`
                        : 'Tagihan dan aspek ujian aturan ini ikut terhapus dan tidak bisa dikembalikan. Jadwal ujian tidak terpengaruh.'
                }
                confirmLabel={
                    ruleset.registrants_count > 0 ? 'Ubah syarat' : 'Ya, hapus'
                }
                onConfirm={() =>
                    ruleset.registrants_count > 0
                        ? router.visit(edit(ruleset.id).url)
                        : router.delete(destroy(ruleset.id).url)
                }
            />
        </>
    );
}

RulesetShow.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Aturan pendaftaran', href: index() },
    ],
};
