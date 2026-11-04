import { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    type Column, type ColumnDef, type RowSelectionState, type Updater, flexRender, getCoreRowModel, useReactTable,
} from '@tanstack/react-table';
import { ArrowDown, ArrowUp, Columns3, GripVertical } from 'lucide-react';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
    DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { nextSort, visitTable } from './use-table-query';
import { moveColumn, useColumnLayout } from './use-column-layout';
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
    /** Column ids that stick to the right edge while scrolling horizontally; they can't be moved, hidden or resized. */
    pinnedRight?: string[];
    /** localStorage key for the user's column layout; defaults to `baseUrl`. */
    layoutKey?: string;
}

const SELECT_ID = '__select__';

// Don't hijack clicks that land on an interactive control inside the row.
const isInteractive = (target: EventTarget | null): boolean =>
    target instanceof Element && !!target.closest('a, button, input, select, textarea, [role="checkbox"]');

const resolve = <S,>(updater: Updater<S>, old: S): S =>
    typeof updater === 'function' ? (updater as (old: S) => S)(old) : updater;

const columnLabel = <T,>(column: Column<T>): string =>
    typeof column.columnDef.header === 'string' && column.columnDef.header !== '' ? column.columnDef.header : column.id;

// Pinned cells need an opaque background so scrolled content doesn't show through,
// mirroring the row's translucent hover tint.
const pinnedClass = (pinned: false | 'left' | 'right') => pinned && cn(
    'sticky z-10 bg-background group-hover:bg-[color-mix(in_oklab,var(--muted)_50%,var(--background))]',
    pinned === 'right' ? 'border-l' : 'border-r',
);

const pinnedStyle = <T,>(column: Column<T>): React.CSSProperties => {
    const pinned = column.getIsPinned();
    if (pinned === 'left') return { left: column.getStart('left') };
    if (pinned === 'right') return { right: column.getAfter('right') };
    return {};
};

export function DataTable<T>({
    columns, rows, pagination, baseUrl, searchable = true, sortable = [], filters = [],
    enableSelection = false, getRowId, renderBulkActions, rowHref, pinnedRight = ['actions'], layoutKey,
}: Props<T>) {
    const params = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : new URLSearchParams();
    const [search, setSearch] = useState(params.get('search') ?? '');
    const currentSort = params.get('sort');
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
    const [layout, setLayout, resetLayout] = useColumnLayout(layoutKey ?? baseUrl);
    const [dragging, setDragging] = useState<string | null>(null);
    const [dropTarget, setDropTarget] = useState<string | null>(null);

    const selectionColumn: ColumnDef<T> = {
        id: SELECT_ID,
        size: 40,
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
    const left = enableSelection ? [SELECT_ID] : [];
    const right = columns.map((c) => c.id).filter((id): id is string => !!id && pinnedRight.includes(id));
    const pinned = new Set([...left, ...right]);
    const effectiveColumns = (enableSelection ? [selectionColumn, ...columns] : columns).map((c) => (
        c.id && pinned.has(c.id) ? { ...c, enableHiding: false, enableResizing: false } : c
    ));

    const table = useReactTable({
        data: rows,
        columns: effectiveColumns,
        getCoreRowModel: getCoreRowModel(),
        columnResizeMode: 'onChange',
        state: {
            columnOrder: layout.order,
            columnSizing: layout.sizing,
            columnVisibility: layout.visibility,
            columnPinning: { left, right },
            ...(enableSelection ? { rowSelection } : {}),
        },
        onColumnOrderChange: (u) => setLayout((l) => ({ ...l, order: resolve(u, l.order) })),
        onColumnSizingChange: (u) => setLayout((l) => ({ ...l, sizing: resolve(u, l.sizing) })),
        onColumnVisibilityChange: (u) => setLayout((l) => ({ ...l, visibility: resolve(u, l.visibility) })),
        ...(enableSelection ? {
            enableRowSelection: true,
            onRowSelectionChange: setRowSelection,
            getRowId: getRowId ? (row: T) => getRowId(row) : undefined,
        } : {}),
    });
    const selectedRows = enableSelection ? table.getSelectedRowModel().rows.map((r) => r.original) : [];
    const clearSelection = () => setRowSelection({});

    // Stored order may be stale (columns added/removed since); drop unknown ids, append new ones.
    const currentOrder = (): string[] => {
        const ids = table.getAllLeafColumns().map((c) => c.id);
        return [...layout.order.filter((id) => ids.includes(id)), ...ids.filter((id) => !layout.order.includes(id))];
    };
    const endDrag = () => { setDragging(null); setDropTarget(null); };
    const hideableColumns = table.getAllLeafColumns().filter((c) => c.getCanHide());

    return (
        <div className="space-y-4">
            {enableSelection && renderBulkActions && selectedRows.length > 0 && (
                <div className="flex items-center gap-3 rounded-md border bg-muted/50 px-3 py-2 text-sm">
                    <span>{selectedRows.length} ausgewählt</span>
                    {renderBulkActions(selectedRows, clearSelection)}
                </div>
            )}
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
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="ml-auto"><Columns3 className="h-4 w-4" />Columns</Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        {hideableColumns.map((c) => (
                            <DropdownMenuCheckboxItem
                                key={c.id}
                                checked={c.getIsVisible()}
                                onCheckedChange={(v) => c.toggleVisibility(!!v)}
                                onSelect={(e) => e.preventDefault()}
                            >
                                {columnLabel(c)}
                            </DropdownMenuCheckboxItem>
                        ))}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onSelect={resetLayout}>Reset layout</DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
            <div className="rounded-md border">
                <Table className="table-fixed" style={{ width: table.getTotalSize(), minWidth: '100%' }}>
                    <TableHeader>
                        {table.getHeaderGroups().map((hg) => (
                            <TableRow key={hg.id} className="group">
                                {hg.headers.map((h) => {
                                    const columnId = h.column.id;
                                    const isPinned = h.column.getIsPinned();
                                    const isSortable = sortable.includes(columnId);
                                    const isActive = currentSort === columnId || currentSort === `-${columnId}`;
                                    const isDesc = currentSort === `-${columnId}`;
                                    const canDrop = !isPinned && dragging !== null && dragging !== columnId;

                                    return (
                                        <TableHead
                                            key={h.id}
                                            style={{ width: h.getSize(), ...pinnedStyle(h.column) }}
                                            className={cn(
                                                'overflow-hidden text-ellipsis',
                                                isPinned ? pinnedClass(isPinned) : 'relative',
                                                dragging === columnId && 'opacity-50',
                                                canDrop && dropTarget === columnId && 'bg-muted',
                                            )}
                                            onDragOver={canDrop ? (e) => { e.preventDefault(); setDropTarget(columnId); } : undefined}
                                            onDragLeave={canDrop ? () => setDropTarget(null) : undefined}
                                            onDrop={canDrop ? (e) => {
                                                e.preventDefault();
                                                table.setColumnOrder(moveColumn(currentOrder(), dragging, columnId));
                                                endDrag();
                                            } : undefined}
                                        >
                                            {h.isPlaceholder ? null : (
                                                <div className="flex items-center gap-1">
                                                    {!isPinned && (
                                                        <span
                                                            draggable
                                                            aria-label={`Move column ${columnLabel(h.column)}`}
                                                            className="-ml-1 shrink-0 cursor-grab text-muted-foreground/50 hover:text-muted-foreground"
                                                            onDragStart={(e) => {
                                                                e.dataTransfer.effectAllowed = 'move';
                                                                e.dataTransfer.setData('text/plain', columnId);
                                                                setDragging(columnId);
                                                            }}
                                                            onDragEnd={endDrag}
                                                        >
                                                            <GripVertical className="h-3.5 w-3.5" />
                                                        </span>
                                                    )}
                                                    {isSortable ? (
                                                        <button
                                                            type="button"
                                                            className="flex min-w-0 items-center gap-1 font-medium"
                                                            onClick={() => visitTable(baseUrl, { sort: nextSort(currentSort, columnId) })}
                                                        >
                                                            <span className="truncate">{flexRender(h.column.columnDef.header, h.getContext())}</span>
                                                            {isActive && (isDesc ? <ArrowDown className="h-3 w-3 shrink-0" /> : <ArrowUp className="h-3 w-3 shrink-0" />)}
                                                        </button>
                                                    ) : (
                                                        flexRender(h.column.columnDef.header, h.getContext())
                                                    )}
                                                </div>
                                            )}
                                            {h.column.getCanResize() && (
                                                <div
                                                    role="separator"
                                                    aria-orientation="vertical"
                                                    aria-label={`Resize column ${columnLabel(h.column)}`}
                                                    onMouseDown={h.getResizeHandler()}
                                                    onTouchStart={h.getResizeHandler()}
                                                    onDoubleClick={() => h.column.resetSize()}
                                                    className={cn(
                                                        'absolute top-0 right-0 h-full w-1.5 cursor-col-resize touch-none select-none hover:bg-border',
                                                        h.column.getIsResizing() && 'bg-primary',
                                                    )}
                                                />
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
                                        className={cn('group', href && 'cursor-pointer')}
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
                                        {row.getVisibleCells().map((cell) => {
                                            const isPinned = cell.column.getIsPinned();
                                            return (
                                                <TableCell
                                                    key={cell.id}
                                                    style={pinnedStyle(cell.column)}
                                                    className={cn('overflow-hidden text-ellipsis', pinnedClass(isPinned))}
                                                >
                                                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                </TableCell>
                                            );
                                        })}
                                    </TableRow>
                                );
                            })
                        ) : (
                            <TableRow><TableCell colSpan={table.getVisibleLeafColumns().length} className="h-24 text-center text-muted-foreground">No results.</TableCell></TableRow>
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
