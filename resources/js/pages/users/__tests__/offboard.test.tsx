import { useRef, useState } from 'react';
import { render, screen, fireEvent, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const post = vi.fn();
vi.mock('@inertiajs/react', () => ({
    useForm: <T extends Record<string, unknown>>(initial: T) => {
        const [data, setAll] = useState(initial);
        const transform = useRef<(d: T) => unknown>((d) => d);
        return {
            data,
            setData: (k: string, v: unknown) => setAll((d) => ({ ...d, [k]: v })),
            transform: (fn: (d: T) => unknown) => { transform.current = fn; },
            post: (url: string) => post(url, transform.current(data)),
            processing: false,
            errors: {},
        };
    },
    Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
    usePage: () => ({ props: {} }),
}));
vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

import UserOffboard from '../offboard';

const all = { checkin: true, transfer: true };
const props = {
    user: { id: 'u1', name: 'Lea Leaver', email: 'lea@x.test', login_enabled: true, department_name: 'Finance' },
    assets: [
        { id: 'a1', name: 'Laptop', asset_tag: 'INV-1', model_name: 'ThinkPad', serial: 'SN1', status_name: 'In use', location_name: null },
        { id: 'a2', name: 'Phone', asset_tag: 'INV-2', model_name: 'Pixel', serial: 'SN2', status_name: 'In use', location_name: null },
    ],
    licenses: [{ seat_id: 's1', license_id: 'l1', name: 'Office 365', checked_out_at: '2026-01-01' }],
    accessories: [{ checkout_id: 'c1', accessory_id: 'x1', name: 'Headset', checked_out_at: null }],
    consumables: [{ id: 'k1', consumable_id: 'k', name: 'Toner', qty: 2, checked_out_at: null }],
    abilities: { assets: all, licenses: all, accessories: all },
    canDisableLogin: true,
    transferOptions: [{ value: 'u2', label: 'Hal Heir' }],
    userCreateUrl: '/app/users/quick',
    statusOptions: [{ value: 'st', label: 'Storage' }],
    locationOptions: [],
    checkinStatusId: 'st',
};

const actionFor = (name: string, action: string) =>
    within(screen.getByRole('radiogroup', { name: `Action for ${name}` })).getByLabelText(action);

describe('UserOffboard', () => {
    beforeEach(() => post.mockClear());

    it('renders the header and all sections from the props', () => {
        render(<UserOffboard {...props} />);
        expect(screen.getByText('Offboarding Lea Leaver')).toBeInTheDocument();
        expect(screen.getByText('Department: Finance')).toBeInTheDocument();
        expect(screen.getByText('Assets (2)')).toBeInTheDocument();
        expect(screen.getByText('Licenses (1)')).toBeInTheDocument();
        expect(screen.getByText('Accessories (1)')).toBeInTheDocument();
        expect(screen.getByText('Toner')).toBeInTheDocument();
        expect(actionFor('Laptop', 'Check in')).toBeChecked();
        expect(screen.getByLabelText('Disable login')).toBeInTheDocument();
    });

    it('reveals the user picker once a row is set to Transfer', () => {
        render(<UserOffboard {...props} />);
        expect(screen.queryByLabelText('Transfer to')).not.toBeInTheDocument();
        fireEvent.click(actionFor('Office 365', 'Transfer'));
        expect(screen.getByLabelText('Transfer to')).toBeInTheDocument();
    });

    it('leaves Keep rows out of the submitted payload', () => {
        render(<UserOffboard {...props} />);
        fireEvent.click(actionFor('Phone', 'Keep'));
        fireEvent.click(actionFor('Headset', 'Keep'));
        fireEvent.click(screen.getByRole('button', { name: /^Offboard/ }));

        expect(post).toHaveBeenCalledTimes(1);
        const [url, payload] = post.mock.calls[0];
        expect(url).toBe('/app/users/u1/offboard');
        expect(payload).toMatchObject({
            items: [
                { type: 'asset', id: 'a1', action: 'checkin' },
                { type: 'license_seat', id: 's1', action: 'checkin' },
            ],
            transfer_to: null,
            status_id: 'st',
            disable_login: false,
        });
        expect(payload.items).toHaveLength(2);
    });

    it('sets a whole section at once', () => {
        render(<UserOffboard {...props} />);
        fireEvent.click(within(screen.getByText('Assets (2)').closest('[data-slot="card"]') as HTMLElement).getByRole('button', { name: 'Set all to keep' }));
        expect(actionFor('Laptop', 'Keep')).toBeChecked();
        expect(actionFor('Phone', 'Keep')).toBeChecked();
        expect(actionFor('Office 365', 'Check in')).toBeChecked();
    });

    it('does not offer actions the viewer lacks', () => {
        render(<UserOffboard {...props} canDisableLogin={false}
            abilities={{ assets: { checkin: true, transfer: false }, licenses: { checkin: false, transfer: false }, accessories: all }} />);
        expect(within(screen.getByRole('radiogroup', { name: 'Action for Laptop' })).queryByLabelText('Transfer')).not.toBeInTheDocument();
        expect(within(screen.getByRole('radiogroup', { name: 'Action for Office 365' })).queryByLabelText('Check in')).not.toBeInTheDocument();
        expect(actionFor('Office 365', 'Keep')).toBeChecked();
        expect(screen.queryByLabelText('Disable login')).not.toBeInTheDocument();
    });
});
