import { router } from '@inertiajs/react';
import { Check, FileText, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { toastFirstError } from '@/hooks/use-flash-toast';
import { show as showDocument } from '@/routes/documents';
import {
    destroy as destroyDocument,
    store as storeDocument,
} from '@/routes/portal/children/documents';

export type DocumentSlot = {
    slot: number;
    label: string;
    document: {
        id: number;
        name: string;
        size: number;
        mime_type: string;
    } | null;
};

function formatSize(bytes: number): string {
    return bytes >= 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/**
 * One file of a document type: shows the uploaded file with a link to open it, or a button to pick one. The file is
 * sent as soon as it is picked, so there is no extra Save step on a phone.
 */
export function DocumentSlotRow({
    registrantId,
    type,
    slot,
    maxKilobytes,
    canEdit,
}: {
    registrantId: number;
    type: string;
    slot: DocumentSlot;
    maxKilobytes: number;
    canEdit: boolean;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [busy, setBusy] = useState(false);
    const [tooBig, setTooBig] = useState(false);

    function pick(file: File | undefined) {
        if (!file) {
            return;
        }

        if (file.size > maxKilobytes * 1024) {
            setTooBig(true);

            if (inputRef.current) {
                inputRef.current.value = '';
            }

            return;
        }

        setTooBig(false);
        setBusy(true);
        router.post(
            storeDocument(registrantId).url,
            { type, slot: slot.slot, file },
            {
                forceFormData: true,
                preserveScroll: true,
                onError: (errors) => toastFirstError(errors),
                onFinish: () => {
                    setBusy(false);

                    if (inputRef.current) {
                        inputRef.current.value = '';
                    }
                },
            },
        );
    }

    function remove(id: number) {
        setBusy(true);
        router.delete(destroyDocument([registrantId, id]).url, {
            preserveScroll: true,
            onError: (errors) => toastFirstError(errors),
            onFinish: () => setBusy(false),
        });
    }

    return (
        <li className="grid gap-1.5 py-3 first:pt-0 last:pb-0">
            <div className="flex items-center justify-between gap-3">
                <div className="flex min-w-0 items-center gap-2.5">
                    {slot.document ? (
                        <Check className="size-5 shrink-0 text-success" />
                    ) : (
                        <FileText className="size-5 shrink-0 text-muted-foreground" />
                    )}
                    <div className="min-w-0">
                        <p className="text-[15px] font-medium">{slot.label}</p>
                        {slot.document ? (
                            <a
                                href={showDocument(slot.document.id).url}
                                target="_blank"
                                rel="noreferrer"
                                className="block truncate text-sm text-muted-foreground underline underline-offset-4"
                            >
                                {slot.document.name} ·{' '}
                                {formatSize(slot.document.size)}
                            </a>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Belum diunggah
                            </p>
                        )}
                    </div>
                </div>
                {canEdit && (
                    <div className="flex shrink-0 items-center gap-1">
                        <input
                            ref={inputRef}
                            type="file"
                            accept="application/pdf,image/jpeg"
                            className="sr-only"
                            aria-label={`Pilih berkas ${slot.label}`}
                            onChange={(e) => pick(e.target.files?.[0])}
                        />
                        <Button
                            type="button"
                            size="sm"
                            variant={slot.document ? 'outline' : 'default'}
                            disabled={busy}
                            onClick={() => inputRef.current?.click()}
                        >
                            {busy ? <Spinner /> : <Upload />}
                            {slot.document ? 'Ganti' : 'Unggah'}
                        </Button>
                        {slot.document && (
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                aria-label={`Hapus ${slot.label}`}
                                disabled={busy}
                                onClick={() =>
                                    slot.document && remove(slot.document.id)
                                }
                            >
                                <Trash2 />
                            </Button>
                        )}
                    </div>
                )}
            </div>
            {tooBig && (
                <p className="text-sm font-medium text-destructive-foreground">
                    Ukuran berkas lebih dari 1 MB. Perkecil dulu ya, atau foto
                    ulang dengan ukuran lebih kecil.
                </p>
            )}
        </li>
    );
}
