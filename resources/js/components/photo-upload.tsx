import { Camera, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export function PhotoUpload({
    value,
    onChange,
    label = 'Foto',
    hint = 'Format JPG atau PNG, maksimal 2 MB.',
    maxSizeMb = 2,
    className,
}: {
    /** URL of an already uploaded photo, if any. */
    value?: string | null;
    onChange: (file: File | null) => void;
    label?: string;
    hint?: string;
    maxSizeMb?: number;
    className?: string;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        return () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    const shownUrl = previewUrl ?? value ?? null;

    function handleFile(file: File | undefined) {
        if (!file) {
            return;
        }

        if (file.size > maxSizeMb * 1024 * 1024) {
            setError(
                `Ukuran fotonya lebih dari ${maxSizeMb} MB, coba yang lebih kecil ya.`,
            );

            return;
        }

        setError(null);
        setPreviewUrl(URL.createObjectURL(file));
        onChange(file);
    }

    function handleRemove() {
        setPreviewUrl(null);
        setError(null);
        onChange(null);

        if (inputRef.current) {
            inputRef.current.value = '';
        }
    }

    return (
        <div className={cn('flex items-center gap-4', className)}>
            <button
                type="button"
                onClick={() => inputRef.current?.click()}
                className={cn(
                    'relative flex h-28 w-24 shrink-0 items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-neutral-300 bg-neutral-50 text-lime-700 transition-colors hover:border-primary hover:bg-lime-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring dark:border-border dark:bg-card dark:text-primary',
                    shownUrl && 'border-solid border-transparent',
                )}
                aria-label={shownUrl ? `Ganti ${label}` : `Pilih ${label}`}
            >
                {shownUrl ? (
                    <img
                        src={shownUrl}
                        alt={label}
                        className="size-full object-cover"
                    />
                ) : (
                    <Camera className="size-7" />
                )}
            </button>

            <div className="grid min-w-0 gap-2">
                <p className="text-[15px] font-semibold">{label}</p>
                <p className="text-sm text-muted-foreground">{hint}</p>
                {error && (
                    <p className="text-sm font-medium text-destructive-foreground">
                        {error}
                    </p>
                )}
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        className="h-11 px-4"
                        onClick={() => inputRef.current?.click()}
                    >
                        <Camera />
                        {shownUrl ? 'Ganti foto' : 'Pilih foto'}
                    </Button>
                    {shownUrl && (
                        <Button
                            type="button"
                            variant="ghost"
                            className="h-11 px-4"
                            onClick={handleRemove}
                        >
                            <Trash2 />
                            Hapus
                        </Button>
                    )}
                </div>
            </div>

            <input
                ref={inputRef}
                type="file"
                accept="image/jpeg,image/png"
                className="sr-only"
                tabIndex={-1}
                onChange={(event) => handleFile(event.target.files?.[0])}
            />
        </div>
    );
}
