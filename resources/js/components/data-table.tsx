import {
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import type {
    ColumnDef,
    OnChangeFn,
    SortingState,
} from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import type { KeyboardEvent, MouseEvent } from 'react';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

export type RowSelection = {
    selected: ReadonlySet<string>;
    onChange: (selected: Set<string>) => void;
};

const INTERACTIVE =
    'a, button, input, label, [role="checkbox"], [data-no-row-open]';

/**
 * A table whose rows, sorting and paging come from the server. Columns that can be sorted show a button in
 * their header; the page decides what a sort change does (usually a new request).
 *
 * Two optional behaviours speed up admin work: `selection` adds a checkbox column for bulk actions, and
 * `onRowOpen` makes a row open a quick view when clicked (clicks on links, buttons and checkboxes inside the
 * row keep their own meaning).
 */
export function DataTable<T>({
    columns,
    data,
    getRowId,
    sorting,
    onSortingChange,
    selection,
    onRowOpen,
    activeRowId,
}: {
    columns: ColumnDef<T>[];
    data: T[];
    getRowId: (row: T) => string;
    sorting: SortingState;
    onSortingChange: OnChangeFn<SortingState>;
    selection?: RowSelection;
    onRowOpen?: (row: T) => void;
    /** Row shown in the quick view right now, highlighted in the table. */
    activeRowId?: string | null;
}) {
    'use no memo';

    // eslint-disable-next-line react-hooks/incompatible-library
    const table = useReactTable({
        data,
        columns,
        getRowId,
        state: { sorting },
        onSortingChange,
        manualSorting: true,
        enableSortingRemoval: false,
        getCoreRowModel: getCoreRowModel(),
    });

    const rowIds = table.getRowModel().rows.map((row) => row.id);
    const selectedOnPage = selection
        ? rowIds.filter((id) => selection.selected.has(id))
        : [];
    const allSelected =
        rowIds.length > 0 && selectedOnPage.length === rowIds.length;
    const someSelected = selectedOnPage.length > 0 && !allSelected;

    function toggleAll(checked: boolean) {
        if (!selection) {
            return;
        }

        const next = new Set(selection.selected);

        rowIds.forEach((id) => (checked ? next.add(id) : next.delete(id)));
        selection.onChange(next);
    }

    function toggleRow(id: string, checked: boolean) {
        if (!selection) {
            return;
        }

        const next = new Set(selection.selected);

        if (checked) {
            next.add(id);
        } else {
            next.delete(id);
        }

        selection.onChange(next);
    }

    function openRow(event: MouseEvent | KeyboardEvent, row: T) {
        if (
            !onRowOpen ||
            (event.target as HTMLElement).closest(INTERACTIVE) !== null
        ) {
            return;
        }

        onRowOpen(row);
    }

    return (
        <Card className="gap-0 overflow-hidden p-0">
            <Table>
                <TableHeader>
                    {table.getHeaderGroups().map((group) => (
                        <TableRow
                            key={group.id}
                            className="hover:bg-transparent"
                        >
                            {selection && (
                                <TableHead className="w-12 pr-0">
                                    <Checkbox
                                        aria-label="Pilih semua di halaman ini"
                                        checked={
                                            allSelected
                                                ? true
                                                : someSelected
                                                  ? 'indeterminate'
                                                  : false
                                        }
                                        onCheckedChange={(checked) =>
                                            toggleAll(checked === true)
                                        }
                                    />
                                </TableHead>
                            )}
                            {group.headers.map((header) => {
                                const sorted = header.column.getIsSorted();
                                const canSort = header.column.getCanSort();

                                return (
                                    <TableHead
                                        key={header.id}
                                        aria-sort={
                                            sorted === 'asc'
                                                ? 'ascending'
                                                : sorted === 'desc'
                                                  ? 'descending'
                                                  : undefined
                                        }
                                        className={cn(
                                            header.column.columnDef.meta
                                                ?.className,
                                        )}
                                    >
                                        {header.isPlaceholder ? null : canSort ? (
                                            <button
                                                type="button"
                                                onClick={header.column.getToggleSortingHandler()}
                                                className="-ml-2 inline-flex items-center gap-1.5 rounded-lg px-2 py-1 font-bold uppercase hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring focus-visible:outline-none"
                                            >
                                                {flexRender(
                                                    header.column.columnDef
                                                        .header,
                                                    header.getContext(),
                                                )}
                                                {sorted === 'asc' ? (
                                                    <ArrowUp className="size-3.5" />
                                                ) : sorted === 'desc' ? (
                                                    <ArrowDown className="size-3.5" />
                                                ) : (
                                                    <ChevronsUpDown className="size-3.5 text-muted-foreground" />
                                                )}
                                            </button>
                                        ) : (
                                            flexRender(
                                                header.column.columnDef.header,
                                                header.getContext(),
                                            )
                                        )}
                                    </TableHead>
                                );
                            })}
                        </TableRow>
                    ))}
                </TableHeader>
                <TableBody>
                    {table.getRowModel().rows.map((row) => {
                        const checked =
                            selection?.selected.has(row.id) ?? false;

                        return (
                            <TableRow
                                key={row.id}
                                data-state={
                                    checked || activeRowId === row.id
                                        ? 'selected'
                                        : undefined
                                }
                                onClick={(event) =>
                                    openRow(event, row.original)
                                }
                                className={cn(onRowOpen && 'cursor-pointer')}
                            >
                                {selection && (
                                    <TableCell className="w-12 pr-0">
                                        <Checkbox
                                            aria-label="Pilih baris ini"
                                            checked={checked}
                                            onCheckedChange={(next) =>
                                                toggleRow(row.id, next === true)
                                            }
                                        />
                                    </TableCell>
                                )}
                                {row.getVisibleCells().map((cell) => (
                                    <TableCell
                                        key={cell.id}
                                        className={cn(
                                            cell.column.columnDef.meta
                                                ?.className,
                                        )}
                                    >
                                        {flexRender(
                                            cell.column.columnDef.cell,
                                            cell.getContext(),
                                        )}
                                    </TableCell>
                                ))}
                            </TableRow>
                        );
                    })}
                </TableBody>
            </Table>
        </Card>
    );
}
