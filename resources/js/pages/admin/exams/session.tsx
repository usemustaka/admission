import { Head, Link } from '@inertiajs/react';
import { Download, ListChecks, Printer, UsersRound } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { SessionSummary } from '@/components/exams/session-summary';
import { PageHeader } from '@/components/page-header';
import { PersonAvatar } from '@/components/person-avatar';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import { index } from '@/routes/admin/exams';
import { show as showRegistrant } from '@/routes/admin/registrants';
import { exportMethod } from '@/routes/admin/exams/sessions';
import { edit as editScores } from '@/routes/admin/exams/sessions/scores';
import type { ExamSessionInfo, RosterRow } from '@/types/exam';

/**
 * Hides the admin shell while printing, so the attendance sheet comes out as a plain table.
 */
const printStyles = `
@media print {
    header.sticky, nav, main > a { display: none !important; }
    main { max-width: none !important; padding: 0 !important; }
    body { background: #fff !important; }
}
`;

export default function ExamSessionRoster({
    session,
    registrants,
    can,
}: {
    session: ExamSessionInfo;
    registrants: RosterRow[];
    can: { manage: boolean; export: boolean };
}) {
    return (
        <>
            <Head title={`Daftar hadir ${session.label}`} />
            <style>{printStyles}</style>
            <div className="grid gap-6">
                <PageHeader
                    icon={UsersRound}
                    title="Daftar hadir ujian"
                    meta={<SessionSummary session={session} />}
                    actions={
                        <span className="flex flex-wrap gap-2 print:hidden">
                            <Button
                                variant="outline"
                                onClick={() => window.print()}
                                disabled={registrants.length === 0}
                            >
                                <Printer />
                                Cetak
                            </Button>
                            {can.export && (
                                <Button variant="outline" asChild>
                                    <a href={exportMethod(session.id).url}>
                                        <Download />
                                        Unduh CSV
                                    </a>
                                </Button>
                            )}
                            <Button asChild>
                                <Link href={editScores(session.id)}>
                                    <ListChecks />
                                    {can.manage ? 'Input nilai' : 'Lihat nilai'}
                                </Link>
                            </Button>
                        </span>
                    }
                />

                {registrants.length === 0 ? (
                    <EmptyState
                        icon={UsersRound}
                        title="Belum ada peserta di sesi ini"
                        description="Peserta muncul di sini setelah orang tua atau panitia memilih sesi ini."
                    />
                ) : (
                    <div className="overflow-hidden rounded-2xl border border-line bg-card print:rounded-none print:border-0">
                        <Table className="print:text-sm">
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-12">No</TableHead>
                                    <TableHead>Nama</TableHead>
                                    <TableHead>NIK</TableHead>
                                    <TableHead className="hidden md:table-cell print:hidden">
                                        NISN
                                    </TableHead>
                                    <TableHead className="hidden md:table-cell print:hidden">
                                        Kontak orang tua
                                    </TableHead>
                                    <TableHead className="hidden lg:table-cell print:hidden">
                                        Pembayaran
                                    </TableHead>
                                    <TableHead className="hidden w-64 print:table-cell">
                                        Tanda tangan
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {registrants.map((registrant, number) => (
                                    <TableRow
                                        key={registrant.id}
                                        className="print:h-14"
                                    >
                                        <TableCell className="tabular-nums">
                                            {number + 1}
                                        </TableCell>
                                        <TableCell>
                                            <span className="flex items-center gap-3">
                                                <PersonAvatar
                                                    name={registrant.name}
                                                    src={registrant.photo_url}
                                                    size="sm"
                                                    className="print:hidden"
                                                />
                                                <span className="grid">
                                                    <Link
                                                        href={showRegistrant(
                                                            registrant.id,
                                                        )}
                                                        className="font-semibold outline-none hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring print:no-underline"
                                                    >
                                                        {registrant.name}
                                                    </Link>
                                                    <span className="text-xs text-muted-foreground">
                                                        {
                                                            registrant.organization
                                                        }
                                                        {registrant.needs_identity_review && (
                                                            <Badge
                                                                variant="warning"
                                                                className="ml-2 print:hidden"
                                                            >
                                                                Cek NIK
                                                            </Badge>
                                                        )}
                                                    </span>
                                                </span>
                                            </span>
                                        </TableCell>
                                        <TableCell className="tabular-nums">
                                            {registrant.nik ?? '-'}
                                        </TableCell>
                                        <TableCell className="hidden tabular-nums md:table-cell print:hidden">
                                            {registrant.nisn ?? '-'}
                                        </TableCell>
                                        <TableCell className="hidden md:table-cell print:hidden">
                                            {registrant.contact ? (
                                                <span className="grid">
                                                    <span>
                                                        {
                                                            registrant.contact
                                                                .name
                                                        }
                                                    </span>
                                                    <a
                                                        href={
                                                            registrant.contact
                                                                .whatsapp_url
                                                        }
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="text-xs text-muted-foreground tabular-nums hover:underline"
                                                    >
                                                        {
                                                            registrant.contact
                                                                .phone
                                                        }
                                                    </a>
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    Belum ada nomor HP
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell print:hidden">
                                            {registrant.payment === 'none' ? (
                                                <span className="text-muted-foreground">
                                                    -
                                                </span>
                                            ) : (
                                                <StatusBadge
                                                    status={registrant.payment}
                                                />
                                            )}
                                        </TableCell>
                                        <TableCell className="hidden border-b border-neutral-400 print:table-cell" />
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>
        </>
    );
}

ExamSessionRoster.layout = {
    breadcrumbs: [
        { title: 'Beranda', href: dashboard() },
        { title: 'Ujian', href: index() },
        { title: 'Daftar hadir', href: '#' },
    ],
};
