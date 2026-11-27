import { render, screen, fireEvent } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const page = vi.hoisted(() => ({ url: '/app/licenses/l1', props: {} as Record<string, unknown> }));
const post = vi.fn();
vi.mock('@inertiajs/react', () => ({
    usePage: () => page,
    useForm: (initial: Record<string, unknown>) => ({ data: initial, setData: vi.fn(), post: vi.fn(), reset: vi.fn(), processing: false, errors: {} }),
    Link: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => <a href={href} {...rest}>{children}</a>,
    router: { reload: vi.fn(), post: (...a: unknown[]) => post(...a) },
}));
vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

import ShowLicense from '../show';

const setCan = (can: Record<string, boolean>) => { page.props = { auth: { user: null, can } }; };

const license = {
    id: 'l1', name: 'CAD Suite', product_key: null, has_product_key: true, seats: 3, free_seats: 1, low: false,
    category_id: null, manufacturer_id: null, supplier_id: null, depreciation_id: null,
    category_name: null, manufacturer_name: null, supplier_name: null, depreciation_name: null,
    licensed_to_name: null, licensed_to_email: null, reassignable: false, maintained: false, requestable: false,
    purchase_date: null, purchase_cost: null, order_number: null, purchase_order: null,
    expiration_date: null, termination_date: null, min_amt: null, notes: null,
};
const seats = [
    { id: 's1', retired: false, holder: { type: 'user' as const, name: 'Ada', url: '/app/users/u1' }, checked_out_at: '2026-11-01', notes: null },
    { id: 's2', retired: true, holder: null, checked_out_at: null, notes: null },
    { id: 's3', retired: false, holder: null, checked_out_at: null, notes: null },
];

describe('License page', () => {
    beforeEach(() => { setCan({}); post.mockClear(); });

    it('lists seats as checked out, used up and free', () => {
        render(<ShowLicense license={license} seats={seats} history={[]} />);
        expect(screen.getByRole('link', { name: 'User: Ada' })).toHaveAttribute('href', '/app/users/u1');
        expect(screen.getByText('Used up')).toBeInTheDocument();
        expect(screen.getByText('Free')).toBeInTheDocument();
        expect(screen.getByText('1 of 3 free')).toBeInTheDocument();
    });

    it('offers check-out, check-in and edit only with their permissions', () => {
        const { unmount } = render(<ShowLicense license={license} seats={seats} history={[]} />);
        expect(screen.queryByRole('button', { name: /Check out seat/ })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Check in/ })).not.toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'Edit' })).not.toBeInTheDocument();
        unmount();

        setCan({ 'licenses.checkout': true, 'licenses.checkin': true, 'licenses.edit': true });
        render(<ShowLicense license={license} seats={seats} history={[]} />);
        expect(screen.getByRole('button', { name: /Check out seat/ })).toBeEnabled();
        expect(screen.getByRole('link', { name: 'Edit' })).toBeInTheDocument();

        const confirm = vi.fn(() => true);
        vi.stubGlobal('confirm', confirm);
        fireEvent.click(screen.getByRole('button', { name: /Check in/ }));
        expect(confirm).toHaveBeenCalledWith(expect.stringContaining('used up'));
        vi.unstubAllGlobals();
        expect(post).toHaveBeenCalledWith('/app/license-seats/s1/checkin', {}, { preserveScroll: true });
    });

    it('disables check-out without free seats', () => {
        setCan({ 'licenses.checkout': true });
        render(<ShowLicense license={{ ...license, free_seats: 0 }} seats={seats} history={[]} />);
        expect(screen.getByRole('button', { name: /Check out seat/ })).toBeDisabled();
    });
});
