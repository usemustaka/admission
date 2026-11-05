import { router } from '@inertiajs/react';
import { useState } from 'react';
import { SlotPicker } from '@/components/slot-picker';
import type { SlotDay } from '@/components/slot-picker';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { toastFirstError } from '@/hooks/use-flash-toast';
import { examSession } from '@/routes/admin/registrants';

/**
 * The committee's way to change the exam session of a registration. Full sessions can be chosen too: the
 * picker then asks for a reason, and both go to the server, which keeps them in a note on the child.
 */
export function ExamSessionForm({
    registrantId,
    days,
    selected,
}: {
    registrantId: number;
    days: SlotDay[];
    /** Id of the session the registration holds now, if any. */
    selected: string | null;
}) {
    const [session, setSession] = useState<string | undefined>(
        selected ?? undefined,
    );
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);
    const [reasonError, setReasonError] = useState<string>();

    const chosen = days
        .flatMap((day) => day.sessions)
        .find((candidate) => candidate.id === session);
    const overriding =
        chosen !== undefined &&
        chosen.id !== selected &&
        chosen.taken >= chosen.quota;

    function save() {
        setProcessing(true);
        setReasonError(undefined);
        router.put(
            examSession(registrantId).url,
            {
                exam_session_id: session,
                override_full: overriding,
                override_reason: overriding ? reason : undefined,
            },
            {
                preserveScroll: true,
                onError: (errors) => {
                    setReasonError(errors.override_reason);
                    toastFirstError(errors, (key) => key === 'override_reason');
                },
                onSuccess: () => setReason(''),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <div className="grid gap-3">
            <SlotPicker
                days={days}
                value={session}
                onValueChange={setSession}
                allowFull
                currentValue={selected ?? undefined}
                overrideReason={reason}
                onOverrideReasonChange={setReason}
                overrideError={reasonError}
            />
            <div>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={
                        processing ||
                        session === undefined ||
                        session === selected ||
                        (overriding && reason.trim() === '')
                    }
                    onClick={save}
                >
                    {processing && <Spinner />}
                    Ubah jadwal
                </Button>
            </div>
        </div>
    );
}
