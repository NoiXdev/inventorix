import { useState } from 'react';
import { render, screen, fireEvent, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const post = vi.fn();
vi.mock('@inertiajs/react', () => ({
    useForm: <T extends Record<string, unknown>>(initial: T) => {
        const [data, setAll] = useState(initial);
        return {
            data,
            setData: (k: string, v: unknown) => setAll((d) => ({ ...d, [k]: v })),
            post: (...a: unknown[]) => post(...a, data),
            processing: false,
            errors: {},
        };
    },
    Link: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => <a href={href} {...rest}>{children}</a>,
    router: { reload: vi.fn(), get: vi.fn() },
}));
vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

import BulkCheckout, { type BulkAsset } from '../bulk-checkout';

const asset = (id: string, name: string, tag: string, serial: string): BulkAsset =>
    ({ id, name, asset_tag: tag, serial, model_name: 'ThinkPad', status_name: 'Lager' });

const props = {
    availableAssets: [asset('a1', 'Laptop 1', 'INV-001', 'SN111'), asset('a2', 'Laptop 2', 'INV-002', 'SN222'), asset('a3', 'Phone', 'INV-010', 'SN333')],
    preselectedIds: ['a1'],
    skipped: [{ name: 'Old laptop', reason: 'The asset is already checked out. Check it in first.' }],
    checkoutOptions: {
        assigneeOptions: { user: [{ value: 'u1', label: 'Ada' }], location: [], asset: [] },
        userCreateUrl: '/app/users/quick',
        deployableStatusOptions: [{ value: 's-use', label: 'In Benutzung' }],
        statusOptions: [{ value: 's-use', label: 'In Benutzung' }],
        locationOptions: [],
        checkoutStatusId: 's-use',
        checkinStatusId: 's-store',
        rtdLocationName: null,
    },
    maxAssets: 500,
};

const rows = () => within(screen.getAllByRole('rowgroup')[1]).getAllByRole('row');
const search = () => screen.getByLabelText('Add asset');
const scan = (code: string) => {
    fireEvent.change(search(), { target: { value: code } });
    fireEvent.keyDown(search(), { key: 'Enter' });
};

describe('Bulk checkout page', () => {
    beforeEach(() => post.mockClear());

    it('starts with the list selection and names what was left out', () => {
        render(<BulkCheckout {...props} />);
        expect(rows()).toHaveLength(1);
        expect(screen.getByText('Laptop 1')).toBeInTheDocument();
        expect(screen.getByText(/Old laptop/)).toBeInTheDocument();
    });

    it('adds a scanned asset tag or serial on Enter, once', () => {
        render(<BulkCheckout {...props} />);
        scan('inv-002');
        scan('SN333');
        expect(rows()).toHaveLength(3);
        scan('INV-002');
        expect(rows()).toHaveLength(3);
        expect(screen.getByText('Laptop 2 is already in the list.')).toBeInTheDocument();
    });

    it('says when nothing available matches', () => {
        render(<BulkCheckout {...props} />);
        scan('INV-999');
        expect(screen.getByText(/No available asset matches/)).toBeInTheDocument();
    });

    it('adds from the suggestions and removes again', () => {
        render(<BulkCheckout {...props} />);
        fireEvent.change(search(), { target: { value: 'phone' } });
        fireEvent.click(screen.getByRole('option').querySelector('button')!);
        expect(rows()).toHaveLength(2);
        fireEvent.click(screen.getByRole('button', { name: 'Remove Laptop 1' }));
        expect(rows()).toHaveLength(1);
        expect(screen.getByText('Phone')).toBeInTheDocument();
    });

    it('posts the selected assets with the checkout fields', () => {
        render(<BulkCheckout {...props} />);
        scan('INV-002');
        fireEvent.click(screen.getByRole('button', { name: 'Check out 2' }));
        expect(post).toHaveBeenCalledTimes(1);
        const [url, data] = post.mock.calls[0];
        expect(url).toBe('/app/assets/bulk-checkout');
        expect(data).toMatchObject({ asset_ids: ['a1', 'a2'], assignee_type: 'user', status_id: 's-use' });
    });

    it('cannot submit without assets', () => {
        render(<BulkCheckout {...props} preselectedIds={[]} />);
        expect(screen.getByText('No assets selected yet.')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /^Check out/ })).toBeDisabled();
    });
});
