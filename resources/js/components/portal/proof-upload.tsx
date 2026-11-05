import { Camera, FileText, RefreshCw, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { FormFieldControlProps } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const MAX_SIZE_MB = 4;

function formatSize(bytes: number): string {
    return bytes >= 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/**
 * Picks the transfer proof from a phone's camera, gallery or files. The whole
 * area is one big tap target; after picking, a photo preview (or the file
 * name for a PDF) replaces the prompt.
 */
export function ProofUpload({
    value,
    onChange,
    control,
}: {
    value: File | null;
    onChange: (file: File | null) => void;
    control: FormFieldControlProps;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [error, setError] = useState<string | null>(null);

    const previewUrl = useMemo(
        () =>
            value !== null && value.type.startsWith('image/')
                ? URL.createObjectURL(value)
                : null,
        [value],
    );

    useEffect(() => {
        return () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    function pick(file: File | undefined) {
        if (!file) {
            return;
        }

        if (file.size > MAX_SIZE_MB * 1024 * 1024) {
            setError(
                `Ukuran filenya lebih dari ${MAX_SIZE_MB} MB. Coba foto ulang dengan ukuran lebih kecil ya.`,
            );

            if (inputRef.current) {
                inputRef.current.value = '';
            }

            return;
        }

        setError(null);
        onChange(file);
    }

    function clear() {
        setError(null);
        onChange(null);

        if (inputRef.current) {
            inputRef.current.value = '';
        }
    }

    return (
        <div className="grid gap-3">
            <input
                {...control}
                ref={inputRef}
                type="file"
                accept="image/jpeg,image/png,application/pdf"
                className="peer sr-only"
                onChange={(event) => pick(event.target.files?.[0])}
            />

            {value === null ? (
                <label
                    htmlFor={control.id}
                    className={cn(
                        'flex min-h-28 cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-neutral-300 bg-neutral-50 px-4 py-6 text-center transition-colors peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ring hover:border-neutral-500 dark:border-border dark:bg-card',
                        control['aria-invalid'] && 'border-destructive',
                    )}
                >
                    <span className="grid size-12 place-items-center rounded-full bg-lime-100 text-lime-700 dark:bg-lime-400/15 dark:text-lime-300">
                        <Camera className="size-6" />
                    </span>
                    <span className="text-base font-semibold">
                        Ketuk untuk pilih foto atau PDF
                    </span>
                    <span className="text-sm text-muted-foreground">
                        Bisa dari kamera atau galeri
                    </span>
                </label>
            ) : (
                <div className="grid gap-3 rounded-2xl border border-line p-3">
                    <div className="flex items-center gap-3">
                        {previewUrl ? (
                            <img
                                src={previewUrl}
                                alt="Bukti pembayaran yang dipilih"
                                className="size-20 shrink-0 rounded-xl object-cover"
                            />
                        ) : (
                            <span className="grid size-20 shrink-0 place-items-center rounded-xl bg-neutral-100 text-muted-foreground dark:bg-muted">
                                <FileText className="size-8" />
                            </span>
                        )}
                        <div className="grid min-w-0 gap-0.5">
                            <p className="truncate text-[15px] font-semibold">
                                {value.name}
                            </p>
                            <p className="text-sm text-muted-foreground tabular-nums">
                                {formatSize(value.size)}
                            </p>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="lg"
                            onClick={() => inputRef.current?.click()}
                        >
                            <RefreshCw />
                            Ganti
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="lg"
                            onClick={clear}
                        >
                            <Trash2 />
                            Hapus
                        </Button>
                    </div>
                </div>
            )}

            {error && (
                <p className="text-[15px] font-medium text-destructive-foreground">
                    {error}
                </p>
            )}
        </div>
    );
}
