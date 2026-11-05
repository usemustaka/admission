import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays, ListChecks, Pencil, Trash2, Wallet } from 'lucide-react';
import { useState } from 'react';
import { BillEditor } from '@/components/admission/bill-editor';
import { ExamAspectEditor } from '@/components/admission/exam-aspect-editor';
import { ExamScheduleEditor } from '@/components/admission/exam-schedule-editor';
import { RulesetConditions } from '@/components/admission/ruleset-conditions';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { destroy, edit, index } from '@/routes/admin/rulesets';
import type {
    BillItemRow,
    ExamAspectRow,
    ExamDayRow,
    RulesetSummary,
} from '@/types/admission';

export default function RulesetShow({
    ruleset,
    bills,
    aspects,
    days,
    can,
}: {
    ruleset: RulesetSummary;
    bills: { registration: BillItemRow[]; settlement: BillItemRow[] };
    aspects: ExamAspectRow[];
    days: ExamDayRow[];
    can: { update: boolean; delete: boolean };
}) {
    const [confirmDelete, setConfirmDelete] = useState(false);

    return (
        <>
            <Head title={ruleset.name} />
            <div className="mx-auto grid w-full max-w-4xl gap-6 p-4 md:p-6">
                <PageHeader
                    icon={ListChecks}
                    title={ruleset.name}
                    description={`Urutan ke-${ruleset.priority} saat dicek. Dipakai untuk pendaftar dengan syarat berikut.`}
                    meta={
                        <div className="flex flex-wrap items-center gap-2 pt-1">
                            <RulesetConditions ruleset={ruleset} />
                            {!ruleset.is_active && (
                                <Badge variant="muted">Tidak aktif</Badge>
                            )}
                        </div>
                    }
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
                    title="Jadwal ujian"
                    description="Tanggal dan sesi yang bisa dipilih orang tua. Kuota membatasi jumlah yang bisa memilih satu sesi."
                    icon={CalendarDays}
                >
                    <ExamScheduleEditor
                        key={JSON.stringify(days)}
                        rulesetId={ruleset.id}
                        initial={days}
                        canEdit={can.update}
                    />
                </SectionCard>
            </div>

            <ConfirmDialog
                open={confirmDelete}
                onOpenChange={setConfirmDelete}
                tone="danger"
                title={`Hapus aturan ${ruleset.name}?`}
                description="Pendaftar yang sudah memakai aturan ini tetap menyimpan salinannya. Yang dihapus tidak bisa dikembalikan."
                confirmLabel="Ya, hapus"
                onConfirm={() => router.delete(destroy(ruleset.id).url)}
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
