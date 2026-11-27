import { render, screen, fireEvent } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const page = vi.hoisted(() => ({ url: '/app/accessories/x1', props: {} as Record<string, unknown> }));
const post = vi.fn();
vi.mock('@inertiajs/react', () => ({
    usePage: () => page,
    useForm: (initial: Record<string, unknown>) => ({ data: initial, setData: vi.fn(), post: vi.fn(), reset: vi.fn(), processing: false, errors: {} }),
    Link: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => <a href={href} {...rest}>{children}</a>,
    router: { reload: vi.fn(), post: (...a: unknown[]) => post(...a) },
}));
vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

import ShowAccessory from '../show';

const setCan = (can: Record<string, boolean>) => { page.props = { auth: { user: null, can } }; };
const accessory = {
    id: 'x1', name: 'Headset', model_number: null, qty: 4, remaining: 1, min_amt: 2, default_purchase_cost: null,
    requestable: false, archived: false, notes: null, image_url: null, category_name: null, manufacturer_name: null,
    location_name: null, supplier_name: null, low_stock: true,
};
const checkouts = [
    { id: 'c1', holder: { type: 'user' as const, name: 'Ada', url: '/app/users/u1' }, checked_out_at: '2026-11-01', note: null },
    { id: 'c2', holder: { type: 'location' as const, name: 'Lager', url: null }, checked_out_at: '2026-11-02', note: 'spare' },
];

describe('Accessory page', () => {
    beforeEach(() => { setCan({}); post.mockClear(); });

    it('shows what is left and who has units', () => {
        render(<ShowAccessory accessory={accessory} checkouts={checkouts} history={[]} />);
        expect(screen.getByText('1 of 4 available')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'User: Ada' })).toHaveAttribute('href', '/app/users/u1');
        expect(screen.getByText('Location: Lager')).toBeInTheDocument();
    });

    it('offers check-out and check-in only with their permissions', () => {
        const { unmount } = render(<ShowAccessory accessory={accessory} checkouts={checkouts} history={[]} />);
        expect(screen.queryByRole('button', { name: /Check out/ })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Check in/ })).not.toBeInTheDocument();
        unmount();

        setCan({ 'accessories.checkout': true, 'accessories.checkin': true });
        render(<ShowAccessory accessory={accessory} checkouts={checkouts} history={[]} />);
        expect(screen.getByRole('button', { name: /Check out/ })).toBeEnabled();
        vi.stubGlobal('confirm', vi.fn(() => true));
        fireEvent.click(screen.getAllByRole('button', { name: /Check in/ })[0]);
        expect(post).toHaveBeenCalledWith('/app/accessory-checkouts/c1/checkin', {}, { preserveScroll: true });
        vi.unstubAllGlobals();
    });

    it('cannot check out when none is left or it is archived', () => {
        setCan({ 'accessories.checkout': true });
        const { unmount } = render(<ShowAccessory accessory={{ ...accessory, remaining: 0 }} checkouts={[]} history={[]} />);
        expect(screen.getByRole('button', { name: /Check out/ })).toBeDisabled();
        unmount();
        render(<ShowAccessory accessory={{ ...accessory, archived: true }} checkouts={[]} history={[]} />);
        expect(screen.getByRole('button', { name: /Check out/ })).toBeDisabled();
    });
});
