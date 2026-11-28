import { render, screen, fireEvent } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const page = vi.hoisted(() => ({ url: '/app/components/x1', props: {} as Record<string, unknown> }));
const post = vi.hoisted(() => vi.fn());
vi.mock('@inertiajs/react', () => ({
    usePage: () => page,
    useForm: (initial: Record<string, unknown>) => ({ data: initial, setData: vi.fn(), post, reset: vi.fn(), processing: false, errors: {} }),
    Link: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => <a href={href} {...rest}>{children}</a>,
    router: { reload: vi.fn(), post: vi.fn() },
}));
vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

import ShowComponent from '../show';

const setCan = (can: Record<string, boolean>) => { page.props = { auth: { user: null, can } };; };
const component = {
    id: 'x1', name: 'RAM 16GB', model_number: null, serial: null, qty: 5, min_amt: 2, purchase_cost: null, purchase_date: null,
    order_number: null, notes: null, image_url: null, category_name: null, manufacturer_name: null, location_name: null,
    supplier_name: null, checked_out: 4, remaining: 1, low_stock: true,
};
const checkouts = [
    { id: 'c1', qty: 3, asset: { name: 'Server 1', url: '/app/assets/a1' }, checked_out_at: '2026-11-01', note: null },
];

describe('Component page', () => {
    beforeEach(() => { setCan({}); post.mockClear(); });

    it('shows what is left and where units are installed', () => {
        render(<ShowComponent component={component} checkouts={checkouts} history={[]} />);
        expect(screen.getByText('1 of 5 available')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Server 1' })).toHaveAttribute('href', '/app/assets/a1');
        expect(screen.getByText('3')).toBeInTheDocument();
    });

    it('offers check-out and check-in only with their permissions', () => {
        const { unmount } = render(<ShowComponent component={component} checkouts={checkouts} history={[]} />);
        expect(screen.queryByRole('button', { name: /Check out/ })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Check in/ })).not.toBeInTheDocument();
        unmount();

        setCan({ 'components.checkout': true, 'components.checkin': true });
        render(<ShowComponent component={component} checkouts={checkouts} history={[]} />);
        expect(screen.getByRole('button', { name: /Check out/ })).toBeEnabled();
        expect(screen.getByRole('button', { name: /Check in/ })).toBeInTheDocument();
    });

    it('asks for a quantity when checking in', () => {
        setCan({ 'components.checkin': true });
        render(<ShowComponent component={component} checkouts={checkouts} history={[]} />);
        fireEvent.click(screen.getByRole('button', { name: /Check in/ }));
        expect(screen.getByLabelText(/Quantity to check in/)).toHaveValue(3);
        fireEvent.click(screen.getAllByRole('button', { name: 'Check in' }).at(-1)!);
        expect(post).toHaveBeenCalledWith('/app/component-assets/c1/checkin', expect.anything());
    });

    it('cannot check out when none is left', () => {
        setCan({ 'components.checkout': true });
        render(<ShowComponent component={{ ...component, remaining: 0 }} checkouts={[]} history={[]} />);
        expect(screen.getByRole('button', { name: /Check out/ })).toBeDisabled();
    });
});
