import { Bell, Search } from 'lucide-react';
import type { CSSProperties } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { accentVariables, isHexColor } from '@/lib/accent';
import { cn } from '@/lib/utils';

/**
 * A small mock of the app header and a few controls, painted with the values
 * being edited, so staff see the effect before they save.
 */
export function BrandPreview({
    name,
    logoUrl,
    showName,
    accent,
}: {
    name: string;
    logoUrl: string | null;
    showName: boolean;
    accent: string;
}) {
    const style = (
        isHexColor(accent) ? accentVariables(accent) : {}
    ) as CSSProperties;
    const initial = (name.trim()[0] ?? '?').toUpperCase();

    return (
        <div
            style={style}
            className="overflow-hidden rounded-2xl border border-line bg-background"
            aria-label="Contoh tampilan"
            role="img"
        >
            <div className="flex h-14 items-center justify-between border-b border-line px-4">
                <span className="inline-flex items-center gap-2.5">
                    {logoUrl ? (
                        <img
                            src={logoUrl}
                            alt=""
                            className="h-8 w-auto max-w-32 object-contain"
                        />
                    ) : (
                        <span className="grid size-8 place-items-center rounded-[9px] bg-primary text-lg font-extrabold text-primary-foreground">
                            {initial}
                        </span>
                    )}
                    {(showName || !logoUrl) && (
                        <span className="truncate text-lg font-extrabold tracking-tight">
                            {name || 'Nama aplikasi'}
                        </span>
                    )}
                </span>
                <span className="flex items-center gap-1 text-muted-foreground">
                    <Search className="size-4" />
                    <Bell className="size-4" />
                </span>
            </div>

            <div className="grid gap-4 p-4">
                <div className="flex gap-2">
                    {['Beranda', 'Tagihan', 'Daftar'].map((label, index) => (
                        <span
                            key={label}
                            className={cn(
                                'rounded-full px-3 py-1 text-xs font-semibold',
                                index === 0
                                    ? 'bg-lime-300 text-neutral-900'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {label}
                        </span>
                    ))}
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Button size="sm" tabIndex={-1}>
                        Tombol utama
                    </Button>
                    <Button size="sm" variant="outline" tabIndex={-1}>
                        Sekunder
                    </Button>
                    <Badge>Lencana</Badge>
                </div>
                <div className="flex items-center gap-3 rounded-xl bg-lime-50 p-3 text-sm text-lime-800">
                    <span className="grid size-8 place-items-center rounded-full bg-lime-100 text-lime-700">
                        <Bell className="size-4" />
                    </span>
                    Latar lembut dan ikon memakai warna yang sama.
                </div>
            </div>
        </div>
    );
}
