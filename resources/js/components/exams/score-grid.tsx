import { Link, router } from '@inertiajs/react';
import { Check, Save, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { toast } from 'sonner';
import { PersonAvatar } from '@/components/person-avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { toastFirstError } from '@/hooks/use-flash-toast';
import { cn } from '@/lib/utils';
import { show as showRegistrant } from '@/routes/admin/registrants';
import { update } from '@/routes/admin/exams/sessions/scores';
import type { GridAspect, GridRow } from '@/types/exam';

type Result = 'passed' | 'failed' | 'incomplete' | null;

function toText(score: number | null | undefined): string {
    return score === null || score === undefined ? '' : String(score);
}

function resultOf(aspects: GridAspect[], values: string[]): Result {
    if (aspects.length === 0) {
        return null;
    }

    const scores = aspects.map((_, index) =>
        values[index] === undefined || values[index] === ''
            ? null
            : Number(values[index]),
    );

    if (scores.some((score) => score === null)) {
        return 'incomplete';
    }

    return scores.every(
        (score, index) => (score as number) >= aspects[index].passing_score,
    )
        ? 'passed'
        : 'failed';
}

/**
 * Scores of one exam session as a grid: a row per registration, a column per aspect. Tab moves to the next
 * cell, Enter one row down, and Ctrl/Cmd+S saves. Only the cells that changed are sent, and every row says
 * on its own whether it was saved.
 */
export function ScoreGrid({
    sessionId,
    aspects,
    rows,
    canManage,
}: {
    sessionId: number;
    aspects: GridAspect[];
    rows: GridRow[];
    canManage: boolean;
}) {
    const [values, setValues] = useState<Record<number, string[]>>(() =>
        Object.fromEntries(rows.map((row) => [row.id, row.scores.map(toText)])),
    );
    const [rowErrors, setRowErrors] = useState<Record<number, string>>({});
    const [confirmAnnounced, setConfirmAnnounced] = useState(false);
    const [processing, setProcessing] = useState(false);
    const inputs = useRef(new Map<string, HTMLInputElement>());

    const changedCells = (row: GridRow): Record<number, number | null> => {
        const changes: Record<number, number | null> = {};

        row.aspects.forEach((_, index) => {
            const current = values[row.id]?.[index] ?? '';

            if (current !== toText(row.scores[index])) {
                changes[index] = current === '' ? null : Number(current);
            }
        });

        return changes;
    };

    const dirtyRows = rows.filter(
        (row) => Object.keys(changedCells(row)).length > 0,
    );
    const needsAnnouncedConfirmation = dirtyRows.some((row) => row.announced);

    function save() {
        if (!canManage || processing || dirtyRows.length === 0) {
            return;
        }

        setProcessing(true);
        router.put(
            update(sessionId).url,
            {
                rows: Object.fromEntries(
                    dirtyRows.map((row) => [row.id, changedCells(row)]),
                ),
                confirm_after_announce: confirmAnnounced,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setRowErrors({}),
                onError: (errors) => {
                    setRowErrors(
                        Object.fromEntries(
                            Object.entries(errors)
                                .filter(([key]) => key.startsWith('rows.'))
                                .map(([key, message]) => [
                                    Number(key.slice('rows.'.length)),
                                    message,
                                ]),
                        ),
                    );

                    if (errors.summary) {
                        toast.error(errors.summary);
                    } else {
                        toastFirstError(errors);
                    }
                },
                onFinish: () => setProcessing(false),
            },
        );
    }

    const saveRef = useRef(save);

    useEffect(() => {
        saveRef.current = save;
    });

    useEffect(() => {
        function onKeyDown(event: globalThis.KeyboardEvent) {
            if (
                (event.metaKey || event.ctrlKey) &&
                event.key.toLowerCase() === 's'
            ) {
                event.preventDefault();
                saveRef.current();
            }
        }

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    useEffect(() => {
        if (dirtyRows.length === 0) {
            return;
        }

        function warn(event: BeforeUnloadEvent) {
            event.preventDefault();
        }

        window.addEventListener('beforeunload', warn);

        return () => window.removeEventListener('beforeunload', warn);
    }, [dirtyRows.length]);

    function setCell(row: GridRow, index: number, raw: string) {
        const digits = raw.replace(/\D/gu, '').slice(0, 3);
        const next = digits === '' ? '' : String(Number(digits));

        setValues((current) => {
            const cells = [...(current[row.id] ?? [])];
            cells[index] = next;

            return { ...current, [row.id]: cells };
        });
        setRowErrors((current) => {
            if (!(row.id in current)) {
                return current;
            }

            const { [row.id]: _removed, ...rest } = current;

            return rest;
        });
    }

    function focusCell(rowIndex: number, column: number, step: 1 | -1) {
        for (
            let index = rowIndex + step;
            index >= 0 && index < rows.length;
            index += step
        ) {
            const input = inputs.current.get(`${index}:${column}`);

            if (input && !input.disabled) {
                input.focus();
                input.select();

                return;
            }
        }
    }

    function onKeyDown(
        event: KeyboardEvent<HTMLInputElement>,
        rowIndex: number,
        column: number,
    ) {
        if (event.key === 'Enter' || event.key === 'ArrowDown') {
            event.preventDefault();
            focusCell(rowIndex, column, event.shiftKey ? -1 : 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            focusCell(rowIndex, column, -1);
        }
    }

    return (
        <div className="grid gap-4">
            <div className="overflow-x-auto rounded-2xl border border-line bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="sticky left-0 z-10 min-w-56 bg-card">
                                Pendaftar
                            </TableHead>
                            {aspects.map((aspect, index) => (
                                <TableHead key={index} className="min-w-28">
                                    <span className="grid normal-case">
                                        <span>{aspect.name}</span>
                                        <span className="text-xs font-medium text-muted-foreground">
                                            KKM {aspect.passing_score}
                                        </span>
                                    </span>
                                </TableHead>
                            ))}
                            <TableHead className="min-w-36">Hasil</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.map((row, rowIndex) => {
                            const cells = values[row.id] ?? [];
                            const dirty =
                                Object.keys(changedCells(row)).length > 0;
                            const result = resultOf(row.aspects, cells);
                            const error = rowErrors[row.id];

                            return (
                                <TableRow
                                    key={row.id}
                                    aria-invalid={Boolean(error)}
                                    className={cn(
                                        dirty &&
                                            'bg-lime-50 hover:bg-lime-50 dark:bg-lime-400/10 dark:hover:bg-lime-400/10',
                                        error &&
                                            'bg-red-50 hover:bg-red-50 dark:bg-red-500/10 dark:hover:bg-red-500/10',
                                    )}
                                >
                                    <TableCell
                                        className={cn(
                                            'sticky left-0 z-10',
                                            error
                                                ? 'bg-red-50 dark:bg-neutral-900'
                                                : dirty
                                                  ? 'bg-lime-50 dark:bg-neutral-900'
                                                  : 'bg-card',
                                        )}
                                    >
                                        <span className="flex items-center gap-3">
                                            <PersonAvatar
                                                name={row.name}
                                                src={row.photo_url}
                                                size="sm"
                                            />
                                            <span className="grid">
                                                <Link
                                                    href={showRegistrant(
                                                        row.id,
                                                    )}
                                                    className="font-semibold outline-none hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                                    tabIndex={-1}
                                                >
                                                    {row.name}
                                                </Link>
                                                <span className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground tabular-nums">
                                                    {row.nik ?? 'Belum ada NIK'}
                                                    {row.announced && (
                                                        <Badge variant="info">
                                                            {row.status_label}
                                                        </Badge>
                                                    )}
                                                </span>
                                                {error && (
                                                    <span
                                                        role="alert"
                                                        className="mt-1 text-sm font-medium text-destructive-foreground"
                                                    >
                                                        {error}
                                                    </span>
                                                )}
                                            </span>
                                        </span>
                                    </TableCell>
                                    {aspects.map((aspect, column) => {
                                        const available =
                                            column < row.aspects.length;
                                        const kkm =
                                            row.aspects[column]
                                                ?.passing_score ??
                                            aspect.passing_score;
                                        const text = cells[column] ?? '';
                                        const outOfRange = Number(text) > 100;

                                        return (
                                            <TableCell key={column}>
                                                <Input
                                                    ref={(element) => {
                                                        const key = `${rowIndex}:${column}`;

                                                        if (element) {
                                                            inputs.current.set(
                                                                key,
                                                                element,
                                                            );
                                                        } else {
                                                            inputs.current.delete(
                                                                key,
                                                            );
                                                        }
                                                    }}
                                                    type="text"
                                                    inputMode="numeric"
                                                    autoComplete="off"
                                                    aria-label={`${aspect.name} ${row.name}`}
                                                    aria-invalid={
                                                        outOfRange ||
                                                        Boolean(error)
                                                    }
                                                    disabled={
                                                        !canManage || !available
                                                    }
                                                    value={text}
                                                    onFocus={(event) =>
                                                        event.target.select()
                                                    }
                                                    onChange={(event) =>
                                                        setCell(
                                                            row,
                                                            column,
                                                            event.target.value,
                                                        )
                                                    }
                                                    onKeyDown={(event) =>
                                                        onKeyDown(
                                                            event,
                                                            rowIndex,
                                                            column,
                                                        )
                                                    }
                                                    className={cn(
                                                        'h-10 w-20 text-center tabular-nums',
                                                        text !== '' &&
                                                            Number(text) <
                                                                kkm &&
                                                            'font-semibold text-destructive-foreground',
                                                    )}
                                                />
                                            </TableCell>
                                        );
                                    })}
                                    <TableCell>
                                        {result === 'passed' && (
                                            <Badge
                                                variant="success"
                                                className="gap-1"
                                            >
                                                <Check />
                                                Tuntas
                                            </Badge>
                                        )}
                                        {result === 'failed' && (
                                            <Badge
                                                variant="danger"
                                                className="gap-1"
                                            >
                                                <X />
                                                Belum tuntas
                                            </Badge>
                                        )}
                                        {result === 'incomplete' && (
                                            <Badge variant="muted">
                                                Belum lengkap
                                            </Badge>
                                        )}
                                    </TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            </div>

            {canManage && (
                <div className="sticky bottom-20 z-30 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-line bg-card px-4 py-3 shadow-float md:bottom-4">
                    <div className="grid gap-1.5">
                        <p className="text-sm font-semibold tabular-nums">
                            {dirtyRows.length > 0
                                ? `${dirtyRows.length} baris berubah, belum disimpan`
                                : 'Belum ada perubahan'}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Tab pindah ke kanan, Enter turun satu baris, Ctrl+S
                            atau Cmd+S simpan semua.
                        </p>
                        {needsAnnouncedConfirmation && (
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="confirm-announced"
                                    checked={confirmAnnounced}
                                    onCheckedChange={(checked) =>
                                        setConfirmAnnounced(checked === true)
                                    }
                                />
                                <Label
                                    htmlFor="confirm-announced"
                                    className="text-sm font-medium"
                                >
                                    Saya sadar nilai ini sudah diumumkan
                                </Label>
                            </div>
                        )}
                    </div>
                    <Button
                        onClick={save}
                        disabled={processing || dirtyRows.length === 0}
                    >
                        {processing ? <Spinner /> : <Save />}
                        Simpan semua
                    </Button>
                </div>
            )}
        </div>
    );
}
