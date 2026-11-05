import { TriangleAlert } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

/**
 * What is not finished yet when the committee is about to announce. Announcing stays possible; this only makes
 * sure nobody does it by accident.
 */
export function AnnounceWarnings({ warnings }: { warnings: string[] }) {
    if (warnings.length === 0) {
        return null;
    }

    return (
        <Alert variant="warning">
            <TriangleAlert />
            <AlertTitle>Ada yang belum selesai</AlertTitle>
            <AlertDescription>
                <ul className="grid list-disc gap-0.5 pl-5">
                    {warnings.map((warning) => (
                        <li key={warning}>{warning}</li>
                    ))}
                </ul>
            </AlertDescription>
        </Alert>
    );
}
