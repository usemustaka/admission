import { Head, Link } from '@inertiajs/react';
import { ListChecks, UsersRound } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { ScoreGrid } from '@/components/exams/score-grid';
import { SessionSummary } from '@/components/exams/session-summary';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index } from '@/routes/admin/exams';
import { show } from '@/routes/admin/exams/sessions';
import type { ExamSessionInfo, GridAspect, GridRow } from '@/types/exam';

export default function ExamScores({
    session,
    aspects,
    rows,
    can,
}: {
    session: ExamSessionInfo;
    aspects: GridAspect[];
    rows: GridRow[];
    can: { manage: boolean };
}) {
    return (
        <>
            <Head title={`Nilai ${session.label}`} />
            <div className="grid gap-6">
                <PageHeader
                    icon={ListChecks}
                    title="Input nilai ujian"
                    meta={<SessionSummary session={session} />}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={show(session.id)}>
                                <UsersRound />
                                Daftar hadir
                            </Link>
                        </Button>
                    }
                />

                {!can.manage && rows.length > 0 && (
                    <Alert variant="info">
                        <AlertDescription>
                            Kamu hanya bisa melihat nilai. Mengisi nilai butuh
                            izin mengelola pendaftar.
                        </AlertDescription>
                    </Alert>
                )}

                {rows.length === 0 ? (
                    <EmptyState
                        icon={UsersRound}
                        title="Belum ada peserta di sesi ini"
                        description="Nilai bisa diisi setelah ada pendaftar yang memilih sesi ini."
                    />
                ) : aspects.length === 0 ? (
                    <EmptyState
                        icon={ListChecks}
                        title="Aturan pendaftaran ini belum punya aspek ujian"
                        description="Tambahkan aspek ujian di aturan pendaftaran dulu, lalu nilai bisa diisi di sini."
                    />
                ) : (
                    <ScoreGrid
                        key={session.id}
                        sessionId={session.id}
                        aspects={aspects}
                        rows={rows}
                        canManage={can.manage}
                    />
                )}
            </div>
        </>
    );
}

ExamScores.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Ujian', href: index() },
        { title: 'Input nilai', href: '#' },
    ],
};
