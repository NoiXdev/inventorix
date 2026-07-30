import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

import Dashboard from '../dashboard';

const base = {
    stats: { assets: 42 },
    warranty: { expired: 3, soon_30: 5, soon_90: 9 },
    latestDocuments: [{ id: 'd1', title: 'Invoice', category_label: 'Rechnung', attached_to: 'Asset', uploaded_by: 'Ada', created_at: '01.01.2025 10:00', url: 'http://x/open/d1' }],
    openIncidents: [{ id: 1, title: 'Broken screen', model: 'X1', serial: 'SN1', open_date: '02.01.2025', days_open: 4, asset_url: '/app/assets/a1' }],
    warrantyExpiring: [{ id: 'a1', owner: 'Bob', model: 'X1', serial: 'SN1', guarantee_end: '10.02.2025', days_left: 20, asset_url: '/app/assets/a1' }],
};

describe('Dashboard', () => {
    it('renders the assets stat and warranty buckets', () => {
        render(<Dashboard {...base} />);
        expect(screen.getByText('42')).toBeInTheDocument();
        expect(screen.getByText('3')).toBeInTheDocument();
        expect(screen.getByText('5')).toBeInTheDocument();
        expect(screen.getByText('9')).toBeInTheDocument();
    });

    it('renders a row in each mini-table', () => {
        render(<Dashboard {...base} />);
        expect(screen.getByText('Invoice')).toBeInTheDocument();
        expect(screen.getByText('Broken screen')).toBeInTheDocument();
        expect(screen.getByText('Bob')).toBeInTheDocument();

        const docLink = screen.getByRole('link', { name: 'Invoice' });
        expect(docLink.getAttribute('href')).toBe('http://x/open/d1');
        expect(docLink.getAttribute('target')).toBe('_blank');
        expect(docLink.getAttribute('rel')).toBe('noreferrer');

        const incidentLink = screen.getByRole('link', { name: 'Broken screen' });
        expect(incidentLink.getAttribute('href')).toBe('/app/assets/a1');

        const warrantyLink = screen.getByRole('link', { name: 'Bob' });
        expect(warrantyLink.getAttribute('href')).toBe('/app/assets/a1');
    });

    it('shows empty states when lists are empty', () => {
        render(<Dashboard {...base} latestDocuments={[]} openIncidents={[]} warrantyExpiring={[]} />);
        expect(screen.getAllByText(/nothing|none|no /i).length).toBeGreaterThan(0);
    });
});
