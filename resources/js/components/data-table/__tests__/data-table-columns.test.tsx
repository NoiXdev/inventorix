import { render, screen, fireEvent, within } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';
import { type ColumnDef } from '@tanstack/react-table';
import { DataTable } from '../data-table';
import { moveColumn } from '../use-column-layout';

interface R { id: string; name: string; email: string }
const columns: ColumnDef<R>[] = [
    { id: 'name', accessorFn: (r) => r.name, header: 'Name', cell: ({ row }) => row.original.name },
    { id: 'email', accessorFn: (r) => r.email, header: 'Email', cell: ({ row }) => row.original.email },
    { id: 'actions', header: '', cell: () => <button type="button">Edit</button> },
];
const rows: R[] = [{ id: 'a', name: 'Alpha', email: 'a@x.de' }];
const pagination = { current_page: 1, last_page: 1, per_page: 25, total: 1 };

const headers = () => screen.getAllByRole('columnheader').map((th) => th.textContent);
const renderTable = () => render(
    <DataTable columns={columns} rows={rows} pagination={pagination as never} baseUrl="/x" searchable={false} />,
);

describe('moveColumn', () => {
    it('moves left and right', () => {
        expect(moveColumn(['a', 'b', 'c'], 'a', 'c')).toEqual(['b', 'c', 'a']);
        expect(moveColumn(['a', 'b', 'c'], 'c', 'a')).toEqual(['c', 'a', 'b']);
    });
});

describe('DataTable column layout', () => {
    beforeEach(() => localStorage.clear());

    it('reorders columns by dragging a header grip onto another header, keeping actions last', () => {
        renderTable();
        const nameTh = screen.getAllByRole('columnheader')[0];
        const emailTh = screen.getAllByRole('columnheader')[1];
        const dataTransfer = { setData: () => {}, effectAllowed: '' };

        fireEvent.dragStart(within(nameTh).getByLabelText('Move column Name'), { dataTransfer });
        fireEvent.dragOver(emailTh, { dataTransfer });
        fireEvent.drop(emailTh, { dataTransfer });

        expect(headers()).toEqual(['Email', 'Name', '']);
        expect(JSON.parse(localStorage.getItem('data-table:/x')!).order).toEqual(['email', 'name', 'actions']);
    });

    it('restores a persisted layout and hides columns', () => {
        localStorage.setItem('data-table:/x', JSON.stringify({ order: ['email', 'name'], visibility: { name: false } }));
        renderTable();
        expect(headers()).toEqual(['Email', '']);
    });

    it('pins the actions column without a move or resize handle', () => {
        renderTable();
        const actionsTh = screen.getAllByRole('columnheader')[2];
        expect(actionsTh.className).toContain('sticky');
        expect(within(actionsTh).queryByLabelText(/move column/i)).toBeNull();
        expect(within(actionsTh).queryByLabelText(/resize column/i)).toBeNull();
        expect(screen.getByLabelText('Resize column Name')).toBeInTheDocument();
    });
});
