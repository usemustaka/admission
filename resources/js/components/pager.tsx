import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types/people';

/**
 * Previous / next controls under a server-paginated list. Renders nothing
 * when everything fits on one page.
 */
export function Pager({
    page,
    noun,
}: {
    page: Pick<
        Paginated<unknown>,
        | 'current_page'
        | 'last_page'
        | 'from'
        | 'to'
        | 'total'
        | 'prev_page_url'
        | 'next_page_url'
    >;
    /** What is being counted, e.g. "pendaftar". */
    noun: string;
}) {
    if (page.last_page <= 1) {
        return null;
    }

    return (
        <nav
            className="flex flex-wrap items-center justify-between gap-3"
            aria-label="Halaman"
        >
            <p className="text-sm text-muted-foreground tabular-nums">
                {page.from}–{page.to} dari {page.total} {noun} · Halaman{' '}
                {page.current_page} dari {page.last_page}
            </p>
            <div className="flex gap-2">
                {page.prev_page_url ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={page.prev_page_url} preserveScroll>
                            <ChevronLeft />
                            Sebelumnya
                        </Link>
                    </Button>
                ) : (
                    <Button variant="outline" size="sm" disabled>
                        <ChevronLeft />
                        Sebelumnya
                    </Button>
                )}
                {page.next_page_url ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={page.next_page_url} preserveScroll>
                            Berikutnya
                            <ChevronRight />
                        </Link>
                    </Button>
                ) : (
                    <Button variant="outline" size="sm" disabled>
                        Berikutnya
                        <ChevronRight />
                    </Button>
                )}
            </div>
        </nav>
    );
}
