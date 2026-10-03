import { Button } from '@/Components/ui/button';
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/Components/ui/empty';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { router } from '@inertiajs/react';
import { flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ArrowUpDown, ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from 'cn';

/**
 * Server-driven table (shadcn data-table pattern on TanStack Table). Pagination and sorting are
 * done by Laravel: this component only reads the paginator and changes the query string.
 *
 * paginator: a Laravel LengthAwarePaginator serialised by Inertia.
 * sort: current spatie/laravel-query-builder sort string, e.g. "-created_at".
 * Columns may set meta.sortKey to make their header sortable and meta.align = 'right'.
 */
export function DataTable({
    columns,
    paginator,
    sort,
    query = {},
    emptyTitle = 'Nothing here yet',
    emptyDescription,
    toolbar,
}) {
    const table = useReactTable({
        data: paginator.data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        manualPagination: true,
        manualSorting: true,
        pageCount: paginator.last_page,
    });

    const visit = (params) =>
        router.get(
            window.location.pathname,
            { ...query, sort, ...params },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const toggleSort = (key) => {
        const next = sort === key ? `-${key}` : sort === `-${key}` ? undefined : key;
        visit({ sort: next, page: undefined });
    };

    return (
        <div className="flex flex-col gap-3">
            {toolbar}
            <div className="overflow-hidden rounded-lg border bg-card shadow-card">
                <Table>
                    <TableHeader className="bg-surface-3">
                        {table.getHeaderGroups().map((group) => (
                            <TableRow key={group.id} className="hover:bg-transparent">
                                {group.headers.map((header) => {
                                    const meta = header.column.columnDef.meta ?? {};
                                    const sortKey = meta.sortKey;
                                    const direction =
                                        sort === sortKey
                                            ? 'asc'
                                            : sort === `-${sortKey}`
                                              ? 'desc'
                                              : null;
                                    const label = flexRender(
                                        header.column.columnDef.header,
                                        header.getContext(),
                                    );

                                    return (
                                        <TableHead
                                            key={header.id}
                                            className={cn(
                                                'h-10 text-xs font-medium text-muted-foreground',
                                                meta.align === 'right' && 'text-right',
                                            )}
                                            aria-sort={
                                                direction === 'asc'
                                                    ? 'ascending'
                                                    : direction === 'desc'
                                                      ? 'descending'
                                                      : undefined
                                            }
                                        >
                                            {sortKey ? (
                                                <button
                                                    type="button"
                                                    onClick={() => toggleSort(sortKey)}
                                                    className="inline-flex items-center gap-1 hover:text-foreground"
                                                >
                                                    {label}
                                                    {direction === 'asc' ? (
                                                        <ArrowUp className="size-3.5" />
                                                    ) : direction === 'desc' ? (
                                                        <ArrowDown className="size-3.5" />
                                                    ) : (
                                                        <ArrowUpDown className="size-3.5 opacity-50" />
                                                    )}
                                                </button>
                                            ) : (
                                                label
                                            )}
                                        </TableHead>
                                    );
                                })}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow key={row.id} className="h-11">
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell
                                            key={cell.id}
                                            className={cn(
                                                'tabular-nums',
                                                cell.column.columnDef.meta?.align === 'right' &&
                                                    'text-right',
                                            )}
                                        >
                                            {flexRender(
                                                cell.column.columnDef.cell,
                                                cell.getContext(),
                                            )}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow className="hover:bg-transparent">
                                <TableCell colSpan={columns.length}>
                                    <Empty className="py-10">
                                        <EmptyHeader>
                                            <EmptyTitle>{emptyTitle}</EmptyTitle>
                                            {emptyDescription && (
                                                <EmptyDescription>
                                                    {emptyDescription}
                                                </EmptyDescription>
                                            )}
                                        </EmptyHeader>
                                    </Empty>
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
            {paginator.total > 0 && (
                <div className="flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground">
                    <span className="tabular-nums">
                        {paginator.from}–{paginator.to} of {paginator.total}
                    </span>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!paginator.prev_page_url}
                            onClick={() => visit({ page: paginator.current_page - 1 })}
                        >
                            <ChevronLeft /> Previous
                        </Button>
                        <span className="tabular-nums">
                            Page {paginator.current_page} of {paginator.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!paginator.next_page_url}
                            onClick={() => visit({ page: paginator.current_page + 1 })}
                        >
                            Next <ChevronRight />
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
