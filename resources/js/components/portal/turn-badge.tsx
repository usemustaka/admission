import { CircleCheck, Clock, Hand } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type { Turn } from '@/lib/portal-next-step';

const meta = {
    parent: { variant: 'default', icon: Hand },
    committee: { variant: 'info', icon: Clock },
    done: { variant: 'success', icon: CircleCheck },
} as const;

/**
 * Says whose turn it is: the parent's, the committee's, or nobody's. The icon
 * and the words carry the meaning, the colour only backs them up.
 */
export function TurnBadge({ turn, label }: { turn: Turn; label: string }) {
    const { variant, icon: Icon } = meta[turn];

    return (
        <Badge variant={variant} className="gap-1.5 px-3 py-1 text-[13px]">
            <Icon />
            {label}
        </Badge>
    );
}
