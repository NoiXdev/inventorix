import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => <a href={href} {...rest}>{children}</a>,
}));
vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

import MyItems from '../index';

const none = { assets: [], licenses: [], accessories: [], consumables: [], pendingAcceptances: [] };

describe('My items page', () => {
    it('shows the empty state', () => {
        render(<MyItems {...none} />);
        expect(screen.getByText('You have no items checked out.')).toBeInTheDocument();
    });

    it('links a pending acceptance to its review page', () => {
        render(<MyItems {...none} pendingAcceptances={[{ id: 'abc', item_name: 'Headset', sent_at: '2026-11-01' }]} />);
        expect(screen.getByRole('link', { name: 'Review' })).toHaveAttribute('href', '/app/my-items/acceptances/abc');
    });

    it('renders an asset without a link when href is null', () => {
        render(<MyItems {...none} assets={[{ id: '1', name: 'ThinkPad', asset_tag: 'A-1', model: null, serial: null, checked_out_at: null, acceptance: null, href: null }]} />);
        expect(screen.getByText('ThinkPad')).toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'ThinkPad' })).not.toBeInTheDocument();
    });
});
