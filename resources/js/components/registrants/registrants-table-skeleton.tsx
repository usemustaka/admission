import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Stands in for the registrant table while another page, sort or filter loads, so the list does not flash
 * old rows under the new filters.
 */
export function RegistrantsTableSkeleton({ rows = 8 }: { rows?: number }) {
    return (
        <Card
            className="gap-0 overflow-hidden p-0"
            aria-busy="true"
            aria-label="Memuat daftar pendaftar"
        >
            <div className="flex items-center gap-4 border-b border-line px-4 py-3.5">
                <Skeleton className="size-5" />
                <Skeleton className="h-4 w-28" />
                <Skeleton className="ml-auto hidden h-4 w-20 md:block" />
                <Skeleton className="h-4 w-16" />
            </div>
            {Array.from({ length: rows }, (_, index) => (
                <div
                    key={index}
                    className="flex items-center gap-4 border-b border-line px-4 py-3 last:border-b-0"
                >
                    <Skeleton className="size-5" />
                    <Skeleton className="size-8 rounded-full" />
                    <div className="grid flex-1 gap-1.5">
                        <Skeleton className="h-4 w-1/2 max-w-56" />
                        <Skeleton className="h-3 w-1/3 max-w-36" />
                    </div>
                    <Skeleton className="hidden h-4 w-24 md:block" />
                    <Skeleton className="h-6 w-28 rounded-full" />
                </div>
            ))}
        </Card>
    );
}
