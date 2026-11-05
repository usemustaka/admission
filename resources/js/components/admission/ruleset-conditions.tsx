import { Badge } from '@/components/ui/badge';
import type { RulesetSummary } from '@/types/admission';

/**
 * The "who is this for" of a ruleset as readable chips.
 */
export function RulesetConditions({ ruleset }: { ruleset: RulesetSummary }) {
    return (
        <div className="flex flex-wrap gap-1.5">
            <Badge variant="secondary">
                {ruleset.wave ?? 'Semua gelombang'}
            </Badge>
            <Badge variant="secondary">
                {ruleset.organization ?? 'Semua lembaga'}
            </Badge>
            <Badge variant="secondary">
                {ruleset.gender ?? 'Semua jenis kelamin'}
            </Badge>
        </div>
    );
}
