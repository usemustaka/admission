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
            <div className="grid gap-6">
                <PageHeader
                    icon={ListChecks}
                    title={ruleset.name}
                    description="Isi tagihan, aspek ujian, dan jadwal ujian untuk pendaftar yang cocok dengan syarat aturan ini."
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
