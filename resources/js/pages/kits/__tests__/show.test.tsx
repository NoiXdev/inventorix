import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const page = vi.hoisted(() => ({ url: '/app/kits/k1', props: {} as Record<string, unknown> }));
vi.mock('@inertiajs/react', () => ({
    usePage: () => page,
    Link: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => <a href={href} {...rest}>{children}</a>,
}));
vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

import ShowKit from '../show';

const setCan = (can: Record<string, boolean>) => { page.props = { auth: { user: null, can } }; };
const kit = { id: 'k1', name: 'New hire', notes: null };
const lines = [
    { id: 'l1', type: 'accessory' as const, item_id: 'a1', name: 'Mouse', qty: 2, available: true, url: '/app/accessories/a1' },
    { id: 'l2', type: 'license' as const, item_id: 'b1', name: 'Office', qty: 1, available: false, url: null },
];

describe('Kit page', () => {
    beforeEach(() => setCan({}));

    it('lists lines with their availability', () => {
        render(<ShowKit kit={kit} lines={lines} />);
        expect(screen.getByRole('link', { name: 'Mouse' })).toHaveAttribute('href', '/app/accessories/a1');
        expect(screen.getByText('Available')).toBeInTheDocument();
        expect(screen.getByText('Deleted')).toBeInTheDocument();
    });

    it('offers check-out only with the permission', () => {
        const { unmount } = render(<ShowKit kit={kit} lines={lines} />);
        expect(screen.queryByRole('link', { name: /Check out/ })).not.toBeInTheDocument();
        unmount();
        setCan({ 'kits.checkout': true });
        render(<ShowKit kit={kit} lines={lines} />);
        expect(screen.getByRole('link', { name: /Check out/ })).toHaveAttribute('href', '/app/kits/k1/checkout');
    });
});
