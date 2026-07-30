import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { AssetHistory } from '../asset-history';

const entries = [
    { id: 2, event: 'updated', event_label: 'Updated', causer_name: 'Cara Causer', created_at: '2025-02-01 10:00:00',
      changes: [{ field: 'owner_id', label: 'Owner', old: 'Olive Old', new: 'Nate New' }] },
    { id: 1, event: 'created', event_label: 'Created', causer_name: 'System', created_at: '2025-01-01 09:00:00', changes: [] },
];

describe('AssetHistory', () => {
    it('renders entries with event label, causer and changes', () => {
        render(<AssetHistory history={entries} />);
        expect(screen.getByText('Updated')).toBeInTheDocument();
        expect(screen.getByText('Cara Causer')).toBeInTheDocument();
        expect(screen.getByText('Owner')).toBeInTheDocument();
        expect(screen.getByText(/Olive Old/)).toBeInTheDocument();
        expect(screen.getByText(/Nate New/)).toBeInTheDocument();
    });

    it('shows the causer for a system entry', () => {
        render(<AssetHistory history={entries} />);
        expect(screen.getByText('System')).toBeInTheDocument();
    });

    it('shows an empty state when there is no history', () => {
        render(<AssetHistory history={[]} />);
        expect(screen.getByText(/no history/i)).toBeInTheDocument();
    });
});
