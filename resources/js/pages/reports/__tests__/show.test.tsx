// resources/js/pages/reports/__tests__/show.test.tsx
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const get = vi.fn();
vi.mock('@inertiajs/react', () => ({ router: { get: (...a: unknown[]) => get(...a) } }));
vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

import ReportShow from '../show';

const base = {
    meta: { key: 'guarantee_status', label: 'Garantie', description: 'd' },
    filterConfig: [
        { key: 'status', type: 'multiselect' as const, label: 'Status', options: [{ value: 'expired', label: 'Abgelaufen' }] },
        { key: 'min', type: 'number' as const, label: 'Min', default: 3 },
    ],
    filters: {},
    columns: [{ key: 'owner', label: 'Owner' }, { key: 'buy_price', label: 'Preis' }],
    rows: [['Alice', 100]],
    filterSummary: '',
    pagination: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
};

describe('ReportShow', () => {
    it('renders columns and a data row', () => {
        render(<ReportShow {...base} />);
        expect(screen.getByText('Owner')).toBeInTheDocument();
        expect(screen.getByText('Alice')).toBeInTheDocument();
    });

    it('Apply pushes the selected filters to the URL', () => {
        get.mockClear();
        render(<ReportShow {...base} />);
        fireEvent.click(screen.getByText('Abgelaufen'));       // select the multiselect option
        fireEvent.click(screen.getByText('Apply'));
        expect(get).toHaveBeenCalledWith('/app/reports/guarantee_status',
            { filters: { status: ['expired'], min: '3' } },
            expect.objectContaining({ preserveScroll: true }));
    });

    it('renders a totals row when totals present', () => {
        render(<ReportShow {...base} totals={{ buy_price: 100 }} />);
        expect(screen.getByText('Σ')).toBeInTheDocument();
    });

    it('shows an empty state with no rows', () => {
        render(<ReportShow {...base} rows={[]} />);
        expect(screen.getByText(/Keine Daten/)).toBeInTheDocument();
    });
});
