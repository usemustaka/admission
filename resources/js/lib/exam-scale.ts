/**
 * How an exam aspect is graded. Mirrors App\Enums\ExamScale: a level is stored as the number 0-100 it stands for, so
 * the KKM of a level scale is the lowest level that still passes.
 */
export type ExamScale =
    | 'number'
    | 'good-fair'
    | 'good-only'
    | 'memorized-fair'
    | 'memorized-only'
    | 'able-fair'
    | 'able-only';

export type ScaleLevel = { value: number; label: string };

export const SCALE_OPTIONS: { value: ExamScale; label: string }[] = [
    { value: 'number', label: 'Angka (0-100)' },
    { value: 'good-fair', label: 'Baik / Kurang / Belum' },
    { value: 'good-only', label: 'Baik / Belum' },
    { value: 'memorized-fair', label: 'Hafal / Kurang / Belum' },
    { value: 'memorized-only', label: 'Hafal / Belum' },
    { value: 'able-fair', label: 'Bisa / Kurang / Belum' },
    { value: 'able-only', label: 'Bisa / Belum' },
];

const TOP: Record<string, string> = {
    good: 'Baik',
    memorized: 'Hafal',
    able: 'Bisa',
};

/** Levels from best to worst; empty for a number. */
export function levelsOf(scale: string | undefined): ScaleLevel[] {
    const [kind, mode] = (scale ?? 'number').split('-');
    const top = TOP[kind];

    if (!top) {
        return [];
    }

    return mode === 'fair'
        ? [
              { value: 100, label: top },
              { value: 50, label: 'Kurang' },
              { value: 0, label: 'Belum' },
          ]
        : [
              { value: 100, label: top },
              { value: 0, label: 'Belum' },
          ];
}

export function isNumeric(scale: string | undefined): boolean {
    return levelsOf(scale).length === 0;
}

/** A stored score as people read it: the number, or the name of its level. */
export function scoreLabel(
    scale: string | undefined,
    score: number | string | null | undefined,
): string {
    if (score === null || score === undefined || score === '') {
        return '-';
    }

    return (
        levelsOf(scale).find((level) => level.value === Number(score))?.label ??
        String(score)
    );
}

/** The levels that can be a KKM: "Belum" never can. */
export function passingLevels(scale: string | undefined): ScaleLevel[] {
    return levelsOf(scale).filter((level) => level.value > 0);
}
