import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { AssetIncidents } from '../asset-incidents';

const open = { id: 1, title: 'Screen cracked', notes: 'dropped', open_date: '2025-01-10', closed_date: null, status: 'open' as const, created_at: '2025-01-10 09:00:00' };
const closed = { id: 2, title: 'Battery swollen', notes: null, open_date: '2024-11-01', closed_date: '2024-12-01', status: 'closed' as const, created_at: '2024-11-01 09:00:00' };

describe('AssetIncidents', () => {
    it('renders open and closed status badges', () => {
        render(<AssetIncidents assetId="a1" incidents={[open, closed]} />);
        expect(screen.getByText('Screen cracked')).toBeInTheDocument();
        expect(screen.getByText(/open/i)).toBeInTheDocument();
        expect(screen.getByText(/closed/i)).toBeInTheDocument();
    });

    it('opens the dialog when New incident is clicked', () => {
        render(<AssetIncidents assetId="a1" incidents={[]} />);
        fireEvent.click(screen.getByRole('button', { name: /new incident/i }));
        expect(screen.getByRole('dialog')).toBeInTheDocument();
        expect(screen.getByLabelText(/title/i)).toBeInTheDocument();
    });

    it('prefills the dialog when editing a row', () => {
        render(<AssetIncidents assetId="a1" incidents={[open]} />);
        fireEvent.click(screen.getByRole('button', { name: /edit screen cracked/i }));
        expect(screen.getByLabelText(/title/i)).toHaveValue('Screen cracked');
    });

    it('shows an empty state when there are no incidents', () => {
        render(<AssetIncidents assetId="a1" incidents={[]} />);
        expect(screen.getByText(/no incidents/i)).toBeInTheDocument();
    });
});
