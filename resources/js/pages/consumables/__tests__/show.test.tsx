import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const page = vi.hoisted(() => ({ url: '/app/consumables/c1', props: {} as Record<string, unknown> }));
vi.mock('@inertiajs/react', () => ({
    usePage: () => page,
    useForm: (initial: Record<string, unknown>) => ({ data: initial, setData: vi.fn(), post: vi.fn(), reset: vi.fn(), processing: false, errors: {} }),
    Link: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => <a href={href} {...rest}>{children}</a>,
    router: { reload: vi.fn(), post: vi.fn() },
}));
vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

import ShowConsumable from '../show';

const setCan = (can: Record<string, boolean>) => { page.props = { auth: { user: null, can } }; };
const consumable = {
    id: 'c1', name: 'Toner', model_number: null, item_no: 'CF259A', qty: 10, min_amt: 2, purchase_cost: null, purchase_date: null,
    order_number: null, requestable: false, archived: false, notes: null, image_url: null, category_name: null, manufacturer_name: null,
    location_name: null, supplier_name: null, handed_out: 7, remaining: 3, low_stock: false,
};
const assignments = [
    { id: 'a1', user_name: 'Ada', user_url: '/app/users/u1', qty: 5, checked_out_at: '2026-11-01', note: null },
    { id: 'a2', user_name: 'Gone User', user_url: null, qty: 2, checked_out_at: '2026-10-01', note: null },
];

describe('Consumable page', () => {
    beforeEach(() => setCan({}));

    it('shows what is left and who got what', () => {
        render(<ShowConsumable consumable={consumable} assignments={assignments} history={[]} />);
        expect(screen.getByText('3 of 10 left')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Ada' })).toHaveAttribute('href', '/app/users/u1');
        expect(screen.getByText('Gone User (deleted)')).toBeInTheDocument();
    });

    it('offers check-out only with the permission and while some are left', () => {
        const { unmount } = render(<ShowConsumable consumable={consumable} assignments={[]} history={[]} />);
        expect(screen.queryByRole('button', { name: /Check out/ })).not.toBeInTheDocument();
        unmount();
        setCan({ 'consumables.checkout': true });
        const second = render(<ShowConsumable consumable={consumable} assignments={[]} history={[]} />);
        expect(screen.getByRole('button', { name: /Check out/ })).toBeEnabled();
        second.unmount();
        render(<ShowConsumable consumable={{ ...consumable, remaining: 0 }} assignments={[]} history={[]} />);
        expect(screen.getByRole('button', { name: /Check out/ })).toBeDisabled();
    });
});
