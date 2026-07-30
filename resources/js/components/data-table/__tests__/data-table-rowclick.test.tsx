import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { type ColumnDef } from '@tanstack/react-table';

const visit = vi.fn();
vi.mock('@inertiajs/react', () => ({ router: { visit: (...a: unknown[]) => visit(...a) } }));

import { DataTable } from '../data-table';

interface R { id: string; name: string }
const rows: R[] = [{ id: 'a', name: 'Alpha' }];
const pagination = { current_page: 1, last_page: 1, per_page: 25, total: 1 };
const columns: ColumnDef<R>[] = [
    { id: 'name', accessorFn: (r) => r.name, header: 'Name', cell: ({ row }) => row.original.name },
    { id: 'actions', header: '', cell: () => <button type="button">Edit</button> },
];

describe('DataTable rowHref', () => {
    it('navigates when a non-interactive cell is clicked', () => {
        visit.mockClear();
        render(<DataTable columns={columns} rows={rows} pagination={pagination as never} baseUrl="/x" searchable={false} rowHref={(r) => `/x/${r.id}`} />);
        fireEvent.click(screen.getByText('Alpha'));
        expect(visit).toHaveBeenCalledWith('/x/a');
    });

    it('does not navigate when a row action button is clicked', () => {
        visit.mockClear();
        render(<DataTable columns={columns} rows={rows} pagination={pagination as never} baseUrl="/x" searchable={false} rowHref={(r) => `/x/${r.id}`} />);
        fireEvent.click(screen.getByText('Edit'));
        expect(visit).not.toHaveBeenCalled();
    });

    it('does not make rows clickable when rowHref is absent', () => {
        visit.mockClear();
        render(<DataTable columns={columns} rows={rows} pagination={pagination as never} baseUrl="/x" searchable={false} />);
        fireEvent.click(screen.getByText('Alpha'));
        expect(visit).not.toHaveBeenCalled();
    });
});
