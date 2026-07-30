import { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    type ColumnDef, type RowSelectionState, flexRender, getCoreRowModel, useReactTable,
} from '@tanstack/react-table';
import { ArrowDown, ArrowUp } from 'lucide-react';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { nextSort, visitTable } from './use-table-query';
import type { PaginationMeta, FilterConfig } from './types';

interface Props<T> {
    columns: ColumnDef<T>[];
    rows: T[];
    pagination: PaginationMeta;
    baseUrl: string;
    searchable?: boolean;
    sortable?: string[];
    filters?: FilterConfig[];
    enableSelection?: boolean;
    getRowId?: (row: T) => string;
    renderBulkActions?: (selected: T[], clear: () => void) => React.ReactNode;
    /** When set, clicking anywhere on a row (except a button/link/checkbox) navigates here. */
    rowHref?: (row: T) => string;
}

// Don't hijack clicks that land on an interactive control inside the row.
const isInteractive = (target: EventTarget | null): boolean =>
    target instanceof Element && !!target.closest('a, button, input, select, textarea, [role="checkbox"]');

export function DataTable<T>({
    columns, rows, pagination, baseUrl, searchable = true, sortable = [], filters = [],
    enableSelection = false, getRowId, renderBulkActions, rowHref,
}: Props<T>) {
    const params = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : new URLSearchParams();
    const [search, setSearch] = useState(params.get('search') ?? '');
    const currentSort = params.get('sort');
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
    const selectionColumn: ColumnDef<T> = {
        id: '__select__',
        header: ({ table: t }) => (
            <Checkbox
                checked={t.getIsAllPageRowsSelected() || (t.getIsSomePageRowsSelected() && 'indeterminate')}
                onCheckedChange={(v) => t.toggleAllPageRowsSelected(!!v)}
                aria-label="Select all"
            />
        ),
        cell: ({ row }) => (
            <Checkbox checked={row.getIsSelected()} onCheckedChange={(v) => row.toggleSelected(!!v)} aria-label="Select row" />
        ),
    };
    const effectiveColumns = enableSelection ? [selectionColumn, ...columns] : columns;
    const table = useReactTable({
        data: rows,
        columns: effectiveColumns,
        getCoreRowModel: getCoreRowModel(),
        ...(enableSelection ? {
            enableRowSelection: true,
            state: { rowSelection },
            onRowSelectionChange: setRowSelection,
            getRowId: getRowId ? (row: T) => getRowId(row) : undefined,
        } : {}),
    });
    const selectedRows = enableSelection ? table.getSelectedRowModel().rows.map((r) => r.original) : [];
    const clearSelection = () => setRowSelection({});

    return (
        <div className="space-y-4">
            {enableSelection && renderBulkActions && selectedRows.length > 0 && (
                <div className="flex items-center gap-3 rounded-md border bg-muted/50 px-3 py-2 text-sm">
                    <span>{selectedRows.length} ausgewählt</span>
                    {renderBulkActions(selectedRows, clearSelection)}
                </div>
            )}
            {(searchable || filters.length > 0) && (
                <div className="flex flex-wrap items-center gap-2">
                    {searchable && (
                        <form
                            onSubmit={(e) => { e.preventDefault(); visitTable(baseUrl, { search }); }}
                            className="flex gap-2"
                        >
                            <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search…" className="w-64" />
                            <Button type="submit" variant="secondary">Search</Button>
                        </form>
                    )}
                    {filters.map((f) => {
                        const active = params.get(`filter[${f.key}]`) ?? '__all__';
                        return (
                            <Select
                                key={f.key}
                                value={active}
                                onValueChange={(v) => visitTable(baseUrl, { [`filter[${f.key}]`]: v === '__all__' ? '' : v })}
                            >
                                <SelectTrigger className="w-56"><SelectValue placeholder={f.label} /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__all__">All {f.label}</SelectItem>
                                    {f.options.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        );
                    })}
                </div>
            )}
            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((hg) => (
                            <TableRow key={hg.id}>
                                {hg.headers.map((h) => {
                                    const columnId = h.column.id;
                                    const isSortable = sortable.includes(columnId);
                                    const isActive = currentSort === columnId || currentSort === `-${columnId}`;
                                    const isDesc = currentSort === `-${columnId}`;

                                    return (
                                        <TableHead key={h.id}>
                                            {h.isPlaceholder ? null : isSortable ? (
                                                <button
                                                    type="button"
                                                    className="flex items-center gap-1 font-medium"
                                                    onClick={() => visitTable(baseUrl, { sort: nextSort(currentSort, columnId) })}
                                                >
                                                    {flexRender(h.column.columnDef.header, h.getContext())}
                                                    {isActive && (isDesc ? <ArrowDown className="h-3 w-3" /> : <ArrowUp className="h-3 w-3" />)}
                                                </button>
                                            ) : (
                                                flexRender(h.column.columnDef.header, h.getContext())
                                            )}
                                        </TableHead>
                                    );
                                })}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows.length ? (
                            table.getRowModel().rows.map((row) => {
                                const href = rowHref?.(row.original);
                                return (
                                    <TableRow
                                        key={row.id}
                                        className={href ? 'cursor-pointer' : undefined}
                                        onClick={href ? (e) => {
                                            if (isInteractive(e.target)) return;
                                            if (e.metaKey || e.ctrlKey) { window.open(href, '_blank', 'noopener'); return; }
                                            router.visit(href);
                                        } : undefined}
                                        onAuxClick={href ? (e) => {
                                            if (e.button !== 1 || isInteractive(e.target)) return;
                                            window.open(href, '_blank', 'noopener');
                                        } : undefined}
                                    >
                                        {row.getVisibleCells().map((cell) => (
                                            <TableCell key={cell.id}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</TableCell>
                                        ))}
                                    </TableRow>
                                );
                            })
                        ) : (
                            <TableRow><TableCell colSpan={effectiveColumns.length} className="h-24 text-center text-muted-foreground">No results.</TableCell></TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
            <div className="flex items-center justify-between">
                <div className="text-sm text-muted-foreground">
                    {pagination.total} result{pagination.total === 1 ? '' : 's'}
                </div>
                <div className="flex gap-2">
                    <Button variant="outline" size="sm" disabled={pagination.current_page <= 1}
                        onClick={() => visitTable(baseUrl, { page: String(pagination.current_page - 1) })}>Previous</Button>
                    <Button variant="outline" size="sm" disabled={pagination.current_page >= pagination.last_page}
                        onClick={() => visitTable(baseUrl, { page: String(pagination.current_page + 1) })}>Next</Button>
                </div>
            </div>
        </div>
    );
}
