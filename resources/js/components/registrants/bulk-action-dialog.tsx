import { router } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { bulk, bulkPreview } from '@/routes/admin/registrants';
import type { BulkAction, BulkPreview } from '@/types/registrant';

/** Who a bulk action is about: rows ticked on the page, or every result of the current filters. */
export type BulkSelection =
    | { mode: 'ids'; ids: number[] }
    | {
          mode: 'filters';
          filters: Record<string, string | number | boolean | undefined>;
          total: number;
      };

const NOTE_MAX = 1000;

const copy: Record<
    BulkAction,
    { title: (count: number) => string; description: string; confirm: string }
> = {
    accept: {
        title: (count) => `Terima data ${count} pendaftar?`,
        description:
            'Data dinyatakan valid dan tagihan pelunasan terbit kalau aturannya punya. Yang bukan berstatus Sedang diperiksa atau masih perlu cek NIK dilewati.',
        confirm: 'Ya, terima',
    },
    announce: {
        title: (count) => `Tandai ${count} pendaftar sudah diumumkan?`,
        description:
            'Pastikan pengumumannya sudah dikirim ke kontak utama. Yang bukan berstatus Data valid atau masih perlu cek NIK dilewati.',
        confirm: 'Ya, tandai',
    },
    revise: {
        title: (count) => `Minta ${count} pendaftar memperbaiki data?`,
        description:
            'Data dikembalikan ke orang tua beserta alasan di bawah. Yang bukan berstatus Sedang diperiksa atau masih perlu cek NIK dilewati.',
        confirm: 'Ya, kembalikan',
    },
};

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function requestBody(
    action: BulkAction,
    selection: BulkSelection,
    includeIncomplete: boolean,
) {
    return {
        action,
        include_incomplete: includeIncomplete,
        ...(selection.mode === 'ids'
            ? { ids: selection.ids }
            : { select_all: true, filters: selection.filters }),
    };
}

/**
 * Asks the server what a bulk run would do (who qualifies, who is skipped and why) before anything changes.
 * Re-asks when the choice that decides it (including incomplete announcements) changes.
 */
function usePreview(
    action: BulkAction,
    selection: BulkSelection,
    includeIncomplete: boolean,
) {
    const [state, setState] = useState<{
        key: string;
        preview: BulkPreview | null;
        failed: boolean;
    } | null>(null);
    const key = useMemo(
        () => JSON.stringify(requestBody(action, selection, includeIncomplete)),
        [action, selection, includeIncomplete],
    );

    useEffect(() => {
        const controller = new AbortController();

        fetch(bulkPreview().url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: key,
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                return response.json() as Promise<BulkPreview>;
            })
            .then((preview) => setState({ key, preview, failed: false }))
            .catch((error: unknown) => {
                if ((error as Error).name !== 'AbortError') {
                    setState({ key, preview: null, failed: true });
                }
            });

        return () => controller.abort();
    }, [key]);

    const current = state?.key === key ? state : null;

    return {
        loading: current === null,
        preview: current?.preview ?? null,
        failed: current?.failed ?? false,
    };
}

function PreviewSkeleton() {
    return (
        <div className="grid gap-2" aria-busy="true" aria-label="Memuat">
            <Skeleton className="h-6 w-1/2" />
            <Skeleton className="h-4 w-3/4" />
            <Skeleton className="h-4 w-2/3" />
        </div>
    );
}

function PreviewSummary({ preview }: { preview: BulkPreview }) {
    return (
        <div className="grid gap-2 rounded-2xl border border-line p-4 text-sm">
            <p className="text-base font-semibold tabular-nums">
                {preview.eligible} layak, {preview.skipped} dilewati
            </p>
            {preview.capped && (
                <p className="text-muted-foreground">
                    Dari {preview.total} hasil, {preview.cap} pertama yang
                    diproses sekali jalan. Jalankan lagi untuk sisanya.
                </p>
            )}
            {preview.reasons.length > 0 && (
                <ul className="grid gap-1 text-muted-foreground">
                    {preview.reasons.map((item) => (
                        <li
                            key={item.reason}
                            className="flex justify-between gap-3"
                        >
                            <span>{item.reason}</span>
                            <span className="font-semibold text-foreground tabular-nums">
                                {item.count}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function IncompleteWarning({
    readiness,
    includeIncomplete,
    onIncludeIncompleteChange,
}: {
    readiness: NonNullable<BulkPreview['readiness']>;
    includeIncomplete: boolean;
    onIncludeIncompleteChange: (include: boolean) => void;
}) {
    if (readiness.incomplete === 0) {
        return null;
    }

    return (
        <Alert variant="warning">
            <TriangleAlert />
            <AlertTitle>
                {readiness.missing_scores} tanpa nilai, {readiness.not_passed}{' '}
                belum tuntas, {readiness.unsettled} belum lunas
            </AlertTitle>
            <AlertDescription>
                <p>
                    {readiness.incomplete} pendaftar belum lengkap. Secara
                    bawaan mereka dilewati.
                </p>
                <Label className="mt-3 flex items-start gap-2.5 font-medium">
                    <Checkbox
                        checked={includeIncomplete}
                        onCheckedChange={(checked) =>
                            onIncludeIncompleteChange(checked === true)
                        }
                        className="mt-0.5"
                    />
                    Umumkan juga yang belum lengkap
                </Label>
            </AlertDescription>
        </Alert>
    );
}

function BulkActionContent({
    action,
    selection,
    onClose,
    onDone,
}: {
    action: BulkAction;
    selection: BulkSelection;
    onClose: () => void;
    onDone: () => void;
}) {
    const [includeIncomplete, setIncludeIncomplete] = useState(false);
    const [note, setNote] = useState('');
    const [noteError, setNoteError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const { loading, preview, failed } = usePreview(
        action,
        selection,
        includeIncomplete,
    );
    const count =
        selection.mode === 'ids' ? selection.ids.length : selection.total;
    const text = copy[action];
    const needsNote = action === 'revise';
    const canConfirm =
        !processing &&
        !loading &&
        (preview === null || preview.eligible > 0) &&
        (!needsNote || note.trim() !== '');

    function run() {
        setProcessing(true);
        setNoteError(null);

        router.post(
            bulk().url,
            {
                ...requestBody(action, selection, includeIncomplete),
                ...(needsNote ? { note: note.trim() } : {}),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: onDone,
                onError: (errors) => {
                    setNoteError(errors.note ?? null);
                    toast.error(
                        Object.values(errors)[0] ??
                            'Pendaftar belum bisa diproses. Coba lagi ya.',
                    );
                },
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{text.title(count)}</DialogTitle>
                <DialogDescription>{text.description}</DialogDescription>
            </DialogHeader>

            {loading ? (
                <PreviewSkeleton />
            ) : preview ? (
                <PreviewSummary preview={preview} />
            ) : (
                failed && (
                    <Alert variant="warning">
                        <TriangleAlert />
                        <AlertTitle>Pratinjau belum bisa dimuat</AlertTitle>
                        <AlertDescription>
                            Kamu tetap bisa lanjut; hasilnya terlihat setelah
                            selesai.
                        </AlertDescription>
                    </Alert>
                )
            )}

            {action === 'announce' && preview?.readiness && (
                <IncompleteWarning
                    readiness={preview.readiness}
                    includeIncomplete={includeIncomplete}
                    onIncludeIncompleteChange={setIncludeIncomplete}
                />
            )}

            {needsNote && (
                <div className="grid gap-2">
                    <Label htmlFor="bulk-note">
                        Apa yang perlu diperbaiki?
                    </Label>
                    <Textarea
                        id="bulk-note"
                        value={note}
                        maxLength={NOTE_MAX}
                        onChange={(event) => setNote(event.target.value)}
                        aria-invalid={noteError !== null}
                        placeholder="Contoh: Foto KK kurang jelas, mohon unggah ulang."
                    />
                    <p className="text-xs text-muted-foreground tabular-nums">
                        Orang tua membaca alasan ini di portal. Satu alasan yang
                        sama dikirim ke semua pendaftar yang layak.{' '}
                        {note.length}/{NOTE_MAX}
                    </p>
                    {noteError && (
                        <p className="text-sm text-destructive">{noteError}</p>
                    )}
                </div>
            )}

            <DialogFooter className="gap-2">
                <Button
                    variant="outline"
                    disabled={processing}
                    onClick={onClose}
                >
                    Batal
                </Button>
                <Button disabled={!canConfirm} onClick={run}>
                    {processing && <Spinner />}
                    {text.confirm}
                    {preview &&
                        preview.eligible > 0 &&
                        ` (${preview.eligible})`}
                </Button>
            </DialogFooter>
        </DialogContent>
    );
}

/**
 * The confirmation step of a bulk action: it shows a server-side preview ("N layak, M dilewati" with the top
 * reasons) before anything is changed, then runs the action on the selection.
 */
export function BulkActionDialog({
    action,
    selection,
    onClose,
    onDone,
}: {
    action: BulkAction | null;
    selection: BulkSelection;
    onClose: () => void;
    onDone: () => void;
}) {
    return (
        <Dialog
            open={action !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            {action && (
                <BulkActionContent
                    action={action}
                    selection={selection}
                    onClose={onClose}
                    onDone={onDone}
                />
            )}
        </Dialog>
    );
}
