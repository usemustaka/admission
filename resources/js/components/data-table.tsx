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
import { Card } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

/**
 * A table whose rows, sorting and paging come from the server. Columns that can be sorted show a button in
 * their header; the page decides what a sort change does (usually a new request).
 */
export function DataTable<T>({
    columns,
    data,
    getRowId,
    sorting,
    onSortingChange,
}: {
    columns: ColumnDef<T>[];
    data: T[];
    getRowId: (row: T) => string;
    sorting: SortingState;
    onSortingChange: OnChangeFn<SortingState>;
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

    return (
        <Card className="gap-0 overflow-hidden p-0">
            <Table>
                <TableHeader>
                    {table.getHeaderGroups().map((group) => (
                        <TableRow key={group.id}>
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
                                                className="-ml-2 inline-flex items-center gap-1.5 rounded-md px-2 py-1 font-medium hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
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
                    {table.getRowModel().rows.map((row) => (
                        <TableRow key={row.id}>
                            {row.getVisibleCells().map((cell) => (
                                <TableCell
                                    key={cell.id}
                                    className={cn(
                                        cell.column.columnDef.meta?.className,
                                    )}
                                >
                                    {flexRender(
                                        cell.column.columnDef.cell,
                                        cell.getContext(),
                                    )}
                                </TableCell>
                            ))}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </Card>
    );
}
