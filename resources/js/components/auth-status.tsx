import { CircleCheck } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { translateStatus } from '@/lib/auth-status';

/**
 * Confirmation message above an auth form, e.g. "link sent to your email".
 */
export default function AuthStatus({ status }: { status?: string }) {
    if (!status) {
        return null;
    }

    return (
        <Alert variant="success">
            <CircleCheck />
            <AlertDescription className="col-start-2 text-[15px] text-emerald-900 dark:text-emerald-200">
                {translateStatus(status)}
            </AlertDescription>
        </Alert>
    );
}
