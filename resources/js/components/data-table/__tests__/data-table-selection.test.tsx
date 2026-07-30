import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { type ColumnDef } from '@tanstack/react-table';
import { DataTable } from '../data-table';

interface R { id: string; name: string }
const columns: ColumnDef<R>[] = [{ id: 'name', accessorFn: (r) => r.name, header: 'Name', cell: ({ row }) => row.original.name }];
const rows: R[] = [{ id: 'a', name: 'Alpha' }, { id: 'b', name: 'Bravo' }];
const pagination = { current_page: 1, last_page: 1, per_page: 25, total: 2 };

describe('DataTable selection', () => {
    it('selecting rows surfaces them to renderBulkActions', () => {
        const onBulk = vi.fn();
        render(<DataTable columns={columns} rows={rows} pagination={pagination as never} baseUrl="/x"
            searchable={false} enableSelection getRowId={(r) => r.id}
            renderBulkActions={(selected) => <button onClick={() => onBulk(selected)}>Bulk {selected.length}</button>} />);
        const checkboxes = screen.getAllByLabelText(/select row/i);
        fireEvent.click(checkboxes[0]);
        fireEvent.click(screen.getByText(/^Bulk/));
        expect(onBulk).toHaveBeenCalledWith([{ id: 'a', name: 'Alpha' }]);
    });
});
