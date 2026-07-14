import { useState } from 'react';
import {
    type ColumnDef, flexRender, getCoreRowModel, useReactTable,
} from '@tanstack/react-table';
import { ArrowDown, ArrowUp } from 'lucide-react';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { nextSort, visitTable } from './use-table-query';
import type { PaginationMeta } from './types';

interface Props<T> {
    columns: ColumnDef<T>[];
    rows: T[];
    pagination: PaginationMeta;
    baseUrl: string;
    searchable?: boolean;
    sortable?: string[];
}

export function DataTable<T>({ columns, rows, pagination, baseUrl, searchable = true, sortable = [] }: Props<T>) {
    const params = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : new URLSearchParams();
    const [search, setSearch] = useState(params.get('search') ?? '');
    const currentSort = params.get('sort');
    const table = useReactTable({ data: rows, columns, getCoreRowModel: getCoreRowModel() });

    return (
        <div className="space-y-4">
            {searchable && (
                <form
                    onSubmit={(e) => { e.preventDefault(); visitTable(baseUrl, { search }); }}
                    className="flex gap-2"
                >
                    <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search…" className="w-64" />
                    <Button type="submit" variant="secondary">Search</Button>
                </form>
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
                            table.getRowModel().rows.map((row) => (
                                <TableRow key={row.id}>
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow><TableCell colSpan={columns.length} className="h-24 text-center text-muted-foreground">No results.</TableCell></TableRow>
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
