import { FileArchive, FileText, TriangleAlert } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { show as showDocument } from '@/routes/documents';
import registrantRoutes from '@/routes/admin/registrants';

export type CommitteeDocuments = {
    groups: {
        type: string;
        label: string;
        required: boolean | null;
        max_files: number;
        files: {
            id: number;
            slot: number;
            label: string;
            name: string;
            size: number;
        }[];
    }[];
    missing: string[];
    kip_number: string | null;
    support_note: string | null;
    count: number;
};

function formatSize(bytes: number): string {
    return bytes >= 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/**
 * The files a parent uploaded, opened one by one or all together as a ZIP, with the required ones that are still
 * missing called out.
 */
export function DocumentsCard({
    registrantId,
    documents,
}: {
    registrantId: number;
    documents: CommitteeDocuments;
}) {
    return (
        <div className="grid gap-4">
            {documents.missing.length > 0 && (
                <Alert variant="warning">
                    <TriangleAlert />
                    <AlertDescription>
                        Berkas wajib belum lengkap:{' '}
                        {documents.missing.join(', ')}.
                    </AlertDescription>
                </Alert>
            )}

            {documents.groups.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    Orang tua belum mengunggah berkas.
                </p>
            )}

            {documents.groups.length > 0 && (
                <div className="grid divide-y divide-line">
                    {documents.groups.map((group) => (
                        <section
                            key={group.type}
                            className="grid gap-1.5 py-3 first:pt-0 last:pb-0"
                        >
                            <h3 className="flex flex-wrap items-center gap-2 text-sm font-semibold">
                                {group.label}
                                {group.required === true && (
                                    <Badge variant="warning">Wajib</Badge>
                                )}
                                {group.required === false && (
                                    <Badge variant="muted">Opsional</Badge>
                                )}
                            </h3>
                            {group.files.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Belum diunggah
                                </p>
                            ) : (
                                <ul className="grid">
                                    {group.files.map((file) => (
                                        <li key={file.id}>
                                            <a
                                                href={showDocument(file.id).url}
                                                target="_blank"
                                                rel="noreferrer"
                                                title={file.name}
                                                className="-mx-2 flex min-h-9 items-center gap-2.5 rounded-lg px-2 py-1 text-sm outline-none hover:bg-accent focus-visible:outline-2 focus-visible:outline-ring"
                                            >
                                                <FileText className="size-4 shrink-0 text-muted-foreground" />
                                                <span className="min-w-0 flex-1 truncate">
                                                    {group.max_files > 1
                                                        ? file.label
                                                        : file.name}
                                                </span>
                                                <span className="text-xs text-muted-foreground tabular-nums">
                                                    {formatSize(file.size)}
                                                </span>
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    ))}
                </div>
            )}

            {(documents.kip_number || documents.support_note) && (
                <dl className="grid gap-3 border-t border-line pt-4 text-sm">
                    {documents.kip_number && (
                        <div className="grid gap-0.5">
                            <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Nomor KIP
                            </dt>
                            <dd className="font-medium tabular-nums">
                                {documents.kip_number}
                            </dd>
                        </div>
                    )}
                    {documents.support_note && (
                        <div className="grid gap-0.5">
                            <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Keterangan orang tua
                            </dt>
                            <dd className="font-medium whitespace-pre-line">
                                {documents.support_note}
                            </dd>
                        </div>
                    )}
                </dl>
            )}

            {documents.count > 0 && (
                <div>
                    <Button variant="outline" size="sm" asChild>
                        <a
                            href={registrantRoutes.documents.zip.url(
                                registrantId,
                            )}
                        >
                            <FileArchive />
                            Unduh semua (ZIP)
                        </a>
                    </Button>
                </div>
            )}
        </div>
    );
}
